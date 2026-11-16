<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (Filament::getCurrentPanel() === null) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
        }

        $discoveredPermissions = collect()
            ->merge(
                collect(FilamentShield::getResources() ?? [])
                    ->flatMap(fn (array $resource): array => collect($resource['permissions'] ?? [])->pluck('key')->all())
            )
            ->merge(
                collect(FilamentShield::getPages() ?? [])
                    ->flatMap(fn (array $page): array => array_keys($page['permissions'] ?? []))
            )
            ->merge(
                collect(FilamentShield::getWidgets() ?? [])
                    ->flatMap(fn (array $widget): array => array_keys($widget['permissions'] ?? []))
            )
            ->merge([
                'ViewAllUnits:FundRequest',
                'ViewAllUnits:RkaHeader',
                'ViewAllUnits:DepartmentBudget',
            ])
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values();

        foreach ($discoveredPermissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // Berikan permission awal ke role hanya jika role belum memiliki permission non-widget sama sekali,
        // agar konfigurasi yang sudah disesuaikan administrator di Filament Shield tidak tertimpa saat seeder dijalankan ulang.
        $widgetPermissionNames = collect(FilamentShield::getWidgets() ?? [])
            ->flatMap(fn (array $widget): array => array_keys($widget['permissions'] ?? []))
            ->unique()
            ->all();

        $initialRolePermissions = [
            'super_admin' => $discoveredPermissions
                ->reject(fn (string $name): bool => $name === 'View:CleanDataRkaPage' || in_array($name, $widgetPermissionNames, true))
                ->values()
                ->all(),
            'direktur' => [
                'ViewAny:RkaHeader', 'View:RkaHeader',
                'ViewAny:FundRequest', 'View:FundRequest',
                'ViewAny:DepartmentBudget', 'View:DepartmentBudget',
                'ViewAny:Department', 'View:Department',
                'ViewAny:Account', 'View:Account',
                'ViewAllUnits:FundRequest', 'ViewAllUnits:RkaHeader', 'ViewAllUnits:DepartmentBudget',
            ],
            'wadir_keuangan' => [
                'ViewAny:RkaHeader', 'View:RkaHeader', 'Update:RkaHeader',
                'ViewAny:FundRequest', 'View:FundRequest', 'Update:FundRequest',
                'ViewAny:DepartmentBudget', 'View:DepartmentBudget',
                'ViewAny:Department', 'View:Department',
                'ViewAny:Account', 'View:Account',
                'View:ManageUnlockRequestsPage',
                'ViewAllUnits:FundRequest', 'ViewAllUnits:RkaHeader', 'ViewAllUnits:DepartmentBudget',
            ],
            'keuangan' => [
                'ViewAny:RkaHeader', 'View:RkaHeader', 'Update:RkaHeader',
                'ViewAny:RkaSchedule', 'View:RkaSchedule', 'Create:RkaSchedule', 'Update:RkaSchedule',
                'ViewAny:RkaSetting', 'View:RkaSetting', 'Create:RkaSetting', 'Update:RkaSetting',
                'ViewAny:RkaGenerateConfig', 'View:RkaGenerateConfig', 'Create:RkaGenerateConfig', 'Update:RkaGenerateConfig',
                'ViewAny:FundRequest', 'View:FundRequest', 'Update:FundRequest',
                'ViewAny:DepartmentBudget', 'View:DepartmentBudget', 'Create:DepartmentBudget', 'Update:DepartmentBudget',
                'ViewAny:Account', 'View:Account', 'Create:Account', 'Update:Account',
                'ViewAny:Department', 'View:Department',
                'View:ManageUnlockRequestsPage', 'View:ManageReconciliationsPage',
                'ViewAllUnits:FundRequest', 'ViewAllUnits:RkaHeader', 'ViewAllUnits:DepartmentBudget',
            ],
            'pemohon' => [
                'ViewAny:RkaHeader', 'View:RkaHeader', 'Create:RkaHeader', 'Update:RkaHeader',
                'ViewAny:FundRequest', 'View:FundRequest', 'Create:FundRequest', 'Update:FundRequest', 'Delete:FundRequest',
                'ViewAny:DepartmentBudget', 'View:DepartmentBudget',
                'ViewAny:Account', 'View:Account',
            ],
        ];

        foreach ($initialRolePermissions as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $existingNonWidgetCount = $role->permissions()
                ->where('guard_name', 'web')
                ->whereNotIn('name', $widgetPermissionNames)
                ->count();

            if ($existingNonWidgetCount > 0) {
                continue;
            }

            $validPermissions = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $permissions)
                ->pluck('name')
                ->all();

            $role->givePermissionTo($validPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
