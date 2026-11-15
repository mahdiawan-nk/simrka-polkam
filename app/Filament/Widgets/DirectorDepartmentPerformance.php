<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\RkaSchedule;
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
        $currentFiscalYear = RkaSchedule::currentFiscalYear();

        $departments = Department::whereHas('budgets', fn ($q) => $q->where('fiscal_year', $currentFiscalYear))->get();

        $performance = $departments->map(function ($dept) use ($currentFiscalYear) {
            $pagu = DepartmentBudget::where('department_id', $dept->id)
                ->where('fiscal_year', $currentFiscalYear)
                ->sum('allocated_amount');

            $approved = FundRequest::where('department_id', $dept->id)
                ->where('fiscal_year', $currentFiscalYear)
                ->where('status', 'approved')
                ->sum('total_approved');

            $percentage = $pagu > 0 ? round(($approved / $pagu) * 100, 1) : 0;

            return [
                'name' => $dept->name,
                'code' => $dept->code,
                'pagu' => $pagu,
                'approved' => $approved,
                'percentage' => $percentage,
            ];
        });

        return [
            'top5' => $performance->sortByDesc('percentage')->take(5)->values(),
            'bottom5' => $performance->sortBy('percentage')->take(5)->values(),
        ];
    }
}
