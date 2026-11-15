<?php

declare(strict_types=1);

namespace App\Services\Rka\Reconciliation;

use App\Models\RkaAdjustment;
use App\Models\RkaAdjustmentMatch;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaLog;
use App\Models\RkaReconciliationHistory;
use App\Models\User;
use App\Services\RkaActivityLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RkaReconciliationService
{
    public function __construct(
        private readonly CrossUnitItemMatcher $matcher,
        private readonly RkaActivityLogService $activityLogService,
    ) {}

    /**
     * Menjalankan rekonsiliasi lintas unit untuk satu RkaHeader (beserta re-evaluasi antrean unit lain pada schedule sama).
     *
     * @return array<string, int>
     */
    public function reconcileForHeader(
        RkaHeader $header,
        ?RkaApprovedImportBatch $batch = null,
        ?int $actorId = null,
        string $source = 'NATIVE_IMPORT',
        bool $forceRerun = false,
    ): array {
        $lock = Cache::lock("rka_reconciliation_schedule_{$header->schedule_id}", 15);

        return $lock->block(10, function () use ($header, $batch, $actorId, $source, $forceRerun): array {
            return DB::transaction(function () use ($header, $batch, $actorId, $source, $forceRerun): array {
                $header->loadMissing(['department', 'schedule']);
                $scheduleId = (int) $header->schedule_id;

                $stats = [
                    'matched' => 0,
                    'waiting' => 0,
                    'review_required' => 0,
                    'conflict' => 0,
                    'unmatched' => 0,
                    'skipped' => 0,
                ];

                $allScheduleAdjustments = RkaAdjustment::query()
                    ->with(['rkaHeader.department', 'account', 'rkaItem', 'importedRkaItem'])
                    ->whereHas('rkaHeader', fn ($q) => $q->where('schedule_id', $scheduleId))
                    ->get();

                $headersByDept = RkaHeader::query()
                    ->where('schedule_id', $scheduleId)
                    ->get()
                    ->keyBy('department_id');

                $latestBatchByHeader = RkaApprovedImportBatch::query()
                    ->where('schedule_id', $scheduleId)
                    ->where('status', RkaApprovedImportBatch::STATUS_SUCCESS)
                    ->orderByDesc('id')
                    ->get()
                    ->unique('rka_header_id')
                    ->keyBy('rka_header_id');

                // 1. Re-evaluasi antrean / match yang belum final (atau kehilangan counterpart akibat reimport) di schedule yang sama
                $pendingMatches = RkaAdjustmentMatch::query()
                    ->with(['sourceAdjustment.rkaHeader.department', 'sourceAdjustment.account'])
                    ->where('schedule_id', $scheduleId)
                    ->where(function ($query): void {
                        $query->whereIn('reconciliation_status', [
                            RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART,
                            RkaAdjustmentMatch::STATUS_UNMATCHED,
                            RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED,
                            RkaAdjustmentMatch::STATUS_CONFLICT,
                        ])->orWhere(function ($sub): void {
                            $sub->where('reconciliation_status', RkaAdjustmentMatch::STATUS_MATCHED)
                                ->whereNull('counterpart_adjustment_id')
                                ->whereNull('reviewed_by');
                        });
                    })
                    ->get();

                foreach ($pendingMatches as $existingMatch) {
                    $srcAdj = $existingMatch->sourceAdjustment;
                    if (! $srcAdj || ! $srcAdj->rkaHeader) {
                        continue;
                    }

                    if ($existingMatch->reviewed_by !== null && ! $forceRerun) {
                        continue;
                    }

                    $result = $this->evaluateSingleAdjustment($srcAdj, $allScheduleAdjustments, $headersByDept);
                    $this->persistMatchResult(
                        sourceAdjustment: $srcAdj,
                        result: $result,
                        sourceBatchId: $existingMatch->source_import_batch_id ?? $latestBatchByHeader->get($srcAdj->rka_header_id)?->id,
                        latestBatchByHeader: $latestBatchByHeader,
                        actorId: $actorId,
                        historySource: (int) $srcAdj->rka_header_id === (int) $header->id ? $source : 'COUNTERPART_IMPORT',
                        stats: $stats,
                    );
                }

                // 2. Rekonsiliasi adjustment milik header yang baru diimport/diproses
                $pairedAsCounterpartIds = RkaAdjustmentMatch::query()
                    ->where('schedule_id', $scheduleId)
                    ->whereIn('reconciliation_status', [
                        RkaAdjustmentMatch::STATUS_MATCHED,
                        RkaAdjustmentMatch::STATUS_CONFLICT,
                    ])
                    ->whereNotNull('counterpart_adjustment_id')
                    ->pluck('counterpart_adjustment_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $headerAdjustments = $allScheduleAdjustments
                    ->where('rka_header_id', $header->id)
                    ->values();

                foreach ($headerAdjustments as $adj) {
                    if (! $this->matcher->requiresCrossUnitReconciliation($adj)) {
                        $stats['skipped']++;

                        continue;
                    }

                    if (in_array((int) $adj->id, $pairedAsCounterpartIds, true)) {
                        $stats['skipped']++;

                        continue;
                    }

                    $existingOwn = RkaAdjustmentMatch::query()
                        ->where('source_adjustment_id', $adj->id)
                        ->first();

                    if ($existingOwn && ($existingOwn->reconciliation_status === RkaAdjustmentMatch::STATUS_CANCELLED || ($existingOwn->reviewed_by !== null && ! $forceRerun))) {
                        $stats['skipped']++;

                        continue;
                    }

                    $result = $this->evaluateSingleAdjustment($adj, $allScheduleAdjustments, $headersByDept);
                    $match = $this->persistMatchResult(
                        sourceAdjustment: $adj,
                        result: $result,
                        sourceBatchId: $batch?->id ?? $latestBatchByHeader->get($header->id)?->id,
                        latestBatchByHeader: $latestBatchByHeader,
                        actorId: $actorId,
                        historySource: $source,
                        stats: $stats,
                    );

                    if ($match->counterpart_adjustment_id !== null && in_array($match->reconciliation_status, [
                        RkaAdjustmentMatch::STATUS_MATCHED,
                        RkaAdjustmentMatch::STATUS_CONFLICT,
                    ], true)) {
                        $pairedAsCounterpartIds[] = (int) $match->counterpart_adjustment_id;
                    }
                }

                $this->logReconciliationSummary($header, $stats, $actorId, $source, $batch?->id);

                return $stats;
            });
        });
    }

    /**
     * Menjalankan rekonsiliasi ulang seluruh header dalam satu schedule (dipicu oleh Finance).
     *
     * @return array<string, int>
     */
    public function rerunForSchedule(int $scheduleId, User $actor, string $reason): array
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new InvalidArgumentException('Alasan rekonsiliasi ulang wajib diisi.');
        }

        $headers = RkaHeader::query()
            ->where('schedule_id', $scheduleId)
            ->orderBy('id')
            ->get();

        $aggregated = [
            'matched' => 0,
            'waiting' => 0,
            'review_required' => 0,
            'conflict' => 0,
            'unmatched' => 0,
            'skipped' => 0,
        ];

        foreach ($headers as $header) {
            $res = $this->reconcileForHeader(
                header: $header,
                batch: null,
                actorId: $actor->id,
                source: 'FINANCE_RERUN',
                forceRerun: false,
            );

            foreach ($aggregated as $key => $val) {
                $aggregated[$key] += $res[$key] ?? 0;
            }
        }

        $this->activityLogService->log(
            action: RkaLog::ACTION_RECONCILIATION_RERUN,
            headerId: null,
            subject: null,
            description: "Finance menjalankan rekonsiliasi ulang untuk jadwal #{$scheduleId}: {$cleanReason}",
            newValues: [
                'schedule_id' => $scheduleId,
                'reason' => $cleanReason,
                'stats' => $aggregated,
            ],
            userId: $actor->id,
        );

        return $aggregated;
    }

    /**
     * Koreksi manual oleh Finance terhadap hasil rekonsiliasi tanpa mengubah isi RKA APPROVED.
     */
    public function financeAdjust(
        RkaAdjustmentMatch $match,
        User $actor,
        string $newStatus,
        ?int $counterpartAdjustmentId,
        ?int $targetDepartmentId,
        ?float $matchedAmount,
        string $reason,
    ): RkaAdjustmentMatch {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new InvalidArgumentException('Alasan koreksi rekonsiliasi wajib diisi.');
        }

        $allowedStatuses = [
            RkaAdjustmentMatch::STATUS_MATCHED,
            RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART,
            RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED,
            RkaAdjustmentMatch::STATUS_UNMATCHED,
            RkaAdjustmentMatch::STATUS_CONFLICT,
        ];

        if (! in_array($newStatus, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Status rekonsiliasi '{$newStatus}' tidak valid untuk koreksi.");
        }

        return DB::transaction(function () use (
            $match,
            $actor,
            $newStatus,
            $counterpartAdjustmentId,
            $targetDepartmentId,
            $matchedAmount,
            $cleanReason
        ): RkaAdjustmentMatch {
            $locked = RkaAdjustmentMatch::query()->lockForUpdate()->findOrFail($match->id);
            $before = $this->snapshotMatch($locked);

            $counterpartAdj = $counterpartAdjustmentId !== null
                ? RkaAdjustment::with('rkaHeader')->findOrFail($counterpartAdjustmentId)
                : null;

            $resolvedTargetDeptId = $targetDepartmentId
                ?? ($counterpartAdj?->rkaHeader?->department_id ? (int) $counterpartAdj->rkaHeader->department_id : $locked->target_department_id);

            $resolvedCounterpartItemId = $counterpartAdj?->imported_rka_item_id ?? $counterpartAdj?->rka_item_id;

            $details = is_array($locked->match_details) ? $locked->match_details : [];
            $details['finance_override'] = [
                'reason' => $cleanReason,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'updated_at' => now()->toDateTimeString(),
            ];

            $locked->update([
                'reconciliation_status' => $newStatus,
                'matching_level' => $newStatus === RkaAdjustmentMatch::STATUS_MATCHED
                    ? RkaAdjustmentMatch::LEVEL_MATCH_UNIQUE
                    : ($newStatus === RkaAdjustmentMatch::STATUS_CONFLICT
                        ? RkaAdjustmentMatch::LEVEL_MATCH_CONFLICT
                        : RkaAdjustmentMatch::LEVEL_MATCH_NONE),
                'counterpart_adjustment_id' => $counterpartAdj?->id,
                'counterpart_item_id' => $resolvedCounterpartItemId,
                'target_department_id' => $resolvedTargetDeptId,
                'matched_amount' => $matchedAmount !== null ? round($matchedAmount, 2) : $locked->matched_amount,
                'matching_rule' => 'MANUAL_FINANCE_ADJUSTMENT',
                'match_details' => $details,
                'matched_at' => $newStatus === RkaAdjustmentMatch::STATUS_MATCHED ? now() : $locked->matched_at,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $cleanReason,
            ]);

            $after = $this->snapshotMatch($locked->fresh());

            RkaReconciliationHistory::create([
                'rka_adjustment_match_id' => $locked->id,
                'action' => 'FINANCE_ADJUSTED',
                'actor_id' => $actor->id,
                'source' => 'FINANCE_MONITORING',
                'reason' => $cleanReason,
                'before_state' => $before,
                'after_state' => $after,
            ]);

            $this->activityLogService->log(
                action: RkaLog::ACTION_RECONCILIATION_ADJUSTED,
                headerId: $locked->sourceAdjustment?->rka_header_id,
                subject: $locked,
                description: "Finance mengoreksi rekonsiliasi #{$locked->id} menjadi {$newStatus}: {$cleanReason}",
                oldValues: $before,
                newValues: $after,
                userId: $actor->id,
            );

            return $locked->fresh();
        });
    }

    /**
     * Membatalkan relasi rekonsiliasi yang terbukti salah oleh Finance.
     */
    public function financeCancel(
        RkaAdjustmentMatch $match,
        User $actor,
        string $reason,
    ): RkaAdjustmentMatch {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new InvalidArgumentException('Alasan pembatalan relasi rekonsiliasi wajib diisi.');
        }

        return DB::transaction(function () use ($match, $actor, $cleanReason): RkaAdjustmentMatch {
            $locked = RkaAdjustmentMatch::query()->lockForUpdate()->findOrFail($match->id);
            $before = $this->snapshotMatch($locked);

            $details = is_array($locked->match_details) ? $locked->match_details : [];
            $details['finance_cancellation'] = [
                'reason' => $cleanReason,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'cancelled_at' => now()->toDateTimeString(),
            ];

            $locked->update([
                'reconciliation_status' => RkaAdjustmentMatch::STATUS_CANCELLED,
                'matching_level' => RkaAdjustmentMatch::LEVEL_MATCH_NONE,
                'counterpart_adjustment_id' => null,
                'counterpart_item_id' => null,
                'matched_amount' => null,
                'matching_rule' => 'FINANCE_CANCELLED',
                'match_details' => $details,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $cleanReason,
            ]);

            $after = $this->snapshotMatch($locked->fresh());

            RkaReconciliationHistory::create([
                'rka_adjustment_match_id' => $locked->id,
                'action' => 'FINANCE_CANCELLED',
                'actor_id' => $actor->id,
                'source' => 'FINANCE_MONITORING',
                'reason' => $cleanReason,
                'before_state' => $before,
                'after_state' => $after,
            ]);

            $this->activityLogService->log(
                action: RkaLog::ACTION_RECONCILIATION_CANCELLED,
                headerId: $locked->sourceAdjustment?->rka_header_id,
                subject: $locked,
                description: "Finance membatalkan relasi rekonsiliasi #{$locked->id}: {$cleanReason}",
                oldValues: $before,
                newValues: $after,
                userId: $actor->id,
            );

            return $locked->fresh();
        });
    }

    /**
     * @param  Collection<int, RkaAdjustment>  $allScheduleAdjustments
     * @param  Collection<int|string, RkaHeader>  $headersByDept
     * @return array<string, mixed>
     */
    private function evaluateSingleAdjustment(
        RkaAdjustment $source,
        Collection $allScheduleAdjustments,
        Collection $headersByDept,
    ): array {
        $expectedTargetDeptId = $this->matcher->expectedTargetDepartmentId($source);
        $sourceDeptId = (int) $source->rkaHeader->department_id;

        if ($expectedTargetDeptId !== null) {
            $counterpartHeaderExists = $headersByDept->has($expectedTargetDeptId);
        } else {
            // Section A MOVED tanpa unit tujuan eksplisit: dianggap ada counterpart hanya jika ada Section B dari unit lain yang menunjuk unit asal ini
            $counterpartHeaderExists = $allScheduleAdjustments->contains(function (RkaAdjustment $cand) use ($sourceDeptId): bool {
                return $cand->section_type === RkaAdjustment::SECTION_B
                    && (int) $cand->target_unit_id === $sourceDeptId;
            });
        }

        return $this->matcher->match($source, $allScheduleAdjustments, $counterpartHeaderExists);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  Collection<int|string, RkaApprovedImportBatch>  $latestBatchByHeader
     * @param  array<string, int>  $stats
     */
    private function persistMatchResult(
        RkaAdjustment $sourceAdjustment,
        array $result,
        ?int $sourceBatchId,
        Collection $latestBatchByHeader,
        ?int $actorId,
        string $historySource,
        array &$stats,
    ): RkaAdjustmentMatch {
        /** @var ?RkaAdjustment $counterpart */
        $counterpart = $result['counterpart_adjustment'];
        $status = (string) $result['reconciliation_status'];

        $existing = RkaAdjustmentMatch::query()
            ->where('source_adjustment_id', $sourceAdjustment->id)
            ->first();

        $before = $existing ? $this->snapshotMatch($existing) : null;

        $targetDeptId = $counterpart?->rkaHeader?->department_id
            ? (int) $counterpart->rkaHeader->department_id
            : $this->matcher->expectedTargetDepartmentId($sourceAdjustment);

        $sourceItemId = $sourceAdjustment->imported_rka_item_id ?? $sourceAdjustment->rka_item_id;
        $counterpartItemId = $counterpart?->imported_rka_item_id ?? $counterpart?->rka_item_id;
        $counterpartBatchId = $counterpart ? $latestBatchByHeader->get($counterpart->rka_header_id)?->id : null;

        $payload = [
            'schedule_id' => (int) $sourceAdjustment->rkaHeader->schedule_id,
            'counterpart_adjustment_id' => $counterpart?->id,
            'source_item_id' => $sourceItemId,
            'counterpart_item_id' => $counterpartItemId,
            'source_department_id' => (int) $sourceAdjustment->rkaHeader->department_id,
            'target_department_id' => $targetDeptId,
            'reconciliation_status' => $status,
            'matching_level' => (string) $result['matching_level'],
            'matched_amount' => $result['matched_amount'],
            'matching_rule' => (string) $result['matching_rule'],
            'match_details' => $result['match_details'],
            'source_import_batch_id' => $sourceBatchId,
            'counterpart_import_batch_id' => $counterpartBatchId,
            'matched_at' => $status === RkaAdjustmentMatch::STATUS_MATCHED ? ($existing?->matched_at ?? now()) : null,
        ];

        $match = RkaAdjustmentMatch::updateOrCreate(
            ['source_adjustment_id' => $sourceAdjustment->id],
            $payload
        );

        $after = $this->snapshotMatch($match);

        $changed = $before === null
            || $before['reconciliation_status'] !== $after['reconciliation_status']
            || $before['counterpart_adjustment_id'] !== $after['counterpart_adjustment_id']
            || $before['target_department_id'] !== $after['target_department_id'];

        if ($changed) {
            $historyAction = match ($status) {
                RkaAdjustmentMatch::STATUS_MATCHED => 'AUTO_MATCHED',
                RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART => 'AUTO_WAITING',
                RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED => 'AUTO_REVIEW_REQUIRED',
                RkaAdjustmentMatch::STATUS_CONFLICT => 'AUTO_CONFLICT',
                default => 'AUTO_UNMATCHED',
            };

            RkaReconciliationHistory::create([
                'rka_adjustment_match_id' => $match->id,
                'action' => $historyAction,
                'actor_id' => $actorId,
                'source' => $historySource,
                'reason' => (string) ($result['match_details']['reason'] ?? $historyAction),
                'before_state' => $before,
                'after_state' => $after,
            ]);
        }

        match ($status) {
            RkaAdjustmentMatch::STATUS_MATCHED => $stats['matched']++,
            RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART => $stats['waiting']++,
            RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED => $stats['review_required']++,
            RkaAdjustmentMatch::STATUS_CONFLICT => $stats['conflict']++,
            default => $stats['unmatched']++,
        };

        return $match;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function logReconciliationSummary(
        RkaHeader $header,
        array $stats,
        ?int $actorId,
        string $source,
        ?int $batchId,
    ): void {
        $action = $stats['conflict'] > 0
            ? RkaLog::ACTION_RECONCILIATION_CONFLICT
            : ($stats['matched'] > 0
                ? RkaLog::ACTION_RECONCILIATION_MATCHED
                : ($stats['waiting'] > 0 ? RkaLog::ACTION_RECONCILIATION_WAITING : RkaLog::ACTION_RECONCILIATION_STARTED));

        $idempotencyKey = sprintf(
            'reconcile:%d:%s:%d:%s',
            $header->id,
            $source,
            $batchId ?? 0,
            md5(json_encode($stats) ?: '')
        );

        $this->activityLogService->log(
            action: $action,
            headerId: $header->id,
            subject: $header,
            description: sprintf(
                'Rekonsiliasi lintas unit selesai (%s): %d matched, %d waiting, %d conflict, %d review_required, %d unmatched.',
                $source,
                $stats['matched'],
                $stats['waiting'],
                $stats['conflict'],
                $stats['review_required'],
                $stats['unmatched'],
            ),
            newValues: [
                'source' => $source,
                'batch_id' => $batchId,
                'stats' => $stats,
            ],
            userId: $actorId,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotMatch(RkaAdjustmentMatch $match): array
    {
        return [
            'reconciliation_status' => $match->reconciliation_status,
            'matching_level' => $match->matching_level,
            'counterpart_adjustment_id' => $match->counterpart_adjustment_id,
            'counterpart_item_id' => $match->counterpart_item_id,
            'target_department_id' => $match->target_department_id,
            'matched_amount' => $match->matched_amount !== null ? (float) $match->matched_amount : null,
            'matching_rule' => $match->matching_rule,
        ];
    }
}
