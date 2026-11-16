<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class RkaOverviewWidget extends Widget
{
    use HasWidgetShield;

    protected string $view = 'filament.widgets.rka-overview-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $service = app(DashboardMetricsService::class);
        $context = $service->getActiveContext();
        $user = $context['user'];

        $isGlobal = $service->canViewAllUnits($user, 'ViewAllUnits:RkaHeader');
        $departmentId = $isGlobal ? null : $context['active_department']?->id;

        if (! $isGlobal && $departmentId === null) {
            return [
                'hasData' => false,
                'scopeLabel' => 'Unit belum dipilih',
                'fiscalYear' => $context['fiscal_year'],
                'summary' => [],
            ];
        }

        $summary = $service->getRkaSummary($context['active_schedule'], $departmentId);

        return [
            'hasData' => true,
            'scopeLabel' => $isGlobal ? 'Seluruh Unit Institusi' : ($context['active_department']?->name ?? 'Unit Aktif'),
            'fiscalYear' => $context['fiscal_year'],
            'summary' => $summary,
        ];
    }
}
