# Matriks Role & Widget Dashboard — SIMRKA Politeknik Kampar

## 1. Hasil Audit Role Aktual (`roles`)
| ID | Nama Role | Guard | Jumlah User | Asal / Status | Tanggung Jawab Utama |
|---|---|---|---|---|---|
| 1 | `super_admin` | `web` | 1 | Bawaan / Aktif | Pengelolaan sistem, kesehatan data, monitoring aktivitas & RKA global |
| 2 | `keuangan` | `web` | 1 | Bawaan / Aktif | Kabag KKU: verifikasi Fund Request (`submitted`), master akun & pagu |
| 3 | `wadir_keuangan` | `web` | 2 | Bawaan / Aktif | Wakil Direktur II: persetujuan tahap Wadir (`verified_kabakku`) & penyerapan unit |
| 4 | `direktur` | `web` | 2 | Bawaan / Aktif | Direktur: pengesahan akhir (`verified_wadir_bakku`) & kinerja makro institusi |
| 5 | `pemohon` | `web` | 22 | Bawaan / Aktif | Kepala Unit / Pemohon: penyusunan RKA & pengajuan pencairan unit masing-masing |
| 6 | `staf_keuangan` | `web` | 0 | `DashboardRbacSeeder` / Siap pakai | Staf Keuangan (Read-Only): pemantauan anggaran, RKA, dan pencairan lintas unit |

## 2. Hasil Audit 22 Widget (`app/Filament/Widgets/`)
Seluruh 22 widget menggunakan trait `BezhanSalleh\FilamentShield\Traits\HasWidgetShield` dan terdaftar via `discoverWidgets()` di `AdminPanelProvider`.

| Widget Class | Permission Shield | Scope Data | Read-Only |
|---|---|---|---|
| `DashboardContextBanner` | `View:DashboardContextBanner` | Konteks user aktif | Ya |
| `RkaOverviewWidget` | `View:RkaOverviewWidget` | Global (`ViewAllUnits:RkaHeader`) / Unit aktif | Ya |
| `SuperAdminStatsOverview` | `View:SuperAdminStatsOverview` | Global institusi | Ya |
| `SuperAdminSystemHealth` | `View:SuperAdminSystemHealth` | Global sistem & master data | Ya |
| `SuperAdminRecentActivity` | `View:SuperAdminRecentActivity` | Global log RKA & Approval | Ya |
| `DirectorStatsOverview` | `View:DirectorStatsOverview` | Global eksekutif | Ya |
| `DirectorExecutiveApprovalQueue` | `View:DirectorExecutiveApprovalQueue` | Global (`verified_wadir_bakku`) | Aksi tinjau |
| `DirectorDepartmentPerformance` | `View:DirectorDepartmentPerformance` | Global per unit | Ya |
| `DirectorFinancialBreakdownMatrix` | `View:DirectorFinancialBreakdownMatrix` | Global per kode akun | Ya |
| `DirectorFiscalYearDistributionChart` | `View:DirectorFiscalYearDistributionChart` | Global lintas TA | Ya |
| `WadirStatsOverview` | `View:WadirStatsOverview` | Global (`verified_kabakku`) | Ya |
| `WadirPendingApprovalTable` | `View:WadirPendingApprovalTable` | Global (`verified_kabakku`) | Aksi approval |
| `WadirDepartmentUtilizationTable` | `View:WadirDepartmentUtilizationTable` | Global per unit | Ya |
| `WadirMonthlyDisbursementTrendChart` | `View:WadirMonthlyDisbursementTrendChart` | Global bulanan | Ya |
| `FinanceStatsOverview` | `View:FinanceStatsOverview` | Global keuangan | Ya |
| `FinanceVerificationQueue` | `View:FinanceVerificationQueue` | Global (`submitted`) | Aksi verifikasi |
| `FinanceMonitoringTable` | `View:FinanceMonitoringTable` | Global (`ViewAllUnits:FundRequest`) / Unit | Ya (Read-Only) |
| `FinanceDepartmentBudgetChart` | `View:FinanceDepartmentBudgetChart` | Global per unit | Ya |
| `FinanceDisbursementTypeChart` | `View:FinanceDisbursementTypeChart` | Global jenis pencairan | Ya |
| `DepartmentStatsOverview` | `View:DepartmentStatsOverview` | Unit aktif (`ContextManager`) | Ya |
| `DepartmentTrackingTable` | `View:DepartmentTrackingTable` | Unit aktif (`ContextManager`) | Link detail |
| `DepartmentTopAccounts` | `View:DepartmentTopAccounts` | Unit aktif (`ContextManager`) | Ya |

## 3. Matriks Role × Widget Final

- **`super_admin` (5 widget):** `DashboardContextBanner`, `SuperAdminStatsOverview`, `SuperAdminSystemHealth`, `SuperAdminRecentActivity`, `RkaOverviewWidget`
- **`direktur` (9 widget):** `DashboardContextBanner`, `DirectorStatsOverview`, `DirectorExecutiveApprovalQueue`, `DirectorDepartmentPerformance`, `FinanceDepartmentBudgetChart`, `WadirMonthlyDisbursementTrendChart`, `DirectorFiscalYearDistributionChart`, `DirectorFinancialBreakdownMatrix`, `RkaOverviewWidget`
- **`wadir_keuangan` (6 widget):** `DashboardContextBanner`, `WadirStatsOverview`, `WadirPendingApprovalTable`, `WadirDepartmentUtilizationTable`, `WadirMonthlyDisbursementTrendChart`, `RkaOverviewWidget`
- **`keuangan` (7 widget):** `DashboardContextBanner`, `FinanceStatsOverview`, `FinanceVerificationQueue`, `FinanceDepartmentBudgetChart`, `FinanceDisbursementTypeChart`, `WadirDepartmentUtilizationTable`, `RkaOverviewWidget`
- **`staf_keuangan` (9 widget, read-only):** `DashboardContextBanner`, `FinanceStatsOverview`, `FinanceMonitoringTable`, `FinanceDepartmentBudgetChart`, `FinanceDisbursementTypeChart`, `WadirDepartmentUtilizationTable`, `DirectorFinancialBreakdownMatrix`, `WadirMonthlyDisbursementTrendChart`, `RkaOverviewWidget`
- **`pemohon` (5 widget, unit-scoped):** `DashboardContextBanner`, `DepartmentStatsOverview`, `DepartmentTrackingTable`, `DepartmentTopAccounts`, `RkaOverviewWidget`
