<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Inisialisasi data master, RBAC, akun administrator awal, dan konfigurasi aplikasi.
     * Aman dijalankan ulang (idempotent) pada database production maupun development.
     */
    public function run(): void
    {
        $this->call([
            Departmentseeder::class,
            AccountDataSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            DashboardRbacSeeder::class,
            DashboardWidgetPermissionSeeder::class,
            InitialAdminSeeder::class,
            ApplicationSettingsSeeder::class,
            KnowledgeCenterSeeder::class,
        ]);
    }
}
