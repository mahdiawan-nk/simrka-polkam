<?php

use App\Filament\Widgets\DepartmentStatsOverview;
use App\Filament\Widgets\DepartmentTrackingTable;
use App\Filament\Widgets\DirectorExecutiveApprovalQueue;
use App\Filament\Widgets\DirectorStatsOverview;
use App\Filament\Widgets\FinanceMonitoringTable;
use App\Filament\Widgets\FinanceStatsOverview;
use App\Filament\Widgets\FinanceVerificationQueue;
use App\Filament\Widgets\SuperAdminStatsOverview;
use App\Filament\Widgets\WadirPendingApprovalTable;
use App\Models\Department;
use App\Models\FundRequest;
use App\Models\User;
use App\Services\Context\Contracts\ContextManagerInterface;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Database\Seeders\DashboardRbacSeeder;
use Database\Seeders\DashboardWidgetPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

function getWidgetPermissionsForRole(Role $role): array
{
    $allWidgetPerms = collect(FilamentShield::getWidgets() ?? [])
        ->flatMap(fn (array $w): array => array_keys($w['permissions'] ?? []))
        ->all();

    return $role->fresh()->permissions
        ->pluck('name')
        ->filter(fn (string $name): bool => in_array($name, $allWidgetPerms, true))
        ->sort()
        ->values()
        ->all();
}

it('is idempotent when run multiple times and preserves resource, page, unknown role, and user-role assignments', function () {
    app(DashboardRbacSeeder::class)->run();

    $unknownRole = Role::firstOrCreate(['name' => 'custom_auditor_role', 'guard_name' => 'web']);
    $customPerm = Permission::firstOrCreate(['name' => 'View:BackupManagement', 'guard_name' => 'web']);
    $unknownRole->syncPermissions([$customPerm->name]);

    $pemohonRole = Role::where('name', 'pemohon')->firstOrFail();
    $beforeNonWidgetPemohon = $pemohonRole->permissions
        ->pluck('name')
        ->reject(fn (string $p): bool => str_starts_with($p, 'View:') && str_contains($p, 'Widget') || str_contains($p, 'StatsOverview') || str_contains($p, 'Table') || str_contains($p, 'Chart') || str_contains($p, 'Banner') || str_contains($p, 'Matrix') || str_contains($p, 'Health') || str_contains($p, 'Activity') || str_contains($p, 'Accounts') || str_contains($p, 'Performance') || str_contains($p, 'Queue'))
        ->sort()
        ->values()
        ->all();

    $sampleUser = User::factory()->create();
    $sampleUser->assignRole($pemohonRole);

    // Run twice
    app(DashboardWidgetPermissionSeeder::class)->run();
    app(DashboardWidgetPermissionSeeder::class)->run();

    $afterNonWidgetPemohon = $pemohonRole->fresh()->permissions
        ->pluck('name')
        ->filter(fn (string $p): bool => in_array($p, $beforeNonWidgetPemohon, true))
        ->sort()
        ->values()
        ->all();

    expect($afterNonWidgetPemohon)->toBe($beforeNonWidgetPemohon);
    expect($unknownRole->fresh()->permissions->pluck('name')->all())->toBe([$customPerm->name]);
    expect($sampleUser->fresh()->hasRole('pemohon'))->toBeTrue();
});

it('assigns exact matrix widget permissions for every actual role', function () {
    app(DashboardRbacSeeder::class)->run();
    app(DashboardWidgetPermissionSeeder::class)->run();

    foreach (DashboardWidgetPermissionSeeder::matrix() as $roleName => $expectedWidgets) {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $actualWidgets = getWidgetPermissionsForRole($role);
        $sortedExpected = collect($expectedWidgets)->sort()->values()->all();

        expect($actualWidgets)->toBe($sortedExpected);
    }
});

it('enforces super_admin, direktur, wadir, staf_keuangan, and pemohon widget visibility and scope', function () {
    app(DashboardRbacSeeder::class)->run();
    app(DashboardWidgetPermissionSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Super Admin sees only its 5 assigned widgets, not Director/Finance/Department widgets
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    expect(SuperAdminStatsOverview::canView())->toBeTrue()
        ->and(DirectorStatsOverview::canView())->toBeFalse()
        ->and(FinanceStatsOverview::canView())->toBeFalse()
        ->and(DepartmentStatsOverview::canView())->toBeFalse();

    // Wadir does NOT see DirectorExecutiveApprovalQueue
    $wadir = User::factory()->create();
    $wadir->assignRole('wadir_keuangan');
    $this->actingAs($wadir);

    expect(WadirPendingApprovalTable::canView())->toBeTrue()
        ->and(DirectorExecutiveApprovalQueue::canView())->toBeFalse();

    // Staf Keuangan sees FinanceMonitoringTable (read-only) and NOT FinanceVerificationQueue
    $staf = User::factory()->create();
    $staf->assignRole('staf_keuangan');
    $this->actingAs($staf);

    expect(FinanceMonitoringTable::canView())->toBeTrue()
        ->and(FinanceVerificationQueue::canView())->toBeFalse()
        ->and($staf->can('create', FundRequest::class))->toBeFalse();

    // Pemohon sees Department widgets scoped to active department and NO global widgets
    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $pemohon = User::factory()->create();
    $pemohon->assignRole('pemohon');
    $ictDept->users()->attach($pemohon->id, ['is_primary' => true]);

    $this->actingAs($pemohon);
    app(ContextManagerInterface::class)->setActiveDepartment($ictDept->id);

    expect(DepartmentStatsOverview::canView())->toBeTrue()
        ->and(DepartmentTrackingTable::canView())->toBeTrue()
        ->and(SuperAdminStatsOverview::canView())->toBeFalse()
        ->and(FinanceStatsOverview::canView())->toBeFalse();
});
