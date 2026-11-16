# Audit Widget Dashboard Existing — SIMRKA Politeknik Kampar

## 1. Ringkasan Temuan Audit
- **Total Widget Existing:** 19 widget pada `app/Filament/Widgets/` (terdaftar via `discoverWidgets` di `AdminPanelProvider`).
- **Temuan Utama:**
  1. **Modul RKA belum terwakili di dashboard:** Seluruh 19 widget sebelumnya hanya membaca `FundRequest` dan `DepartmentBudget`, tanpa menampilkan status/usulan/persetujuan `RkaHeader` & `RkaItem`.
  2. **Masalah N+1 Query:** `FinanceDepartmentBudgetChart`, `WadirDepartmentUtilizationTable`, `DirectorDepartmentPerformance`, dan `DirectorFiscalYearDistributionChart` melakukan query berulang di dalam loop/closure `formatStateUsing()`.
  3. **Tautan Aksi Queue Salah:** `FinanceVerificationQueue`, `WadirPendingApprovalTable`, dan `DirectorExecutiveApprovalQueue` mengarah ke `fund-requests.edit` alih-alih `fund-requests.verify`.
  4. **Duplikasi KPI:** Role `keuangan` dan `wadir_keuangan` memiliki izin `View:SuperAdminStatsOverview` di samping widget KPI role masing-masing.
  5. **Kesalahan Query & Filter:** `SuperAdminSystemHealth` menggunakan `DepartmentBudget::distinct('fiscal_year')->count('department_id')`; `DepartmentTopAccounts` dan `DirectorFinancialBreakdownMatrix` tidak memfilter `verification_status IN ('approved', 'partial')`; `SuperAdminRecentActivity` hanya menampilkan 10 baris `FundRequest` alih-alih log aktivitas RKA & approval.
  6. **Role `staf_keuangan` belum tersedia:** Belum ada role read-only untuk pemantauan keuangan.

## 2. Inventaris & Keputusan Widget

| Widget | Keputusan | Masalah / Catatan Audit | Tindakan |
|---|---|---|---|
| `DashboardContextBanner` | **CREATE** | Belum ada header konteks user, role, unit, TA aktif | Dibuat baru (`sort = 0`) |
| `RkaOverviewWidget` | **CREATE** | Belum ada ringkasan RKA di dashboard | Dibuat baru (`sort = 2`), scoped per role/unit |
| `FinanceMonitoringTable` | **CREATE** | Belum ada tabel pemantauan read-only untuk `staf_keuangan` | Dibuat baru (`sort = 2`) |
| `SuperAdminStatsOverview` | **IMPROVE** | Terbuka di `keuangan`/`wadir_keuangan` | Dicabut dari `keuangan` & `wadir_keuangan` |
| `SuperAdminSystemHealth` | **IMPROVE** | Bug `distinct('fiscal_year')->count('department_id')` | Diperbaiki dengan filter `fiscal_year` + count users |
| `SuperAdminRecentActivity` | **IMPROVE** | Hanya query `FundRequest` | Diubah ke unified `RkaLog` + `FundRequestApproval` |
| `DepartmentStatsOverview` | **IMPROVE** | Sudah baik, scoped ke `activeDepartment` | Dipertahankan |
| `DepartmentTrackingTable` | **IMPROVE** | Tidak ada action `Detail` | Ditambahkan action `view` ke `fund-requests.view` |
| `DepartmentTopAccounts` | **IMPROVE** | Tidak filter `fiscal_year` & `verification_status` | Ditambahkan filter TA aktif & item `approved`/`partial` |
| `FinanceStatsOverview` | **KEEP** | Agregasi SQL sudah ringkas | Dipertahankan |
| `FinanceVerificationQueue` | **IMPROVE** | URL action ke `.edit` & tanpa eager loading | Diperbaiki ke `.verify` + `with('department')` |
| `FinanceDepartmentBudgetChart` | **IMPROVE** | N+1 query per departemen | Dipindah ke `DashboardMetricsService::getDepartmentComparison()` |
| `FinanceDisbursementTypeChart` | **KEEP** | Agregasi `GROUP BY` sudah efisien | Dipertahankan |
| `WadirStatsOverview` | **KEEP** | Agregasi SQL turnaround & approval | Dipertahankan |
| `WadirPendingApprovalTable` | **IMPROVE** | URL action ke `.edit` | Diperbaiki ke `.verify` |
| `WadirDepartmentUtilizationTable` | **IMPROVE** | 6+ N+1 query per baris departemen | Dioptimalkan dengan `withSum` (1 query) |
| `WadirMonthlyDisbursementTrendChart` | **KEEP** | Agregasi `GROUP BY` bulanan | Dipertahankan |
| `DirectorStatsOverview` | **KEEP** | KPI makro institusi | Dipertahankan |
| `DirectorExecutiveApprovalQueue` | **IMPROVE** | URL action ke `.edit` & tanpa eager loading | Diperbaiki ke `.verify` + `with('department')` |
| `DirectorDepartmentPerformance` | **IMPROVE** | N+1 query per departemen | Dipindah ke `DashboardMetricsService::getDepartmentComparison()` |
| `DirectorFinancialBreakdownMatrix` | **IMPROVE** | Tidak filter `verification_status` | Ditambahkan filter `approved`/`partial` |
| `DirectorFiscalYearDistributionChart` | **IMPROVE** | N+1 query per tahun anggaran | Dipindah ke `DashboardMetricsService::getFiscalYearComparison()` |
