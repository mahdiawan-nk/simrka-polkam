<?php

declare(strict_types=1);

namespace App\Services\Rka\Reconciliation;

use App\Models\RkaAdjustment;
use App\Models\RkaAdjustmentMatch;
use Illuminate\Support\Collection;

class CrossUnitItemMatcher
{
    /**
     * Mencocokkan satu source adjustment terhadap daftar kandidat adjustment lintas unit pada schedule yang sama.
     *
     * @param  Collection<int, RkaAdjustment>  $candidates
     * @param  bool  $counterpartHeaderExists  Apakah unit lawan sudah memiliki RkaHeader pada schedule ini
     * @return array{
     *     reconciliation_status: string,
     *     matching_level: string,
     *     counterpart_adjustment: ?RkaAdjustment,
     *     matched_amount: ?float,
     *     matching_rule: string,
     *     match_details: array<string, mixed>
     * }
     */
    public function match(
        RkaAdjustment $source,
        Collection $candidates,
        bool $counterpartHeaderExists,
    ): array {
        $sourceHeader = $source->rkaHeader;
        $sourceDeptId = (int) $sourceHeader->department_id;
        $sourceScheduleId = (int) $sourceHeader->schedule_id;

        if (! $this->requiresCrossUnitReconciliation($source)) {
            return [
                'reconciliation_status' => RkaAdjustmentMatch::STATUS_UNMATCHED,
                'matching_level' => RkaAdjustmentMatch::LEVEL_NOT_REQUIRED,
                'counterpart_adjustment' => null,
                'matched_amount' => null,
                'matching_rule' => 'NOT_REQUIRED_FOR_ACTION_TYPE',
                'match_details' => [
                    'reason' => 'Tipe penyesuaian tidak memerlukan rekonsiliasi lintas unit.',
                    'action_type' => $source->action_type,
                    'section_type' => $source->section_type,
                ],
            ];
        }

        $eligibleCandidates = $candidates->filter(function (RkaAdjustment $candidate) use ($source, $sourceDeptId, $sourceScheduleId): bool {
            $candHeader = $candidate->rkaHeader;
            if (! $candHeader || (int) $candHeader->schedule_id !== $sourceScheduleId) {
                return false;
            }

            if ((int) $candHeader->department_id === $sourceDeptId) {
                return false;
            }

            if (! $this->isUnitPairCompatible($source, $candidate, $sourceDeptId, (int) $candHeader->department_id)) {
                return false;
            }

            if (! $this->isAccountCompatible($source, $candidate)) {
                return false;
            }

            return $this->isDescriptionCompatible((string) $source->description, (string) $candidate->description);
        })->values();

        if ($eligibleCandidates->isEmpty()) {
            if (! $counterpartHeaderExists) {
                return [
                    'reconciliation_status' => RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART,
                    'matching_level' => RkaAdjustmentMatch::LEVEL_MATCH_NONE,
                    'counterpart_adjustment' => null,
                    'matched_amount' => null,
                    'matching_rule' => 'COUNTERPART_NOT_IMPORTED',
                    'match_details' => [
                        'reason' => 'Unit lawan belum mengimpor/memiliki RKA pada jadwal ini.',
                        'source_ref_id' => $source->source_ref_id,
                        'schedule_id' => $sourceScheduleId,
                        'expected_target_department_id' => $this->expectedTargetDepartmentId($source),
                        'candidates_compared' => $candidates->count(),
                    ],
                ];
            }

            return [
                'reconciliation_status' => RkaAdjustmentMatch::STATUS_UNMATCHED,
                'matching_level' => RkaAdjustmentMatch::LEVEL_MATCH_NONE,
                'counterpart_adjustment' => null,
                'matched_amount' => null,
                'matching_rule' => 'NO_MATCHING_CANDIDATE',
                'match_details' => [
                    'reason' => 'Unit lawan sudah memiliki RKA pada jadwal ini, namun tidak ditemukan pasangan item dengan rekening dan uraian yang cocok.',
                    'source_ref_id' => $source->source_ref_id,
                    'schedule_id' => $sourceScheduleId,
                    'expected_target_department_id' => $this->expectedTargetDepartmentId($source),
                    'candidates_compared' => $candidates->count(),
                ],
            ];
        }

        if ($eligibleCandidates->count() > 1) {
            return [
                'reconciliation_status' => RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED,
                'matching_level' => RkaAdjustmentMatch::LEVEL_MATCH_AMBIGUOUS,
                'counterpart_adjustment' => null,
                'matched_amount' => null,
                'matching_rule' => 'MULTIPLE_CANDIDATES_FOUND',
                'match_details' => [
                    'reason' => 'Ditemukan lebih dari satu kandidat pasangan yang cocok sehingga memerlukan tinjauan Finance.',
                    'source_ref_id' => $source->source_ref_id,
                    'candidate_ids' => $eligibleCandidates->pluck('id')->all(),
                    'candidates_compared' => $candidates->count(),
                ],
            ];
        }

        /** @var RkaAdjustment $candidate */
        $candidate = $eligibleCandidates->first();
        $comparison = $this->compareMandatoryAttributes($source, $candidate);

        if (count($comparison['differing_attributes']) > 0) {
            return [
                'reconciliation_status' => RkaAdjustmentMatch::STATUS_CONFLICT,
                'matching_level' => RkaAdjustmentMatch::LEVEL_MATCH_CONFLICT,
                'counterpart_adjustment' => $candidate,
                'matched_amount' => null,
                'matching_rule' => 'ATTRIBUTE_CONFLICT',
                'match_details' => [
                    'reason' => 'Kandidat unik ditemukan, namun terdapat perbedaan pada atribut wajib: '.implode(', ', $comparison['differing_attributes']).'.',
                    'source_ref_id' => $source->source_ref_id,
                    'counterpart_ref_id' => $candidate->source_ref_id,
                    'matched_attributes' => $comparison['matched_attributes'],
                    'differing_attributes' => $comparison['differing_attributes'],
                    'differences' => $comparison['differences'],
                    'candidates_compared' => $candidates->count(),
                ],
            ];
        }

        $matchedAmount = $this->resolveMatchedAmount($source, $candidate);

        return [
            'reconciliation_status' => RkaAdjustmentMatch::STATUS_MATCHED,
            'matching_level' => RkaAdjustmentMatch::LEVEL_MATCH_UNIQUE,
            'counterpart_adjustment' => $candidate,
            'matched_amount' => $matchedAmount,
            'matching_rule' => 'AUTO_UNIQUE_CROSS_UNIT_MATCH',
            'match_details' => [
                'reason' => 'Pasangan item lintas unit cocok secara unik dan seluruh atribut wajib konsisten.',
                'source_ref_id' => $source->source_ref_id,
                'counterpart_ref_id' => $candidate->source_ref_id,
                'matched_attributes' => $comparison['matched_attributes'],
                'differing_attributes' => [],
                'candidates_compared' => $candidates->count(),
            ],
        ];
    }

    public function requiresCrossUnitReconciliation(RkaAdjustment $adjustment): bool
    {
        if ($adjustment->section_type === RkaAdjustment::SECTION_B) {
            return $adjustment->target_unit_id !== null;
        }

        return $adjustment->section_type === RkaAdjustment::SECTION_A
            && $adjustment->action_type === RkaAdjustment::ACTION_MOVED;
    }

    public function expectedTargetDepartmentId(RkaAdjustment $adjustment): ?int
    {
        if ($adjustment->section_type === RkaAdjustment::SECTION_B) {
            return $adjustment->target_unit_id !== null ? (int) $adjustment->target_unit_id : null;
        }

        return $adjustment->destination_unit_id !== null ? (int) $adjustment->destination_unit_id : null;
    }

    private function isUnitPairCompatible(
        RkaAdjustment $source,
        RkaAdjustment $candidate,
        int $sourceDeptId,
        int $candidateDeptId,
    ): bool {
        if ($source->section_type === RkaAdjustment::SECTION_A && $candidate->section_type === RkaAdjustment::SECTION_B) {
            if ((int) $candidate->target_unit_id !== $sourceDeptId) {
                return false;
            }

            if ($source->destination_unit_id !== null && (int) $source->destination_unit_id !== $candidateDeptId) {
                return false;
            }

            return true;
        }

        if ($source->section_type === RkaAdjustment::SECTION_B && $candidate->section_type === RkaAdjustment::SECTION_A) {
            if ((int) $source->target_unit_id !== $candidateDeptId) {
                return false;
            }

            if ($candidate->destination_unit_id !== null && (int) $candidate->destination_unit_id !== $sourceDeptId) {
                return false;
            }

            return $candidate->action_type === RkaAdjustment::ACTION_MOVED;
        }

        return false;
    }

    private function isAccountCompatible(RkaAdjustment $source, RkaAdjustment $candidate): bool
    {
        if ($source->account_id === null || $candidate->account_id === null) {
            return false;
        }

        return (int) $source->account_id === (int) $candidate->account_id;
    }

    private function isDescriptionCompatible(string $descA, string $descB): bool
    {
        $normA = $this->normalizeDescription($descA);
        $normB = $this->normalizeDescription($descB);

        if ($normA === '' || $normB === '') {
            return false;
        }

        if ($normA === $normB) {
            return true;
        }

        $coreA = $this->extractDescriptionCore($normA);
        $coreB = $this->extractDescriptionCore($normB);

        return $coreA !== '' && $coreB !== '' && ($coreA === $coreB || $normA === $coreB || $coreA === $normB);
    }

    private function normalizeDescription(string $text): string
    {
        $clean = mb_strtolower(trim($text));
        $clean = str_replace(['–', '—'], '-', $clean);
        $clean = (string) preg_replace('/\s+/', ' ', $clean);

        return trim($clean);
    }

    private function extractDescriptionCore(string $normalized): string
    {
        if (str_contains($normalized, ' - ')) {
            $parts = explode(' - ', $normalized, 2);

            return trim($parts[1]);
        }

        if (str_contains($normalized, ': ')) {
            $parts = explode(': ', $normalized, 2);

            return trim($parts[1]);
        }

        return $normalized;
    }

    /**
     * @return array{
     *     matched_attributes: array<int, string>,
     *     differing_attributes: array<int, string>,
     *     differences: array<string, array{source: mixed, counterpart: mixed}>
     * }
     */
    private function compareMandatoryAttributes(RkaAdjustment $source, RkaAdjustment $candidate): array
    {
        $matched = ['schedule_id', 'department_pair', 'account_id', 'description'];
        $differing = [];
        $differences = [];

        $sourceProposed = round((float) $source->proposed_amount, 2);
        $candProposed = round((float) $candidate->proposed_amount, 2);
        if (abs($sourceProposed - $candProposed) > 0.01) {
            $differing[] = 'proposed_amount';
            $differences['proposed_amount'] = ['source' => $sourceProposed, 'counterpart' => $candProposed];
        } else {
            $matched[] = 'proposed_amount';
        }

        $sourceVol = round((float) $source->proposed_volume, 2);
        $candVol = round((float) $candidate->proposed_volume, 2);
        if ($sourceVol > 0 && $candVol > 0 && abs($sourceVol - $candVol) > 0.01) {
            $differing[] = 'proposed_volume';
            $differences['proposed_volume'] = ['source' => $sourceVol, 'counterpart' => $candVol];
        } else {
            $matched[] = 'proposed_volume';
        }

        $sourceUnit = mb_strtolower(trim((string) $source->unit_symbol));
        $candUnit = mb_strtolower(trim((string) $candidate->unit_symbol));
        if ($sourceUnit !== '' && $candUnit !== '' && $sourceUnit !== '-' && $candUnit !== '-' && $sourceUnit !== $candUnit) {
            $differing[] = 'unit_symbol';
            $differences['unit_symbol'] = ['source' => $source->unit_symbol, 'counterpart' => $candidate->unit_symbol];
        } else {
            $matched[] = 'unit_symbol';
        }

        $sourcePrice = round((float) $source->unit_price, 2);
        $candPrice = round((float) $candidate->unit_price, 2);
        if ($sourcePrice > 0 && $candPrice > 0 && abs($sourcePrice - $candPrice) > 0.01) {
            $differing[] = 'unit_price';
            $differences['unit_price'] = ['source' => $sourcePrice, 'counterpart' => $candPrice];
        } else {
            $matched[] = 'unit_price';
        }

        return [
            'matched_attributes' => $matched,
            'differing_attributes' => $differing,
            'differences' => $differences,
        ];
    }

    private function resolveMatchedAmount(RkaAdjustment $source, RkaAdjustment $candidate): float
    {
        if ($source->section_type === RkaAdjustment::SECTION_B && (float) $source->approved_amount > 0) {
            return round((float) $source->approved_amount, 2);
        }

        if ($candidate->section_type === RkaAdjustment::SECTION_B && (float) $candidate->approved_amount > 0) {
            return round((float) $candidate->approved_amount, 2);
        }

        return round((float) $source->proposed_amount, 2);
    }
}
