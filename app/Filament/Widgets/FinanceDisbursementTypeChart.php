<?php

namespace App\Filament\Widgets;

use App\Models\FundRequest;
use App\Models\RkaSchedule;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class FinanceDisbursementTypeChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Porsi Pengajuan berdasarkan Jenis Pencairan';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $currentFiscalYear = RkaSchedule::currentFiscalYear();

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
                        '#17324D', // Deep Navy (Panjar)
                        '#245B85', // Academic Blue (Reimburse)
                        '#168C83', // Muted Teal (Langsung)
                        '#C69A45', // Soft Gold (Lainnya)
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
