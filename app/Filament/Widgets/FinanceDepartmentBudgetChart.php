<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\RkaSchedule;
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
        $currentFiscalYear = RkaSchedule::currentFiscalYear();
        $departments = Department::whereHas('budgets', function ($q) use ($currentFiscalYear) {
            $q->where('fiscal_year', $currentFiscalYear);
        })->get();

        $labels = [];
        $allocatedData = [];
        $approvedData = [];

        foreach ($departments as $dept) {
            $labels[] = $dept->code;

            $allocated = DepartmentBudget::where('department_id', $dept->id)
                ->where('fiscal_year', $currentFiscalYear)
                ->sum('allocated_amount');

            $approved = FundRequest::where('department_id', $dept->id)
                ->where('fiscal_year', $currentFiscalYear)
                ->where('status', 'approved')
                ->sum('total_approved');

            $allocatedData[] = $allocated;
            $approvedData[] = $approved;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Alokasi Pagu (Rp)',
                    'data' => $allocatedData,
                    'backgroundColor' => '#3b82f6', // Blue
                ],
                [
                    'label' => 'Realisasi Approved (Rp)',
                    'data' => $approvedData,
                    'backgroundColor' => '#10b981', // Emerald
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
