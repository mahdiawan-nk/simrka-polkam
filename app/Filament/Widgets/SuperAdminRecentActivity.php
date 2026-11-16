<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class SuperAdminRecentActivity extends Widget
{
    use HasWidgetShield;

    protected string $view = 'filament.widgets.super-admin-recent-activity';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'activities' => app(DashboardMetricsService::class)->getRecentSystemActivities(12),
        ];
    }
}
