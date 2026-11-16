<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class FinanceDepartmentBudgetChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Komparasi Alokasi Pagu vs Realisasi per Departemen';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $service = app(DashboardMetricsService::class);
        $context = $service->getActiveContext();
        $rows = $service->getDepartmentComparison($context['fiscal_year']);

        return [
            'datasets' => [
                [
                    'label' => 'Alokasi Pagu (Rp)',
                    'data' => $rows->pluck('pagu')->all(),
                    'backgroundColor' => '#3b82f6',
                ],
                [
                    'label' => 'Disetujui Final (Rp)',
                    'data' => $rows->pluck('approved')->all(),
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $rows->pluck('code')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
