<?php

namespace App\Filament\Widgets;

use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class DirectorStatsOverview extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $currentFiscalYear = '2026/2027';

        // 1. Total Anggaran Institusi (Pagu Seluruh Kampus)
        $totalBudget = DepartmentBudget::where('fiscal_year', $currentFiscalYear)
            ->sum('allocated_amount');

        // 2. Total Realisasi Eksekutif (Approved Final)
        $totalApproved = FundRequest::where('fiscal_year', $currentFiscalYear)
            ->where('status', 'approved')
            ->sum('total_approved');

        // 3. Persentase Penyerapan Institusi (%)
        $absorptionRate = $totalBudget > 0
            ? round(($totalApproved / $totalBudget) * 100, 1)
            : 0;

        // 4. Dokumen Menunggu Approval Direktur
        // Pengajuan berstatus verified_wadir_bakku
        $pendingDirectorApproval = FundRequest::where('status', 'verified_wadir_bakku')->count();

        return [
            Stat::make('Total Anggaran Institusi', 'Rp ' . number_format($totalBudget, 2, ',', '.'))
                ->description("Pagu Kampus TA {$currentFiscalYear}")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('info'),

            Stat::make('Total Realisasi Eksekutif', 'Rp ' . number_format($totalApproved, 2, ',', '.'))
                ->description('Total Nominal Dana Terdistribusi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Penyerapan Institusi', $absorptionRate . '%')
                ->description('Indikator Kinerja Anggaran Makro')
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color($absorptionRate > 85 ? 'success' : ($absorptionRate > 60 ? 'warning' : 'danger')),

            Stat::make('Menunggu Approval Direktur', $pendingDirectorApproval . ' Dokumen')
                ->description('Pengajuan Tahap Final')
                ->descriptionIcon('heroicon-m-document-check')
                ->color($pendingDirectorApproval > 0 ? 'warning' : 'gray'),
        ];
    }
}
