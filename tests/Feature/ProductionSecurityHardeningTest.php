<?php

use App\Models\Department;
use App\Models\FundRequest;
use App\Models\RkaHeader;
use App\Models\RkaSchedule;
use App\Models\User;
use App\Models\UserDepartment;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    foreach ([
        'fund_requests',
        'rka_headers',
        'rka_schedules',
        'user_departments',
        'departments',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'roles',
        'permissions',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

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

    Schema::create('departments', function (Blueprint $table): void {
        $table->id();
        $table->string('type')->default('PRODI');
        $table->string('code')->unique();
        $table->string('level_code')->default('3');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('user_departments', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->foreignId('department_id');
        $table->boolean('is_primary')->default(true);
        $table->timestamps();
    });

    Schema::create('rka_schedules', function (Blueprint $table): void {
        $table->id();
        $table->string('fiscal_year');
        $table->string('period_range')->nullable();
        $table->string('phase_name')->nullable();
        $table->timestamp('start_at')->nullable();
        $table->timestamp('end_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->foreignId('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('rka_headers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('schedule_id');
        $table->foreignId('department_id');
        $table->string('fiscal_year')->nullable();
        $table->string('status')->default('draft');
        $table->timestamps();
    });

    Schema::create('fund_requests', function (Blueprint $table): void {
        $table->id();
        $table->string('document_number')->nullable();
        $table->foreignId('department_id');
        $table->string('fiscal_year');
        $table->string('status')->default('draft');
        $table->foreignId('created_by')->nullable();
        $table->timestamps();
    });

    foreach ([
        'View:FundRequest',
        'Update:FundRequest',
        'Delete:FundRequest',
        'View:RkaHeader',
        'Update:RkaHeader',
        'Delete:RkaHeader',
    ] as $perm) {
        Permission::query()->firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('user model implements FilamentUser and allows panel access in production env', function (): void {
    config(['app.env' => 'production']);

    $user = User::factory()->create();
    $panel = Filament::getPanel('admin');

    expect($user)->toBeInstanceOf(FilamentUser::class)
        ->and($user->canAccessPanel($panel))->toBeTrue();
});

test('pemohon cannot view or update fund requests or rka headers belonging to another department (IDOR protection)', function (): void {
    $pemohonRole = Role::query()->firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $pemohonRole->syncPermissions([
        'View:FundRequest',
        'Update:FundRequest',
        'Delete:FundRequest',
        'View:RkaHeader',
        'Update:RkaHeader',
        'Delete:RkaHeader',
    ]);

    $keuanganRole = Role::query()->firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $keuanganRole->syncPermissions(['View:FundRequest', 'View:RkaHeader']);

    $deptA = Department::query()->create(['code' => 'UNIT-A', 'name' => 'Unit A']);
    $deptB = Department::query()->create(['code' => 'UNIT-B', 'name' => 'Unit B']);

    $userA = User::factory()->create();
    $userA->assignRole($pemohonRole);
    UserDepartment::query()->create(['user_id' => $userA->id, 'department_id' => $deptA->id, 'is_primary' => true]);

    $userB = User::factory()->create();
    $userB->assignRole($pemohonRole);
    UserDepartment::query()->create(['user_id' => $userB->id, 'department_id' => $deptB->id, 'is_primary' => true]);

    $keuangan = User::factory()->create();
    $keuangan->assignRole($keuanganRole);

    $schedule = RkaSchedule::query()->create(['fiscal_year' => '2026/2027', 'is_active' => true]);
    $rkaA = RkaHeader::query()->create(['schedule_id' => $schedule->id, 'department_id' => $deptA->id, 'fiscal_year' => '2026/2027']);
    $rkaB = RkaHeader::query()->create(['schedule_id' => $schedule->id, 'department_id' => $deptB->id, 'fiscal_year' => '2026/2027']);

    $frA = FundRequest::query()->create(['document_number' => 'FR-A', 'department_id' => $deptA->id, 'fiscal_year' => '2026/2027', 'created_by' => $userA->id]);
    $frB = FundRequest::query()->create(['document_number' => 'FR-B', 'department_id' => $deptB->id, 'fiscal_year' => '2026/2027', 'created_by' => $userB->id]);

    // Own department: allowed
    expect(Gate::forUser($userA)->allows('view', $frA))->toBeTrue()
        ->and(Gate::forUser($userA)->allows('update', $frA))->toBeTrue()
        ->and(Gate::forUser($userA)->allows('view', $rkaA))->toBeTrue()
        ->and(Gate::forUser($userA)->allows('update', $rkaA))->toBeTrue();

    // Other department: denied (IDOR blocked)
    expect(Gate::forUser($userA)->allows('view', $frB))->toBeFalse()
        ->and(Gate::forUser($userA)->allows('update', $frB))->toBeFalse()
        ->and(Gate::forUser($userA)->allows('delete', $frB))->toBeFalse()
        ->and(Gate::forUser($userA)->allows('view', $rkaB))->toBeFalse()
        ->and(Gate::forUser($userA)->allows('update', $rkaB))->toBeFalse()
        ->and(Gate::forUser($userA)->allows('delete', $rkaB))->toBeFalse();

    // Cross-department finance role: allowed to view both
    expect(Gate::forUser($keuangan)->allows('view', $frA))->toBeTrue()
        ->and(Gate::forUser($keuangan)->allows('view', $frB))->toBeTrue()
        ->and(Gate::forUser($keuangan)->allows('view', $rkaA))->toBeTrue()
        ->and(Gate::forUser($keuangan)->allows('view', $rkaB))->toBeTrue();
});

test('account template download route denies unauthorized users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/accounts/download-template')
        ->assertForbidden();
});
