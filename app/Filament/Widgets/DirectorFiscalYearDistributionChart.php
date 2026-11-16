<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class DirectorFiscalYearDistributionChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Distribution & Comparison by Fiscal Year';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $rows = app(DashboardMetricsService::class)->getFiscalYearComparison();

        return [
            'datasets' => [
                [
                    'label' => 'Total Pagu (Rp)',
                    'data' => $rows->pluck('pagu')->all(),
                    'backgroundColor' => '#6366f1',
                ],
                [
                    'label' => 'Total Disetujui (Rp)',
                    'data' => $rows->pluck('approved')->all(),
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $rows->pluck('fiscal_year')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
