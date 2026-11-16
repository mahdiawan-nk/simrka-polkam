<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Settings\BrandIdentitySettings;
use App\Models\User;
use App\Services\Branding\BrandSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Storage::fake('public');
    Cache::flush();

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

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function createUserWithRole(string $roleName): User
{
    $role = Role::query()->firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    if ($roleName === 'super_admin') {
        $permission = Permission::query()->firstOrCreate([
            'name' => 'View:BrandIdentitySettings',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('brand settings service returns defaults when database is empty and falls back safely for missing files', function (): void {
    $service = app(BrandSettingsService::class);

    expect($service->getBrandName())->toBe('e-RKA')
        ->and($service->getInstitutionName())->toBe('Politeknik Kampar')
        ->and($service->getLogoUrl('primary'))->toBeNull()
        ->and($service->getFaviconUrl())->toBeNull()
        ->and($service->resolvePublicUrl('branding/non-existent-logo.png'))->toBeNull();
});

test('brand settings service persists updates, invalidates cache, and rejects unsafe svg', function (): void {
    $service = app(BrandSettingsService::class);

    // Prime cache
    expect($service->getBrandName())->toBe('e-RKA');

    $service->updateSettings([
        'brand_name' => 'SIMRKA-PRO',
        'institution_name' => 'Politeknik Negeri Uji',
        'tagline' => 'Transparansi Anggaran Akademik',
        'browser_title' => 'SIMRKA-PRO — Politeknik Negeri Uji',
    ]);

    expect($service->getBrandName())->toBe('SIMRKA-PRO')
        ->and($service->getInstitutionName())->toBe('Politeknik Negeri Uji')
        ->and($service->getTagline())->toBe('Transparansi Anggaran Akademik')
        ->and($service->getBrowserTitle())->toBe('SIMRKA-PRO — Politeknik Negeri Uji');

    // Unsafe SVG must be rejected and deleted
    Storage::disk('public')->put('branding/evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    expect(fn () => $service->updateSettings(['logo_path' => 'branding/evil.svg']))
        ->toThrow(ValidationException::class);

    expect(Storage::disk('public')->exists('branding/evil.svg'))->toBeFalse();
});

test('only super admin can access brand identity settings page', function (): void {
    $superAdmin = createUserWithRole('super_admin');
    $pemohon = createUserWithRole('pemohon');
    $keuangan = createUserWithRole('keuangan');

    $this->actingAs($superAdmin);
    expect(BrandIdentitySettings::canAccess())->toBeTrue();
    Livewire::test(BrandIdentitySettings::class)->assertOk();

    $this->actingAs($pemohon);
    expect(BrandIdentitySettings::canAccess())->toBeFalse();
    Livewire::test(BrandIdentitySettings::class)->assertForbidden();

    $this->actingAs($keuangan);
    expect(BrandIdentitySettings::canAccess())->toBeFalse();
    Livewire::test(BrandIdentitySettings::class)->assertForbidden();
});

test('super admin can update brand text, logo, and favicon via livewire and see them reflected globally', function (): void {
    $superAdmin = createUserWithRole('super_admin');
    $this->actingAs($superAdmin);

    $logo = UploadedFile::fake()->image('logo.png', 200, 60);
    $favicon = UploadedFile::fake()->image('favicon.png', 32, 32);

    Livewire::test(BrandIdentitySettings::class)
        ->fillForm([
            'brand_name' => 'e-RKA Baru',
            'app_full_name' => 'Sistem Keuangan Kampus',
            'institution_name' => 'Politeknik Kampar Unggul',
            'institution_short_name' => 'PKU',
            'tagline' => 'Akuntabel dan Modern',
            'browser_title' => 'e-RKA Baru — PKU',
            'logo_path' => $logo,
            'favicon_path' => $favicon,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $service = app(BrandSettingsService::class);
    expect($service->getBrandName())->toBe('e-RKA Baru')
        ->and($service->getInstitutionName())->toBe('Politeknik Kampar Unggul')
        ->and($service->getLogoUrl('primary'))->not->toBeNull()
        ->and($service->getFaviconUrl())->not->toBeNull();

    // Verify header logo view and login page use updated dynamic brand
    $logoHtml = view('filament.logo')->render();
    expect($logoHtml)->toContain('e-RKA Baru')
        ->and($logoHtml)->toContain('PKU')
        ->and($logoHtml)->toContain('Sistem Keuangan Kampus');

    auth()->logout();

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('e-RKA Baru')
        ->assertSee('Politeknik Kampar Unggul')
        ->assertSee('Masuk ke Akun');
});

test('brand identity settings rejects invalid file types and oversized uploads', function (): void {
    $superAdmin = createUserWithRole('super_admin');
    $this->actingAs($superAdmin);

    $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $oversizedLogo = UploadedFile::fake()->image('huge.png', 800, 800)->size(3000);

    Livewire::test(BrandIdentitySettings::class)
        ->fillForm([
            'logo_path' => $invalidFile,
        ])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    Livewire::test(BrandIdentitySettings::class)
        ->fillForm([
            'logo_path' => $oversizedLogo,
        ])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);
});

test('redesigned login page authenticates valid users and shows error on invalid credentials', function (): void {
    $user = createUserWithRole('super_admin');

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Masuk ke Akun');

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});
