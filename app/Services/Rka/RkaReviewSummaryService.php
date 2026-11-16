<?php

namespace App\Services\Rka;

use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaItem;

/**
 * Service terpusat untuk menghitung Ringkasan Review RKA (Posisi 1 s.d. 8)
 * agar konsisten dengan formula worksheet RINGKASAN pada workbook Excel sumber.
 */
class RkaReviewSummaryService
{
    /**
     * Menghitung seluruh posisi Ringkasan Review RKA (1 s.d. 8).
     *
     * @return array{
     *     usulan_awal: float,
     *     dipindahkan: float,
     *     pos_cost: float,
     *     tambahan_pusat: float,
     *     adjustment_price: float,
     *     usulan_dibahas: float,
     *     disetujui_setelah_review: float,
     *     percentage: float
     * }
     */
    public function calculate(RkaHeader $record): array
    {
        $pos1 = $this->position1UsulanAwal($record);
        $pos2 = $this->position2Dipindahkan($record);
        $pos3 = $this->position3TidakDianggarkan($record);
        $pos4 = $this->position4TambahanReview($record);
        $pos6 = $this->position6UsulanDibahas($record);
        $pos5 = $this->position5PenyesuaianHargaDanVolume($pos1, $pos2, $pos3, $pos4, $pos6);
        $pos7 = $this->position7DisetujuiSetelahReview($record);
        $pos8 = $this->position8Persentase($pos6, $pos7);

        return [
            'usulan_awal' => $pos1,
            'dipindahkan' => $pos2,
            'pos_cost' => $pos3,
            'adjustment_price' => $pos5,
            'tambahan_pusat' => $pos4,
            'usulan_dibahas' => $pos6,
            'disetujui_setelah_review' => $pos7,
            'percentage' => $pos8,
        ];
    }

    /**
     * Posisi 1: Usulan awal unit (pagu_budget).
     */
    public function position1UsulanAwal(RkaHeader $record): float
    {
        return (float) $record->pagu_budget;
    }

    /**
     * Posisi 2: Dipindah ke pos pusat / unit lain (bertanda negatif sesuai worksheet RINGKASAN).
     */
    public function position2Dipindahkan(RkaHeader $record): float
    {
        $amount = (float) $record->adjustments()
            ->where('section_type', RkaAdjustment::SECTION_A)
            ->where('action_type', RkaAdjustment::ACTION_MOVED)
            ->sum('proposed_amount');

        return $amount > 0 ? -$amount : $amount;
    }

    /**
     * Posisi 3: Tidak dianggarkan / sudah dianggarkan di tempat lain (bertanda negatif sesuai worksheet RINGKASAN).
     */
    public function position3TidakDianggarkan(RkaHeader $record): float
    {
        $amount = (float) $record->adjustments()
            ->where('section_type', RkaAdjustment::SECTION_A)
            ->whereIn('action_type', [
                RkaAdjustment::ACTION_BUDGETED_ELSEWHERE,
                RkaAdjustment::ACTION_REMOVED,
            ])
            ->sum('proposed_amount');

        return $amount > 0 ? -$amount : $amount;
    }

    /**
     * Posisi 4: Tambahan dari pos/unit lain atau penambahan saat review (Section B).
     */
    public function position4TambahanReview(RkaHeader $record): float
    {
        return (float) $record->adjustments()
            ->where('section_type', RkaAdjustment::SECTION_B)
            ->sum('proposed_amount');
    }

    /**
     * Posisi 5: Penyesuaian harga satuan / volume usulan saat review.
     * Formula Excel: =C11 - SUM(C6:C9) => Posisi 6 - (Posisi 1 + Posisi 2 + Posisi 3 + Posisi 4).
     */
    public function position5PenyesuaianHargaDanVolume(
        float $pos1,
        float $pos2,
        float $pos3,
        float $pos4,
        float $pos6,
    ): float {
        return $pos6 - ($pos1 + $pos2 + $pos3 + $pos4);
    }

    /**
     * Posisi 6: Usulan yang dibahas dalam RKA unit (1 s.d. 5), mencakup APPROVED, TRIMMED, REJECTED pada BELANJA.
     */
    public function position6UsulanDibahas(RkaHeader $record): float
    {
        return (float) $record->items()
            ->whereIn('status', [
                RkaItem::STATUS_APPROVED,
                RkaItem::STATUS_ACCEPTED,
                RkaItem::STATUS_REDUCED,
                'TRIMMED',
                RkaItem::STATUS_REJECTED,
                'REJECTED_WITH_ADJUSTMENT',
            ])
            ->where(function ($query): void {
                $query->whereNull('source_ref_id')
                    ->orWhere('source_ref_id', 'not like', 'NATIVE:ADJ:A:%');
            })
            ->sum('proposed_total');
    }

    /**
     * Posisi 7: Total nilai yang benar-benar disetujui setelah review.
     */
    public function position7DisetujuiSetelahReview(RkaHeader $record): float
    {
        return (float) $record->items()
            ->whereIn('status', [
                RkaItem::STATUS_APPROVED,
                RkaItem::STATUS_ACCEPTED,
                RkaItem::STATUS_REDUCED,
                'TRIMMED',
                'REJECTED_WITH_ADJUSTMENT',
            ])
            ->sum('approved_total');
    }

    /**
     * Posisi 8: Persentase disetujui terhadap usulan yang dibahas: (Posisi 7 / Posisi 6) * 100.
     */
    public function position8Persentase(float $pos6, float $pos7): float
    {
        return $pos6 > 0 ? round(($pos7 / $pos6) * 100, 1) : 0.0;
    }
}
