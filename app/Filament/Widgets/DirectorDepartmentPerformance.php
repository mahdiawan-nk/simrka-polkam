<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class DirectorDepartmentPerformance extends Widget
{
    use HasWidgetShield;

    protected string $view = 'filament.widgets.director-department-performance';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $service = app(DashboardMetricsService::class);
        $context = $service->getActiveContext();
        $performance = $service->getDepartmentComparison($context['fiscal_year']);

        return [
            'top5' => $performance->sortByDesc('percentage')->take(5)->values(),
            'bottom5' => $performance->sortBy('percentage')->take(5)->values(),
        ];
    }
}
