<?php

use App\Filament\Pages\Database\BackupManagement;
use App\Filament\Widgets\DashboardContextBanner;
use App\Filament\Widgets\DepartmentStatsOverview;
use App\Filament\Widgets\DirectorStatsOverview;
use App\Filament\Widgets\FinanceStatsOverview;
use App\Filament\Widgets\RkaOverviewWidget;
use App\Filament\Widgets\SuperAdminRecentActivity;
use App\Filament\Widgets\SuperAdminStatsOverview;
use App\Filament\Widgets\SuperAdminSystemHealth;
use App\Models\FundRequest;
use App\Models\RkaHeader;
use App\Models\User;
use Database\Seeders\DashboardRbacSeeder;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

function allDashboardWidgetClasses(): array
{
    return [
        DashboardContextBanner::class,
        DepartmentStatsOverview::class,
        DirectorStatsOverview::class,
        FinanceStatsOverview::class,
        RkaOverviewWidget::class,
        SuperAdminRecentActivity::class,
        SuperAdminStatsOverview::class,
        SuperAdminSystemHealth::class,
    ];
}

function syncWidgetPermissionsForRole(Role $role, array $widgetPermissionNames): void
{
    $allWidgetPermNames = collect(allDashboardWidgetClasses())
        ->map(fn (string $class): string => 'View:'.class_basename($class))
        ->all();

    $nonWidgetPerms = $role->permissions
        ->pluck('name')
        ->reject(fn (string $name): bool => in_array($name, $allWidgetPermNames, true))
        ->all();

    $role->syncPermissions(array_merge($nonWidgetPerms, $widgetPermissionNames));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('Test 1: denies all widgets for super_admin when no widget permissions are granted', function () {
    app(DashboardRbacSeeder::class)->run();

    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
    syncWidgetPermissionsForRole($superAdminRole, []);

    $user = User::factory()->create();
    $user->assignRole($superAdminRole);

    $this->actingAs($user);

    foreach (allDashboardWidgetClasses() as $widgetClass) {
        expect($widgetClass::canView())->toBeFalse();
    }

    $visibleWidgets = collect(app(Dashboard::class)->getVisibleWidgets());
    foreach (allDashboardWidgetClasses() as $widgetClass) {
        expect($visibleWidgets)->not->toContain($widgetClass);
    }
});

it('Test 2: allows only explicitly granted widgets for super_admin', function () {
    app(DashboardRbacSeeder::class)->run();

    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
    syncWidgetPermissionsForRole($superAdminRole, [
        'View:DashboardContextBanner',
        'View:SuperAdminStatsOverview',
    ]);

    $user = User::factory()->create();
    $user->assignRole($superAdminRole);

    $this->actingAs($user);

    expect(DashboardContextBanner::canView())->toBeTrue()
        ->and(SuperAdminStatsOverview::canView())->toBeTrue()
        ->and(SuperAdminSystemHealth::canView())->toBeFalse()
        ->and(SuperAdminRecentActivity::canView())->toBeFalse()
        ->and(RkaOverviewWidget::canView())->toBeFalse()
        ->and(FinanceStatsOverview::canView())->toBeFalse();
});

it('Test 3 & 4: enforces widget permissions for regular roles (deny without permission, allow with permission)', function () {
    app(DashboardRbacSeeder::class)->run();

    $customRole = Role::firstOrCreate(['name' => 'custom_widget_tester', 'guard_name' => 'web']);
    syncWidgetPermissionsForRole($customRole, []);

    $user = User::factory()->create();
    $user->assignRole($customRole);

    $this->actingAs($user);
    expect(RkaOverviewWidget::canView())->toBeFalse()
        ->and(DashboardContextBanner::canView())->toBeFalse();

    $customRole->givePermissionTo('View:RkaOverviewWidget');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect(RkaOverviewWidget::canView())->toBeTrue()
        ->and(DashboardContextBanner::canView())->toBeFalse();
});

it('Test 5: enforces partial widget permissions accurately', function () {
    app(DashboardRbacSeeder::class)->run();

    $partialRole = Role::firstOrCreate(['name' => 'partial_widget_tester', 'guard_name' => 'web']);
    $allowed = [
        'View:DashboardContextBanner',
        'View:FinanceStatsOverview',
        'View:RkaOverviewWidget',
    ];
    syncWidgetPermissionsForRole($partialRole, $allowed);

    $user = User::factory()->create();
    $user->assignRole($partialRole);
    $this->actingAs($user);

    $visibleCount = collect(allDashboardWidgetClasses())
        ->filter(fn (string $widgetClass): bool => $widgetClass::canView())
        ->count();

    expect($visibleCount)->toBe(3)
        ->and(DashboardContextBanner::canView())->toBeTrue()
        ->and(FinanceStatsOverview::canView())->toBeTrue()
        ->and(RkaOverviewWidget::canView())->toBeTrue()
        ->and(DirectorStatsOverview::canView())->toBeFalse();
});

it('Test 6: immediately denies widget when permission is revoked and cache is refreshed', function () {
    app(DashboardRbacSeeder::class)->run();

    $role = Role::firstOrCreate(['name' => 'revoke_widget_tester', 'guard_name' => 'web']);
    syncWidgetPermissionsForRole($role, ['View:SuperAdminStatsOverview']);

    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    expect(SuperAdminStatsOverview::canView())->toBeTrue();

    $role->revokePermissionTo('View:SuperAdminStatsOverview');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect(SuperAdminStatsOverview::canView())->toBeFalse();
});

it('Test 7 & 8: preserves super_admin authorization for Resources and Pages', function () {
    app(DashboardRbacSeeder::class)->run();

    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
    syncWidgetPermissionsForRole($superAdminRole, []);

    $user = User::factory()->create();
    $user->assignRole($superAdminRole);
    $this->actingAs($user);

    // Resource policies remain allowed for super_admin
    expect($user->can('viewAny', FundRequest::class))->toBeTrue()
        ->and($user->can('create', FundRequest::class))->toBeTrue()
        ->and($user->can('viewAny', RkaHeader::class))->toBeTrue()
        ->and($user->can('create', RkaHeader::class))->toBeTrue();

    // Super admin page access remains allowed
    expect(BackupManagement::canAccess())->toBeTrue();
});

it('Test 9 & 10: dashboard rendering filters out unauthorized widgets and direct gate check denies ungranted widget', function () {
    app(DashboardRbacSeeder::class)->run();

    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
    syncWidgetPermissionsForRole($superAdminRole, ['View:DashboardContextBanner']);

    $user = User::factory()->create();
    $user->assignRole($superAdminRole);
    $this->actingAs($user);

    expect($user->can('View:DashboardContextBanner'))->toBeTrue()
        ->and($user->can('View:SuperAdminStatsOverview'))->toBeFalse()
        ->and($user->can('View:RkaOverviewWidget'))->toBeFalse();

    $dashboard = app(Dashboard::class);
    $visibleWidgets = collect($dashboard->getVisibleWidgets());

    expect($visibleWidgets)->toContain(DashboardContextBanner::class)
        ->not->toContain(SuperAdminStatsOverview::class)
        ->not->toContain(RkaOverviewWidget::class);
});
