<?php

namespace App\Services;

use App\Models\DepartmentBudget;
use App\Models\RkaApproval;
use App\Models\RkaHeader;
use App\Models\RkaItem;
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
            $rkaHeader->update([
                'status' => RkaHeader::STATUS_SUBMITTED,
                'is_locked' => true,
                'submitted_at' => now(),
            ]);

            return $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_KA_UNIT_SUBMIT,
                'action' => RkaApproval::ACTION_SUBMITTED,
                'comments' => $comments ?? 'Pengajuan RKA dari unit kerja.',
                'digital_signature_hash' => $signatureHash,
            ]);
        });
    }

    /**
     * 2. Wadir Keuangan Memulai Review RKA (SUBMITTED -> IN_REVIEW)
     */
    public function startReviewByWadir(RkaHeader $rkaHeader, User $user, ?string $comments = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments) {
            $rkaHeader->update([
                'status' => RkaHeader::STATUS_IN_REVIEW,
                'is_locked' => true,
            ]);

            return $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_WADIR2_REVIEW,
                'action' => RkaApproval::ACTION_SUBMITTED,
                'comments' => $comments ?? 'Wadir Keuangan mulai menelaah RKA.',
            ]);
        });
    }

    /**
     * 3. Wadir Keuangan Menyetujui & Meneruskan ke Direktur (IN_REVIEW / REVISION_REQUIRED -> IN_APPROVED)
     */
    public function forwardToDirektur(RkaHeader $rkaHeader, User $user, ?string $comments = null, ?string $signatureHash = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments, $signatureHash) {
            $rkaHeader->update([
                'status' => RkaHeader::STATUS_IN_APPROVED,
                'is_locked' => true,
            ]);

            return $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_WADIR2_REVIEW,
                'action' => RkaApproval::ACTION_APPROVED,
                'comments' => $comments ?? 'RKA disetujui Wadir Keuangan dan diteruskan ke Direktur.',
                'digital_signature_hash' => $signatureHash,
            ]);
        });
    }

    /**
     * 4A. Direktur Menyetujui Akhir / Pengesahan (IN_APPROVED -> APPROVED)
     */
    public function approveByDirektur(RkaHeader $rkaHeader, User $user, ?string $comments = null, ?string $signatureHash = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments, $signatureHash) {
            // 1. Update status RKA Header menjadi APPROVED dan lock dokumen
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

            // 3. Update atau buat record DepartmentBudget untuk setiap account_id
            foreach ($approvedTotalsByAccount as $itemSum) {
                DepartmentBudget::updateOrCreate(
                    [
                        'department_id' => $rkaHeader->department_id,
                        'account_id' => $itemSum->account_id,
                        'schedule_id' => $rkaHeader->schedule_id,
                    ],
                    [
                        'fiscal_year' => $rkaHeader->schedule->fiscal_year ?? date('Y'),
                        'allocated_amount' => $itemSum->total_approved,
                    ]
                );
            }

            // 4. Catat riwayat/log approval
            return $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_DIREKTUR_APPROVE,
                'action' => RkaApproval::ACTION_APPROVED,
                'comments' => $comments ?? 'RKA resmi disetujui dan disahkan oleh Direktur.',
                'digital_signature_hash' => $signatureHash,
            ]);
        });
    }

    /**
     * 4B. Direktur Meminta Revisi ke Wadir Keuangan (IN_APPROVED -> REVISION_REQUIRED)
     */
    public function requireRevisionByDirektur(RkaHeader $rkaHeader, User $user, string $comments, ?string $signatureHash = null): RkaApproval
    {
        return DB::transaction(function () use ($rkaHeader, $user, $comments, $signatureHash) {
            $rkaHeader->update([
                'status' => RkaHeader::STATUS_REVISION_REQUIRED,
                'is_locked' => true,
            ]);

            return $this->logApproval([
                'rka_header_id' => $rkaHeader->id,
                'approver_user_id' => $user->id,
                'stage' => RkaApproval::STAGE_DIREKTUR_APPROVE,
                'action' => RkaApproval::ACTION_REVISED,
                'comments' => $comments,
                'digital_signature_hash' => $signatureHash,
            ]);
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
