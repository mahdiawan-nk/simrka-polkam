<?php

namespace App\Services\ApprovedRkaImport;

use App\Models\RkaHeader;
use App\Models\RkaLog;
use App\Models\RkaUnlockRequest;
use App\Models\User;
use App\Services\RkaActivityLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service untuk alur permintaan pembukaan kunci (Unlock Request) dan persetujuan oleh tim keuangan.
 */
class RkaUnlockRequestService
{
    public function __construct(
        public RkaActivityLogService $activityLogService,
    ) {}

    /**
     * Mengajukan permintaan pembukaan kunci untuk RKA yang berstatus APPROVED dan terkunci.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function submitRequest(
        RkaHeader $header,
        User $user,
        string $reason,
        ?string $errorDescription = null,
    ): RkaUnlockRequest {
        if (! $user->can('requestUnlock', $header)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengajukan pembukaan kunci pada RKA ini.');
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('Alasan permintaan pembukaan kunci wajib diisi.');
        }

        $trimmedErrorDescription = $errorDescription !== null ? trim($errorDescription) : null;
        if ($trimmedErrorDescription === '') {
            $trimmedErrorDescription = null;
        }

        return DB::transaction(function () use ($header, $user, $trimmedReason, $trimmedErrorDescription): RkaUnlockRequest {
            /** @var RkaHeader $lockedHeader */
            $lockedHeader = RkaHeader::query()
                ->whereKey($header->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (strtoupper((string) $lockedHeader->status) !== RkaHeader::STATUS_APPROVED) {
                throw new InvalidArgumentException('Permintaan pembukaan kunci hanya dapat diajukan untuk RKA yang berstatus APPROVED.');
            }

            if (! $lockedHeader->is_locked) {
                throw new InvalidArgumentException('RKA ini tidak dalam kondisi terkunci.');
            }

            $existingActive = RkaUnlockRequest::query()
                ->where('rka_header_id', $lockedHeader->id)
                ->whereIn('status', [
                    RkaUnlockRequest::STATUS_PENDING,
                    RkaUnlockRequest::STATUS_APPROVED,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($existingActive as $existing) {
                if ($existing->status === RkaUnlockRequest::STATUS_PENDING) {
                    throw new InvalidArgumentException('Masih terdapat permintaan pembukaan kunci yang menunggu persetujuan tim keuangan.');
                }

                if ($existing->status === RkaUnlockRequest::STATUS_APPROVED && $existing->isReimportActive()) {
                    throw new InvalidArgumentException('Permintaan pembukaan kunci sebelumnya sudah disetujui dan hak import ulang masih aktif.');
                }

                if ($existing->status === RkaUnlockRequest::STATUS_APPROVED && ! $existing->isReimportActive() && $existing->reimport_used_at === null) {
                    $existing->update(['status' => RkaUnlockRequest::STATUS_EXPIRED]);
                }
            }

            $unlockRequest = RkaUnlockRequest::create([
                'rka_header_id' => $lockedHeader->id,
                'department_id' => $lockedHeader->department_id,
                'requested_by' => $user->id,
                'reason' => $trimmedReason,
                'error_description' => $trimmedErrorDescription,
                'status' => RkaUnlockRequest::STATUS_PENDING,
            ]);

            $this->activityLogService->log(
                action: RkaLog::ACTION_UNLOCK_REQUESTED,
                headerId: $lockedHeader->id,
                subject: $unlockRequest,
                description: "Mengajukan pembukaan kunci RKA Approved #{$lockedHeader->id}",
                newValues: [
                    'unlock_request_id' => $unlockRequest->id,
                    'status' => $unlockRequest->status,
                    'reason' => $unlockRequest->reason,
                    'error_description' => $unlockRequest->error_description,
                ],
                userId: $user->id,
            );

            return $unlockRequest;
        });
    }

    /**
     * Menyetujui permintaan pembukaan kunci oleh tim keuangan dan memberikan hak import ulang satu kali.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function approveRequest(
        RkaUnlockRequest $request,
        User $financeUser,
        ?string $notes = null,
        int $validHours = 24,
    ): RkaUnlockRequest {
        if (! $financeUser->can('approve', $request)) {
            throw new AuthorizationException('Hanya tim keuangan yang berwenang menyetujui permintaan pembukaan kunci.');
        }

        if ($validHours < 1) {
            throw new InvalidArgumentException('Durasi masa berlaku hak import ulang minimal 1 jam.');
        }

        $trimmedNotes = $notes !== null ? trim($notes) : null;
        if ($trimmedNotes === '') {
            $trimmedNotes = null;
        }

        return DB::transaction(function () use ($request, $financeUser, $trimmedNotes, $validHours): RkaUnlockRequest {
            /** @var RkaUnlockRequest $lockedRequest */
            $lockedRequest = RkaUnlockRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== RkaUnlockRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('Hanya permintaan berstatus PENDING yang dapat disetujui.');
            }

            /** @var RkaHeader $header */
            $header = RkaHeader::query()
                ->whereKey($lockedRequest->rka_header_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (strtoupper((string) $header->status) !== RkaHeader::STATUS_APPROVED) {
                throw new InvalidArgumentException('Status RKA harus tetap APPROVED untuk menyetujui permintaan pembukaan kunci.');
            }

            $now = now();
            $expiresAt = $now->copy()->addHours($validHours);

            $lockedRequest->update([
                'status' => RkaUnlockRequest::STATUS_APPROVED,
                'reviewed_by' => $financeUser->id,
                'decision_notes' => $trimmedNotes,
                'reviewed_at' => $now,
                'reimport_granted_at' => $now,
                'reimport_expires_at' => $expiresAt,
            ]);

            $this->activityLogService->log(
                action: RkaLog::ACTION_UNLOCK_APPROVED,
                headerId: $header->id,
                subject: $lockedRequest,
                description: "Tim keuangan menyetujui permintaan pembukaan kunci #{$lockedRequest->id} (berlaku hingga {$expiresAt->format('d/m/Y H:i')})",
                oldValues: [
                    'status' => RkaUnlockRequest::STATUS_PENDING,
                ],
                newValues: [
                    'status' => RkaUnlockRequest::STATUS_APPROVED,
                    'reviewed_by' => $financeUser->id,
                    'decision_notes' => $trimmedNotes,
                    'reimport_granted_at' => $now->toIso8601String(),
                    'reimport_expires_at' => $expiresAt->toIso8601String(),
                ],
                userId: $financeUser->id,
            );

            return $lockedRequest->fresh();
        });
    }

    /**
     * Menolak permintaan pembukaan kunci oleh tim keuangan dengan catatan alasan penolakan wajib.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function rejectRequest(
        RkaUnlockRequest $request,
        User $financeUser,
        string $notes,
    ): RkaUnlockRequest {
        if (! $financeUser->can('reject', $request)) {
            throw new AuthorizationException('Hanya tim keuangan yang berwenang menolak permintaan pembukaan kunci.');
        }

        $trimmedNotes = trim($notes);
        if ($trimmedNotes === '') {
            throw new InvalidArgumentException('Catatan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($request, $financeUser, $trimmedNotes): RkaUnlockRequest {
            /** @var RkaUnlockRequest $lockedRequest */
            $lockedRequest = RkaUnlockRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== RkaUnlockRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('Hanya permintaan berstatus PENDING yang dapat ditolak.');
            }

            $now = now();

            $lockedRequest->update([
                'status' => RkaUnlockRequest::STATUS_REJECTED,
                'reviewed_by' => $financeUser->id,
                'decision_notes' => $trimmedNotes,
                'reviewed_at' => $now,
            ]);

            $this->activityLogService->log(
                action: RkaLog::ACTION_UNLOCK_REJECTED,
                headerId: $lockedRequest->rka_header_id,
                subject: $lockedRequest,
                description: "Tim keuangan menolak permintaan pembukaan kunci #{$lockedRequest->id}",
                oldValues: [
                    'status' => RkaUnlockRequest::STATUS_PENDING,
                ],
                newValues: [
                    'status' => RkaUnlockRequest::STATUS_REJECTED,
                    'reviewed_by' => $financeUser->id,
                    'decision_notes' => $trimmedNotes,
                ],
                userId: $financeUser->id,
            );

            return $lockedRequest->fresh();
        });
    }
}
