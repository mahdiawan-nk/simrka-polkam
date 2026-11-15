<?php

namespace App\Filament\Widgets;

use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
class DirectorFiscalYearDistributionChart extends ChartWidget
{
    use HasWidgetShield;
    protected ?string $heading = 'Distribution & Comparison by Fiscal Year';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $fiscalYears = DepartmentBudget::distinct()->pluck('fiscal_year')->sort()->values();

        $allocatedData = [];
        $approvedData = [];

        foreach ($fiscalYears as $fy) {
            $allocatedData[] = DepartmentBudget::where('fiscal_year', $fy)->sum('allocated_amount');
            $approvedData[] = FundRequest::where('fiscal_year', $fy)->where('status', 'approved')->sum('total_approved');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Pagu (Rp)',
                    'data' => $allocatedData,
                    'backgroundColor' => '#6366f1', // Indigo
                ],
                [
                    'label' => 'Total Realisasi (Rp)',
                    'data' => $approvedData,
                    'backgroundColor' => '#10b981', // Emerald
                ],
            ],
            'labels' => $fiscalYears->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
