<?php

namespace App\Filament\Widgets;

use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\RkaSchedule;
use App\Services\Context\Contracts\ContextManagerInterface;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class DepartmentStatsOverview extends Widget
{
    use HasWidgetShield;

    protected string $view = 'filament.widgets.department-stats-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        /** @var ContextManagerInterface $contextManager */
        $contextManager = app(ContextManagerInterface::class);
        $activeDept = $contextManager->getActiveDepartment();

        if (! $activeDept) {
            return [
                'hasDepartment' => false,
            ];
        }

        $currentFiscalYear = RkaSchedule::currentFiscalYear();

        // 1. Pagu Anggaran Unit
        $allocatedAmount = DepartmentBudget::where('department_id', $activeDept->id)
            ->where('fiscal_year', $currentFiscalYear)
            ->sum('allocated_amount');

        // 2. Dana Terpakai (Approved)
        $approvedAmount = FundRequest::where('department_id', $activeDept->id)
            ->where('fiscal_year', $currentFiscalYear)
            ->where('status', 'approved')
            ->sum('total_approved');

        // 3. Dana Sedang Diproses (Pending)
        $pendingAmount = FundRequest::where('department_id', $activeDept->id)
            ->where('fiscal_year', $currentFiscalYear)
            ->whereIn('status', ['submitted', 'verified_kabakku', 'verified_wadir_bakku'])
            ->sum('total_requested');

        // 4. Sisa Pagu Terencana: Pagu - (Terpakai + Diproses)
        $plannedRemaining = $allocatedAmount - ($approvedAmount + $pendingAmount);

        // Burn-rate Percentage
        $burnRate = $allocatedAmount > 0
            ? round(($approvedAmount / $allocatedAmount) * 100, 1)
            : 0;

        return [
            'hasDepartment' => true,
            'departmentName' => $activeDept->name,
            'fiscalYear' => $currentFiscalYear,
            'allocatedAmount' => $allocatedAmount,
            'approvedAmount' => $approvedAmount,
            'pendingAmount' => $pendingAmount,
            'plannedRemaining' => $plannedRemaining,
            'burnRate' => $burnRate,
        ];
    }
}
