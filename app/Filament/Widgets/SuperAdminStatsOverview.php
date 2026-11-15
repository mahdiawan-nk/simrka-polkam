<?php

namespace App\Filament\Widgets;

use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\RkaSchedule;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SuperAdminStatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $currentFiscalYear = RkaSchedule::currentFiscalYear();

        // 1. Total Pagu Anggaran Kampus
        $totalBudget = DepartmentBudget::where('fiscal_year', $currentFiscalYear)
            ->sum('allocated_amount');

        // 2. Total Realisasi Dana (Status Approved)
        $totalApproved = FundRequest::where('fiscal_year', $currentFiscalYear)
            ->where('status', 'approved')
            ->sum('total_approved');

        // 3. Sisa Anggaran Keseluruhan
        $remainingBudget = $totalBudget - $totalApproved;

        // 4. Total Pengajuan Dana Masuk
        $totalRequestsAllTime = FundRequest::count();
        $totalRequestsThisMonth = FundRequest::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        return [
            Stat::make('Total Pagu Anggaran Kampus', 'Rp '.number_format($totalBudget, 2, ',', '.'))
                ->description("Tahun Anggaran {$currentFiscalYear}")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Total Realisasi Dana', 'Rp '.number_format($totalApproved, 2, ',', '.'))
                ->description('Pengajuan Berstatus Approved')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Sisa Anggaran Kampus', 'Rp '.number_format($remainingBudget, 2, ',', '.'))
                ->description('Sisa Pagu yang Dapat Digunakan')
                ->descriptionIcon('heroicon-m-scale')
                ->color($remainingBudget < 0 ? 'danger' : 'warning'),

            Stat::make('Total Pengajuan Dana', number_format($totalRequestsAllTime))
                ->description("{$totalRequestsThisMonth} pengajuan baru bulan ini")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),
        ];
    }
}
