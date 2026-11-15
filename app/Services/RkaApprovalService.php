<?php

namespace App\Services;

use App\Models\DepartmentBudget;
use App\Models\RkaApproval;
use App\Models\RkaHeader;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RkaApprovalService
{
    /**
     * 1. Pemohon Mengajukan RKA (DRAFT -> SUBMITTED)
     */
    public function submitByPemohon(RkaHeader $rkaHeader, User $user, ?string $comments = null, ?string $signatureHash = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments, $signatureHash) {
            $oldStatus = $rkaHeader->status;
            $isResubmission = strtoupper((string) $oldStatus) === RkaHeader::STATUS_REVISION_REQUIRED;

            $rkaHeader->update([
                'status' => RkaHeader::STATUS_SUBMITTED,
                'is_locked' => true,
                'submitted_at' => now(),
            ]);

            $approval = $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_KA_UNIT_SUBMIT,
                'action' => RkaApproval::ACTION_SUBMITTED,
                'comments' => $comments ?? 'Pengajuan RKA dari unit kerja.',
                'digital_signature_hash' => $signatureHash,
            ]);

            app(RkaActivityLogService::class)->log(
                action: $isResubmission ? RkaLog::ACTION_RESUBMIT_RKA : RkaLog::ACTION_SUBMIT_RKA,
                headerId: $rkaHeader->id,
                subject: $approval,
                description: $isResubmission ? 'RKA diajukan kembali setelah revisi.' : 'RKA diajukan oleh pemohon.',
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => RkaHeader::STATUS_SUBMITTED],
                userId: $user->id,
            );

            return $approval;
        });
    }

    /**
     * 2. Wadir Keuangan Memulai Review RKA (SUBMITTED -> IN_REVIEW)
     */
    public function startReviewByWadir(RkaHeader $rkaHeader, User $user, ?string $comments = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments) {
            $oldStatus = $rkaHeader->status;

            $rkaHeader->update([
                'status' => RkaHeader::STATUS_IN_REVIEW,
                'is_locked' => true,
            ]);

            $approval = $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_WADIR2_REVIEW,
                'action' => RkaApproval::ACTION_SUBMITTED,
                'comments' => $comments ?? 'Wadir Keuangan mulai menelaah RKA.',
            ]);

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_START_REVIEW,
                headerId: $rkaHeader->id,
                subject: $approval,
                description: 'RKA mulai direview.',
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => RkaHeader::STATUS_IN_REVIEW],
                userId: $user->id,
            );

            return $approval;
        });
    }

    /**
     * 3. Wadir Keuangan Menyetujui & Meneruskan ke Direktur (IN_REVIEW / REVISION_REQUIRED -> IN_APPROVED)
     */
    public function forwardToDirektur(RkaHeader $rkaHeader, User $user, ?string $comments = null, ?string $signatureHash = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments, $signatureHash) {
            $oldStatus = $rkaHeader->status;

            $rkaHeader->update([
                'status' => RkaHeader::STATUS_IN_APPROVED,
                'is_locked' => true,
            ]);

            $approval = $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_WADIR2_REVIEW,
                'action' => RkaApproval::ACTION_APPROVED,
                'comments' => $comments ?? 'RKA disetujui Wadir Keuangan dan diteruskan ke Direktur.',
                'digital_signature_hash' => $signatureHash,
            ]);

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_FORWARD_DIREKTUR,
                headerId: $rkaHeader->id,
                subject: $approval,
                description: 'RKA diteruskan ke Direktur.',
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => RkaHeader::STATUS_IN_APPROVED],
                userId: $user->id,
            );

            return $approval;
        });
    }

    /**
     * 4A. Direktur Menyetujui Akhir / Pengesahan (IN_APPROVED -> APPROVED)
     */
    public function approveByDirektur(RkaHeader $rkaHeader, User $user, ?string $comments = null, ?string $signatureHash = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments, $signatureHash) {
            // 1. Update status RKA Header menjadi APPROVED dan lock dokumen
            $oldStatus = $rkaHeader->status;
            $rkaHeader->update([
                'status' => RkaHeader::STATUS_APPROVED,
                'is_locked' => true,
            ]);

            // 2. Kelompokkan item RKA yang disetujui berdasarkan account_id dan jumlahkan approved_total-nya
            $approvedTotalsByAccount = $rkaHeader->items()
                ->whereIn('status', [
                    RkaItem::STATUS_APPROVED,
                    RkaItem::STATUS_ACCEPTED,
                    RkaItem::STATUS_REDUCED,
                ])
                ->select('account_id', DB::raw('SUM(approved_total) as total_approved'))
                ->groupBy('account_id')
                ->get();

            $fiscalYear = $rkaHeader->schedule->fiscal_year ?? date('Y');
            $approvedAccountIds = [];

            // 3. Update atau buat record DepartmentBudget berdasarkan unique key (department_id, account_id, fiscal_year)
            foreach ($approvedTotalsByAccount as $itemSum) {
                $approvedAccountIds[] = $itemSum->account_id;

                DepartmentBudget::updateOrCreate(
                    [
                        'department_id' => $rkaHeader->department_id,
                        'account_id' => $itemSum->account_id,
                        'fiscal_year' => $fiscalYear,
                    ],
                    [
                        'schedule_id' => $rkaHeader->schedule_id,
                        'allocated_amount' => $itemSum->total_approved,
                    ]
                );
            }

            // Nolkan alokasi pada schedule yang sama jika seluruh item pada akun tersebut ditolak/dihapus saat revisi
            DepartmentBudget::query()
                ->where('department_id', $rkaHeader->department_id)
                ->where('fiscal_year', $fiscalYear)
                ->where('schedule_id', $rkaHeader->schedule_id)
                ->when(
                    ! empty($approvedAccountIds),
                    fn ($query) => $query->whereNotIn('account_id', $approvedAccountIds)
                )
                ->update(['allocated_amount' => 0]);

            // 4. Catat riwayat/log approval
            $approval = $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_DIREKTUR_APPROVE,
                'action' => RkaApproval::ACTION_APPROVED,
                'comments' => $comments ?? 'RKA resmi disetujui dan disahkan oleh Direktur.',
                'digital_signature_hash' => $signatureHash,
            ]);

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_APPROVE_RKA,
                headerId: $rkaHeader->id,
                subject: $approval,
                description: 'RKA disetujui oleh Direktur.',
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => RkaHeader::STATUS_APPROVED],
                userId: $user->id,
            );

            return $approval;
        });
    }

    /**
     * 4B. Direktur Meminta Revisi ke Wadir Keuangan (IN_APPROVED -> REVISION_REQUIRED)
     */
    public function requireRevisionByDirektur(RkaHeader $rkaHeader, User $user, string $comments, ?string $signatureHash = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments, $signatureHash) {
            $oldStatus = $rkaHeader->status;

            $rkaHeader->update([
                'status' => RkaHeader::STATUS_REVISION_REQUIRED,
                'is_locked' => true,
            ]);

            $approval = $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_DIREKTUR_APPROVE,
                'action' => RkaApproval::ACTION_REVISED,
                'comments' => $comments,
                'digital_signature_hash' => $signatureHash,
            ]);

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_REQUEST_REVISION,
                headerId: $rkaHeader->id,
                subject: $approval,
                description: 'RKA dikembalikan untuk revisi.',
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => RkaHeader::STATUS_REVISION_REQUIRED,
                    'comments' => $comments,
                ],
                userId: $user->id,
            );

            return $approval;
        });
    }

    /**
     * Helper privat untuk mencatat audit trail persetujuan
     */
    private function logApproval(array $data): RkaApproval
    {
        return RkaApproval::create([
            'rka_header_id' => $data['rka_header_id'],
            'approver_user_id' => $data['approver_user_id'],
            'stage' => $data['stage'],
            'action' => $data['action'],
            'comments' => $data['comments'] ?? null,
            'digital_signature_hash' => $data['digital_signature_hash'] ?? null,
            'signed_at' => now(),
        ]);
    }
}
