# Laporan Implementasi Redesign Dashboard & Widget SIMRKA

## 1. Ringkasan Perubahan
- Menambahkan service terpusat `App\Services\Dashboard\DashboardMetricsService` untuk menyatukan agregasi KPI pagu, RKA, Fund Request, perbandingan departemen, perbandingan tahun anggaran, dan aktivitas sistem tanpa N+1 query.
- Menambahkan 3 widget baru:
  - `DashboardContextBanner` (header konteks user, role, unit, dan tahun anggaran aktif).
  - `RkaOverviewWidget` (ringkasan status dan total usulan vs disetujui RKA sesuai scope role).
  - `FinanceMonitoringTable` (tabel pemantauan pengajuan dana read-only untuk `staf_keuangan`).
- Mengoptimalkan query pada `FinanceDepartmentBudgetChart`, `WadirDepartmentUtilizationTable`, `DirectorDepartmentPerformance`, dan `DirectorFiscalYearDistributionChart` sehingga bebas N+1 query.
- Memperbaiki URL action pada `FinanceVerificationQueue`, `WadirPendingApprovalTable`, dan `DirectorExecutiveApprovalQueue` agar mengarah ke rute `filament.admin.resources.fund-requests.verify`.
- Memperbaiki akurasi filter `fiscal_year` dan `verification_status IN ('approved', 'partial')` pada `DepartmentTopAccounts`, `DirectorFinancialBreakdownMatrix`, dan `SuperAdminSystemHealth`.
- Mengimplementasikan role baru `staf_keuangan` melalui `Database\Seeders\DashboardRbacSeeder` dengan akses **read-only** penuh dan permission eksplisit `ViewAllUnits:*` pada `FundRequestPolicy` dan `RkaHeaderPolicy`.

## 2. Verifikasi & Pengujian
- `DashboardRbacSeeder` dijalankan pada database aplikasi.
- `vendor/bin/pint --dirty --format agent` lulus.
- `php artisan test tests/Feature/Dashboard/DashboardWidgetsAndRbacTest.php --compact` menguji seluruh role (`super_admin`, `staf_keuangan`, `keuangan`, `wadir_keuangan`, `direktur`, `pemohon`), proteksi URL langsung, pembatasan unit tanpa `ViewAllUnits:*`, serta akurasi KPI tanpa double counting.
