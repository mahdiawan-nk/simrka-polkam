<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use App\Models\RkaSchedule;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class FinanceStatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $currentFiscalYear = RkaSchedule::currentFiscalYear();

        // 1. Antrean Verifikasi (Status: submitted)
        $pendingCount = FundRequest::where('status', 'submitted')->count();

        // 2. Pengajuan Diproses Bulan Ini (Total Nominal Submitted & Processed)
        $processedThisMonthAmount = FundRequest::whereMonth('updated_at', Carbon::now()->month)
            ->whereYear('updated_at', Carbon::now()->year)
            ->whereIn('status', ['verified_kabakku', 'verified_wadir_bakku', 'approved'])
            ->sum('total_approved');

        // 3. Total Penyesuaian/Koreksi (Total Requested - Total Approved pada pengajuan yang telah terverifikasi)
        $correctionTotals = FundRequest::query()
            ->where('fiscal_year', $currentFiscalYear)
            ->whereIn('status', ['verified_kabakku', 'verified_wadir_bakku', 'approved'])
            ->selectRaw('COALESCE(SUM(total_requested), 0) as total_requested, COALESCE(SUM(total_approved), 0) as total_approved')
            ->first();

        $totalCorrection = (float) ($correctionTotals->total_requested ?? 0) - (float) ($correctionTotals->total_approved ?? 0);

        return [
            Stat::make('Antrean Verifikasi', $pendingCount.' Dokumen')
                ->description('Menunggu pemeriksaan verifikator')
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->color($pendingCount > 0 ? 'warning' : 'gray'),

            Stat::make('Pengajuan Diproses Bulan Ini', 'Rp '.number_format($processedThisMonthAmount, 2, ',', '.'))
                ->description('Total nominal disetujui bulan '.Carbon::now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Total Penyesuaian / Koreksi', 'Rp '.number_format($totalCorrection, 2, ',', '.'))
                ->description('Selisih (Dimohonkan - Disetujui)')
                ->descriptionIcon('heroicon-m-adjustments-vertical')
                ->color($totalCorrection > 0 ? 'info' : 'gray'),
        ];
    }
}
