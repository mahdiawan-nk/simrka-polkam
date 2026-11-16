# Dokumentasi Implementasi Role-Based Dashboard Widget Assignment

## 1. Daftar Role Aktual & Alasan Assignment
1. **`super_admin`** (5 widget): Fokus pada pengelolaan & kesehatan sistem (`DashboardContextBanner`, `SuperAdminStatsOverview`, `SuperAdminSystemHealth`, `SuperAdminRecentActivity`, `RkaOverviewWidget`). Tidak diberikan widget operasional antrean approval agar dashboard tetap ringkas dan terfokus.
2. **`direktur`** (9 widget): Fokus pada ringkasan eksekutif, antrean pengesahan akhir (`DirectorExecutiveApprovalQueue`), performa unit, komparasi pagu & realisasi lintas TA, matriks akun, tren bulanan, dan ringkasan RKA.
3. **`wadir_keuangan`** (6 widget): Fokus pada verifikasi tahap Wakil Direktur II (`WadirStatsOverview`, `WadirPendingApprovalTable`), penyerapan per departemen, tren pencairan bulanan, dan ringkasan RKA. Tidak diberikan `DirectorExecutiveApprovalQueue`.
4. **`keuangan`** (7 widget): Fokus pada antrean verifikasi keuangan (`FinanceVerificationQueue`), KPI verifikasi (`FinanceStatsOverview`), grafik komparasi pagu, jenis pencairan, penyerapan unit, dan ringkasan RKA.
5. **`staf_keuangan`** (9 widget, read-only): Fokus pada pemantauan keuangan (`FinanceMonitoringTable`, `FinanceStatsOverview`, grafik anggaran & jenis pencairan, penyerapan unit, matriks akun, tren bulanan, dan ringkasan RKA). Tidak diberikan widget antrean aksi mutasi (`FinanceVerificationQueue`, `WadirPendingApprovalTable`, `DirectorExecutiveApprovalQueue`).
6. **`pemohon`** (5 widget, unit-scoped): Fokus pada pagu, realisasi, tracking pengajuan, top akun, dan RKA unit kerja aktif (`ContextManager`). Tidak diberikan statistik atau antrean lintas unit.

## 2. Seeder & Cara Menjalankan
- **Seeder:** `database/seeders/DashboardWidgetPermissionSeeder.php`
- **Karakteristik:** Idempotent, hanya menyinkronkan subset permission widget (`FilamentShield::getWidgets()`), menjaga seluruh permission Resource/Page/Custom tetap utuh, tidak mengubah role lain yang tidak terdaftar pada matriks, dan tidak mengubah `model_has_roles`.
- **Perintah eksekusi:**
  ```bash
  docker compose exec app php artisan db:seed --class=DashboardWidgetPermissionSeeder
  ```

## 3. Hasil Pengujian
- `tests/Feature/Authorization/DashboardWidgetRoleAssignmentTest.php`: Menguji idempotensi seeder, kecocokan matriks 6 role, pelestarian permission Resource/Page & user-role assignment, serta penegakan visibilitas dan department scope.
