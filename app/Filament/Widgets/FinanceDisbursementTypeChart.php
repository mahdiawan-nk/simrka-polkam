<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class FinanceDisbursementTypeChart extends ChartWidget
{
    use HasWidgetShield;
    protected ?string $heading = 'Porsi Pengajuan berdasarkan Jenis Pencairan';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $currentFiscalYear = '2026/2027';

        $disbursements = FundRequest::query()
            ->select('disbursement_type', DB::raw('COUNT(*) as total_count'), DB::raw('SUM(total_requested) as total_amount'))
            ->where('fiscal_year', $currentFiscalYear)
            ->groupBy('disbursement_type')
            ->get();

        $labels = [];
        $data = [];

        foreach ($disbursements as $item) {
            $labels[] = ucfirst($item->disbursement_type);
            $data[] = $item->total_count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Pengajuan',
                    'data' => $data,
                    'backgroundColor' => [
                        '#6366f1', // Indigo (Panjar)
                        '#f59e0b', // Amber (Reimburse)
                        '#10b981', // Emerald (Langsung)
                        '#ec4899', // Pink (Lainnya)
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
