<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class SuperAdminSystemHealth extends Widget
{
    use HasWidgetShield;

    protected string $view = 'filament.widgets.super-admin-system-health';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $countsByStatus = FundRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'statusCounts' => [
                'draft' => (int) ($countsByStatus['draft'] ?? 0),
                'submitted' => (int) ($countsByStatus['submitted'] ?? 0),
                'verified' => (int) ($countsByStatus['verified_kabakku'] ?? 0) + (int) ($countsByStatus['verified_wadir_bakku'] ?? 0),
                'approved' => (int) ($countsByStatus['approved'] ?? 0),
                'rejected' => (int) ($countsByStatus['rejected'] ?? 0),
            ],
            'masterCounts' => [
                'departments' => Department::count(),
                'accounts' => Account::count(),
                'budgets' => DepartmentBudget::distinct('fiscal_year')->count('department_id'),
            ],
        ];
    }
}
