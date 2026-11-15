<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use App\Models\FundRequestApproval;
use App\Models\RkaSchedule;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WadirStatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $currentFiscalYear = RkaSchedule::currentFiscalYear();

        // 1. Antrean Persetujuan Wadir (Status: verified_kabakku)
        $pendingCount = FundRequest::where('status', 'verified_kabakku')->count();

        // 2. Total Persetujuan Bulan Ini di Tahap Wadir
        $approvedThisMonth = FundRequestApproval::where('stage', 'wadir_bakku')
            ->where('action', 'approved')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('approved_amount');

        // 3. Rata-rata Waktu Verifikasi (Turnaround Time dalam Jam/Hari)
        // Dihitung dari waktu submitted (created_at/updated_at saat submitted) sampai approval wadir
        $avgHours = FundRequestApproval::where('stage', 'wadir_bakku')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, fund_requests.created_at, fund_request_approvals.created_at)) as avg_hours'))
            ->join('fund_requests', 'fund_requests.id', '=', 'fund_request_approvals.fund_request_id')
            ->value('avg_hours');

        $turnaroundText = $avgHours
            ? ($avgHours >= 24 ? round($avgHours / 24, 1).' Hari' : round($avgHours, 1).' Jam')
            : '0 Jam';

        return [
            Stat::make('Antrean Persetujuan Wadir', $pendingCount.' Dokumen')
                ->description('Menunggu verifikasi Wadir BAKKU')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'gray'),

            Stat::make('Total Persetujuan Bulan Ini', 'Rp '.number_format($approvedThisMonth, 2, ',', '.'))
                ->description('Nominal disetujui Wadir bulan '.Carbon::now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Rata-rata Turnaround Time', $turnaroundText)
                ->description('Kecepatan proses (Submitted → Approval Wadir)')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info'),
        ];
    }
}
