<?php

use App\Filament\Pages\Database\BackupManagement;
use App\Filament\Pages\Development\CleanDataRkaPage;
use App\Filament\Pages\Rka\ManageReconciliationsPage;
use App\Filament\Pages\Rka\ManageUnlockRequestsPage;
use App\Filament\Pages\Settings\BrandIdentitySettings;
use App\Filament\Widgets\SuperAdminStatsOverview;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Schema::dropIfExists('brand_settings');
    Schema::dropIfExists('role_has_permissions');
    Schema::dropIfExists('model_has_roles');
    Schema::dropIfExists('model_has_permissions');
    Schema::dropIfExists('roles');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });

    Schema::create('model_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
    });

    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
    });

    Schema::create('role_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
    });

    $migration = require database_path('migrations/2026_11_17_010006_create_brand_settings_table.php');
    $migration->up();

    foreach ([
        'View:BrandIdentitySettings',
        'View:BackupManagement',
        'View:CleanDataRkaPage',
        'View:ManageReconciliationsPage',
        'View:ManageUnlockRequestsPage',
        'View:SuperAdminStatsOverview',
    ] as $permissionName) {
        Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('scenario A & B: super_admin only sees and accesses pages explicitly granted via shield', function (): void {
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->syncPermissions(['View:BrandIdentitySettings']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($role);
    $this->actingAs($superAdmin);

    // Granted page is visible & accessible
    expect(BrandIdentitySettings::canAccess())->toBeTrue()
        ->and(BrandIdentitySettings::shouldRegisterNavigation())->toBeTrue();
    Livewire::test(BrandIdentitySettings::class)->assertOk();

    // Unchecked pages are hidden from navigation & forbidden on direct access
    expect(BackupManagement::canAccess())->toBeFalse()
        ->and(BackupManagement::shouldRegisterNavigation())->toBeFalse()
        ->and(CleanDataRkaPage::canAccess())->toBeFalse()
        ->and(CleanDataRkaPage::shouldRegisterNavigation())->toBeFalse()
        ->and(ManageReconciliationsPage::canAccess())->toBeFalse()
        ->and(ManageReconciliationsPage::shouldRegisterNavigation())->toBeFalse()
        ->and(ManageUnlockRequestsPage::canAccess())->toBeFalse()
        ->and(ManageUnlockRequestsPage::shouldRegisterNavigation())->toBeFalse();

    Livewire::test(BackupManagement::class)->assertForbidden();
    Livewire::test(CleanDataRkaPage::class)->assertForbidden();
    Livewire::test(ManageReconciliationsPage::class)->assertForbidden();
    Livewire::test(ManageUnlockRequestsPage::class)->assertForbidden();
});

test('scenario C: revoking page permission immediately hides navigation and blocks access', function (): void {
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->syncPermissions(['View:BrandIdentitySettings']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($role);
    $this->actingAs($superAdmin);

    expect(BrandIdentitySettings::canAccess())->toBeTrue();

    // Revoke permission via Spatie/Shield mechanism
    $role->revokePermissionTo('View:BrandIdentitySettings');
    $superAdmin->unsetRelation('roles')->unsetRelation('permissions');

    expect(BrandIdentitySettings::canAccess())->toBeFalse()
        ->and(BrandIdentitySettings::shouldRegisterNavigation())->toBeFalse();

    Livewire::test(BrandIdentitySettings::class)->assertForbidden();
});

test('scenario D: another role only accesses pages explicitly granted to it', function (): void {
    $role = Role::query()->firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $role->syncPermissions(['View:ManageUnlockRequestsPage']);

    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    expect(ManageUnlockRequestsPage::canAccess())->toBeTrue()
        ->and(ManageUnlockRequestsPage::shouldRegisterNavigation())->toBeTrue()
        ->and(BrandIdentitySettings::canAccess())->toBeFalse()
        ->and(BackupManagement::canAccess())->toBeFalse()
        ->and(ManageReconciliationsPage::canAccess())->toBeFalse();

    Livewire::test(BrandIdentitySettings::class)->assertForbidden();
    Livewire::test(BackupManagement::class)->assertForbidden();
});

test('scenario E: super_admin keeps resource policy gate while widget permissions remain explicitly enforced', function (): void {
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->syncPermissions([]);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($role);
    $this->actingAs($superAdmin);

    // Resource policy ability still passes Gate::before for super_admin
    expect(Gate::allows('ViewAny:User'))->toBeTrue();

    // Widget permission remains explicitly enforced via Shield
    expect(SuperAdminStatsOverview::canView())->toBeFalse();

    $role->givePermissionTo('View:SuperAdminStatsOverview');
    $superAdmin->unsetRelation('roles')->unsetRelation('permissions');

    expect(SuperAdminStatsOverview::canView())->toBeTrue();
});
