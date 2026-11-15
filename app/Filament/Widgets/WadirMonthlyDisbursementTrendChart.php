<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class WadirMonthlyDisbursementTrendChart extends ChartWidget
{
    use HasWidgetShield;
    protected ?string $heading = 'Monthly Disbursement Trend (Tren Pencairan Dana Bulanan)';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $currentFiscalYear = '2026/2027';

        // Mengambil total realisasi approved per bulan untuk tahun berjalan
        $monthlyData = FundRequest::query()
            ->select(
                DB::raw('MONTH(updated_at) as month_num'),
                DB::raw('SUM(total_approved) as total_amount')
            )
            ->where('fiscal_year', $currentFiscalYear)
            ->where('status', 'approved')
            ->groupBy('month_num')
            ->pluck('total_amount', 'month_num')
            ->toArray();

        $labels = [];
        $data = [];

        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create()->month($m)->translatedFormat('M');
            $data[] = $monthlyData[$m] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Realisasi Pencairan (Rp)',
                    'data' => $data,
                    'borderColor' => '#059669', // Emerald 600
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
