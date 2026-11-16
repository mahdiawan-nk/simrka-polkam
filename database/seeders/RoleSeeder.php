<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Daftar role aktual yang digunakan dalam workflow SIMRKA / e-RKA.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        $roles = [
            'super_admin',
            'direktur',
            'wadir_keuangan',
            'keuangan',
            'staf_keuangan',
            'pemohon',
        ];

        if (config('filament-shield.panel_user.enabled', false)) {
            $panelUserRole = (string) config('filament-shield.panel_user.name', 'panel_user');
            if ($panelUserRole !== '' && ! in_array($panelUserRole, $roles, true)) {
                $roles[] = $panelUserRole;
            }
        }

        return $roles;
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::roles() as $roleName) {
            Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
