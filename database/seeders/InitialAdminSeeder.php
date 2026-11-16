<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->first();

        if (! $superAdminRole) {
            throw new RuntimeException('Role super_admin belum tersedia. Jalankan RoleSeeder terlebih dahulu.');
        }

        $name = trim((string) env('INITIAL_ADMIN_NAME', 'Administrator'));
        $email = trim((string) env('INITIAL_ADMIN_EMAIL', ''));
        $password = (string) env('INITIAL_ADMIN_PASSWORD', '');

        // Jika sudah ada akun dengan email tersebut, pastikan memiliki role super_admin tanpa mengubah password.
        if ($email !== '') {
            $existingUser = User::query()->where('email', $email)->first();
            if ($existingUser) {
                if (! $existingUser->hasRole('super_admin')) {
                    $existingUser->assignRole($superAdminRole);
                }

                return;
            }
        }

        // Jika sudah ada user ber-role super_admin (mis. saat re-run di database existing), jangan buat duplikat.
        if (User::role('super_admin')->exists()) {
            return;
        }

        if ($email === '' || $password === '') {
            throw new RuntimeException(
                'INITIAL_ADMIN_EMAIL dan INITIAL_ADMIN_PASSWORD wajib diisi pada environment (.env) untuk membuat akun Super Admin awal.'
            );
        }

        $admin = User::query()->create([
            'name' => $name !== '' ? $name : 'Administrator',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
        ]);

        $admin->assignRole($superAdminRole);
    }
}
