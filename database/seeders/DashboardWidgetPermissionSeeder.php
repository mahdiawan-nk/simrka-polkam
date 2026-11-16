<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DashboardWidgetPermissionSeeder extends Seeder
{
    /**
     * Matriks assignment permission widget resmi per role aktual SIMRKA.
     *
     * @return array<string, array<int, string>>
     */
    public static function matrix(): array
    {
        return [
            'super_admin' => [
                'View:DashboardContextBanner',
                'View:SuperAdminStatsOverview',
                'View:SuperAdminSystemHealth',
                'View:SuperAdminRecentActivity',
                'View:RkaOverviewWidget',
            ],
            'direktur' => [
                'View:DashboardContextBanner',
                'View:DirectorStatsOverview',
                'View:DirectorExecutiveApprovalQueue',
                'View:DirectorDepartmentPerformance',
                'View:FinanceDepartmentBudgetChart',
                'View:WadirMonthlyDisbursementTrendChart',
                'View:DirectorFiscalYearDistributionChart',
                'View:DirectorFinancialBreakdownMatrix',
                'View:RkaOverviewWidget',
            ],
            'wadir_keuangan' => [
                'View:DashboardContextBanner',
                'View:WadirStatsOverview',
                'View:WadirPendingApprovalTable',
                'View:WadirDepartmentUtilizationTable',
                'View:WadirMonthlyDisbursementTrendChart',
                'View:RkaOverviewWidget',
            ],
            'keuangan' => [
                'View:DashboardContextBanner',
                'View:FinanceStatsOverview',
                'View:FinanceVerificationQueue',
                'View:FinanceDepartmentBudgetChart',
                'View:FinanceDisbursementTypeChart',
                'View:WadirDepartmentUtilizationTable',
                'View:RkaOverviewWidget',
            ],
            'staf_keuangan' => [
                'View:DashboardContextBanner',
                'View:FinanceStatsOverview',
                'View:FinanceMonitoringTable',
                'View:FinanceDepartmentBudgetChart',
                'View:FinanceDisbursementTypeChart',
                'View:WadirDepartmentUtilizationTable',
                'View:DirectorFinancialBreakdownMatrix',
                'View:WadirMonthlyDisbursementTrendChart',
                'View:RkaOverviewWidget',
            ],
            'pemohon' => [
                'View:DashboardContextBanner',
                'View:DepartmentStatsOverview',
                'View:DepartmentTrackingTable',
                'View:DepartmentTopAccounts',
                'View:RkaOverviewWidget',
            ],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allWidgetPermissions = collect(FilamentShield::getWidgets() ?? [])
            ->flatMap(fn (array $widget): array => array_keys($widget['permissions'] ?? []))
            ->unique()
            ->values()
            ->all();

        foreach ($allWidgetPermissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        foreach (self::matrix() as $roleName => $desiredWidgetPermissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                continue;
            }

            // Pertahankan seluruh permission non-widget (Resource, Page, Custom)
            $preservedNonWidgetPermissions = $role->permissions()
                ->where('guard_name', 'web')
                ->pluck('name')
                ->reject(fn (string $perm): bool => in_array($perm, $allWidgetPermissions, true))
                ->all();

            $validDesiredWidgets = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $desiredWidgetPermissions)
                ->pluck('name')
                ->all();

            $role->syncPermissions(array_values(array_unique(array_merge(
                $preservedNonWidgetPermissions,
                $validDesiredWidgets
            ))));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
