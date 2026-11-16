<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DashboardRbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allNeededPermissions = [
            'View:DashboardContextBanner',
            'View:RkaOverviewWidget',
            'View:FinanceMonitoringTable',
            'ViewAllUnits:FundRequest',
            'ViewAllUnits:RkaHeader',
            'ViewAllUnits:DepartmentBudget',
        ];

        foreach ($allNeededPermissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // 1. Role baru: staf_keuangan (Read-Only Finance Monitoring)
        $stafKeuangan = Role::firstOrCreate([
            'name' => 'staf_keuangan',
            'guard_name' => 'web',
        ]);

        $stafKeuanganPermissions = [
            'ViewAny:FundRequest',
            'View:FundRequest',
            'ViewAllUnits:FundRequest',
            'ViewAny:RkaHeader',
            'View:RkaHeader',
            'ViewAllUnits:RkaHeader',
            'ViewAny:DepartmentBudget',
            'View:DepartmentBudget',
            'ViewAllUnits:DepartmentBudget',
            'ViewAny:Account',
            'View:Account',
            'ViewAny:Department',
            'View:Department',
            'View:DashboardContextBanner',
            'View:FinanceStatsOverview',
            'View:RkaOverviewWidget',
            'View:FinanceMonitoringTable',
            'View:FinanceDepartmentBudgetChart',
            'View:FinanceDisbursementTypeChart',
            'View:WadirDepartmentUtilizationTable',
        ];

        $stafKeuangan->syncPermissions(
            Permission::whereIn('name', $stafKeuanganPermissions)->pluck('name')->all()
        );

        // 2. Berikan View:RkaOverviewWidget ke seluruh role aktif (serta View:DashboardContextBanner ke role non-super_admin)
        foreach (['keuangan', 'wadir_keuangan', 'direktur', 'pemohon'] as $roleName) {
            if ($role = Role::where('name', $roleName)->first()) {
                $role->givePermissionTo('View:DashboardContextBanner');
                $role->givePermissionTo('View:RkaOverviewWidget');
            }
        }

        // 3. Hapus duplikasi SuperAdminStatsOverview dari keuangan & wadir_keuangan
        foreach (['keuangan', 'wadir_keuangan'] as $roleName) {
            if ($role = Role::where('name', $roleName)->first()) {
                if (Permission::where('name', 'View:SuperAdminStatsOverview')->exists()) {
                    $role->revokePermissionTo('View:SuperAdminStatsOverview');
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
