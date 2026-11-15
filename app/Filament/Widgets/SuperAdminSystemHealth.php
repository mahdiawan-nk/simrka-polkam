<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use Filament\Widgets\Widget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class SuperAdminSystemHealth extends Widget
{
    use HasWidgetShield;
    protected string $view = 'filament.widgets.super-admin-system-health';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'statusCounts' => [
                'draft' => FundRequest::where('status', 'draft')->count(),
                'submitted' => FundRequest::where('status', 'submitted')->count(),
                'verified' => FundRequest::whereIn('status', ['verified_kabakku', 'verified_wadir_bakku'])->count(),
                'approved' => FundRequest::where('status', 'approved')->count(),
                'rejected' => FundRequest::where('status', 'rejected')->count(),
            ],
            'masterCounts' => [
                'departments' => Department::count(),
                'accounts' => Account::count(),
                'budgets' => DepartmentBudget::distinct('fiscal_year')->count('department_id'),
            ],
        ];
    }
}
