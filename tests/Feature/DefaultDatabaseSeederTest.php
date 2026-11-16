<?php

use App\Models\Account;
use App\Models\BrandSetting;
use App\Models\Department;
use App\Models\RkaSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    foreach ([
        'knowledge_article_steps',
        'knowledge_article_feedback',
        'knowledge_article_role',
        'knowledge_articles',
        'knowledge_categories',
        'rka_settings',
        'brand_settings',
        'accounts',
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
        $table->foreignId('parent_id')->nullable();
        $table->string('code')->unique();
        $table->string('type')->default('PRODI');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('parent_id')->nullable();
        $table->string('code')->unique();
        $table->string('code_clean')->nullable();
        $table->string('name');
        $table->string('parent_code')->nullable();
        $table->unsignedTinyInteger('level')->default(3);
        $table->string('level_label')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('rka_settings', function (Blueprint $table): void {
        $table->id();
        $table->string('setting_name');
        $table->foreignId('director_user_id')->nullable();
        $table->string('director_name')->nullable();
        $table->string('director_nrp')->nullable();
        $table->string('foundation_head_name')->nullable();
        $table->string('signing_place')->nullable();
        $table->json('pdf_header_config')->nullable();
        $table->json('pdf_footer_config')->nullable();
        $table->json('signing_config')->nullable();
        $table->timestamps();
    });

    $brandMigration = require database_path('migrations/2026_11_17_010006_create_brand_settings_table.php');
    $brandMigration->up();

    $kcMigration = require database_path('migrations/2026_11_17_023520_create_knowledge_center_tables.php');
    $kcMigration->up();

    $kcVisualMigration = require database_path('migrations/2026_11_17_025604_add_visual_steps_to_knowledge_center.php');
    $kcVisualMigration->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('initial admin seeder throws clear error when INITIAL_ADMIN credentials are missing on empty database', function (): void {
    putenv('INITIAL_ADMIN_EMAIL=');
    putenv('INITIAL_ADMIN_PASSWORD=');

    expect(fn () => $this->seed(DatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'INITIAL_ADMIN_EMAIL');
});

test('database seeder initializes all master data, rbac, initial admin, and settings idempotently', function (): void {
    putenv('INITIAL_ADMIN_NAME=Super Admin Kampus');
    putenv('INITIAL_ADMIN_EMAIL=admin@polkam.ac.id');
    putenv('INITIAL_ADMIN_PASSWORD=S3cureProdPassword!');

    $this->seed(DatabaseSeeder::class);

    // 1. Departments (27 actual units + valid parent_id)
    expect(Department::query()->count())->toBe(27);
    $bagUmum = Department::query()->where('code', 'BAG-UMUM')->first();
    $bakku = Department::query()->where('code', 'BAKKU')->first();
    expect($bagUmum?->parent_id)->toBe($bakku?->id);

    // 2. Accounts / COA (72 actual accounts: 19 parent + 53 detail + valid parent_id)
    expect(Account::query()->count())->toBe(72);
    $atk = Account::query()->where('code', '5.2.1.01')->first();
    $bahan = Account::query()->where('code', '5.2.1')->first();
    expect($atk?->level)->toBe(3)
        ->and($atk?->parent_id)->toBe($bahan?->id);

    // 3. Roles & Permissions
    foreach (['super_admin', 'direktur', 'wadir_keuangan', 'keuangan', 'staf_keuangan', 'pemohon'] as $roleName) {
        expect(Role::query()->where('name', $roleName)->exists())->toBeTrue();
    }

    $superAdminRole = Role::query()->where('name', 'super_admin')->firstOrFail();
    $stafKeuanganRole = Role::query()->where('name', 'staf_keuangan')->firstOrFail();

    expect($superAdminRole->hasPermissionTo('View:BrandIdentitySettings'))->toBeTrue()
        ->and($stafKeuanganRole->hasPermissionTo('ViewAny:FundRequest'))->toBeTrue()
        ->and($stafKeuanganRole->hasPermissionTo('Create:FundRequest'))->toBeFalse();

    // 4. Initial Super Admin
    $admin = User::query()->where('email', 'admin@polkam.ac.id')->first();
    expect($admin)->not->toBeNull()
        ->and($admin?->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('S3cureProdPassword!', (string) $admin?->password))->toBeTrue();

    // 5. Application Settings
    expect(BrandSetting::query()->count())->toBe(1)
        ->and(RkaSetting::query()->count())->toBe(1);

    // 6. Idempotency & non-destructive re-run checks
    // Simulasikan admin mengubah password dan mencabut 1 permission dari super_admin
    $admin->update(['password' => Hash::make('AdminChangedPassword99!')]);
    $superAdminRole->revokePermissionTo('View:BackupManagement');

    $permCountBefore = Permission::query()->count();

    // Jalankan seeder ulang dengan env password lama
    $this->seed(DatabaseSeeder::class);

    $admin->refresh();
    $superAdminRole->refresh();

    expect(Department::query()->count())->toBe(27)
        ->and(Account::query()->count())->toBe(72)
        ->and(User::query()->count())->toBe(1)
        ->and(Permission::query()->count())->toBe($permCountBefore)
        ->and(BrandSetting::query()->count())->toBe(1)
        ->and(RkaSetting::query()->count())->toBe(1)
        ->and(Hash::check('AdminChangedPassword99!', $admin->password))->toBeTrue()
        ->and($superAdminRole->hasPermissionTo('View:BackupManagement'))->toBeFalse();
});
