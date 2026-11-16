# Matriks Role × Widget × Permission × Data Scope

## 1. Daftar Role Aktual
1. `super_admin` — Administrator penuh lintas unit.
2. `keuangan` — Verifikator Keuangan / Kabag KKU (operasional verifikasi & master anggaran).
3. `staf_keuangan` *(Baru)* — Staf Keuangan **Read-Only** untuk pemantauan anggaran, RKA, dan Fund Request.
4. `wadir_keuangan` — Wakil Direktur II (tahap persetujuan Wadir BAKKU & monitoring penyerapan unit).
5. `direktur` — Direktur Politeknik Kampar (pengesahan akhir & monitoring kinerja makro).
6. `pemohon` — Kepala Unit / Pemohon (operasional unit kerja masing-masing).

## 2. Matriks Registrasi Widget

| Widget | `super_admin` | `keuangan` | `staf_keuangan` | `wadir_keuangan` | `direktur` | `pemohon` | Data Scope |
|---|---|---|---|---|---|---|---|
| `DashboardContextBanner` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Konteks user aktif |
| `RkaOverviewWidget` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Global (`ViewAllUnits:RkaHeader`) / Unit aktif |
| `SuperAdminStatsOverview` | ✓ | — | — | — | — | — | Global |
| `SuperAdminSystemHealth` | ✓ | — | — | — | — | — | Global |
| `SuperAdminRecentActivity` | ✓ | — | — | — | — | — | Global |
| `FinanceStatsOverview` | — | ✓ | ✓ | — | — | — | Global Keuangan |
| `FinanceVerificationQueue` | — | ✓ | — | — | — | — | Global (`status=submitted`, operasional) |
| `FinanceMonitoringTable` | — | — | ✓ | — | — | — | Read-Only (`ViewAllUnits:FundRequest` / Unit) |
| `FinanceDepartmentBudgetChart` | — | ✓ | ✓ | — | — | — | Global per Unit |
| `FinanceDisbursementTypeChart` | — | ✓ | ✓ | — | — | — | Global |
| `WadirStatsOverview` | — | — | — | ✓ | — | — | Global (`verified_kabakku`) |
| `WadirPendingApprovalTable` | — | — | — | ✓ | — | — | Global (`verified_kabakku`) |
| `WadirDepartmentUtilizationTable` | — | — | ✓ | ✓ | — | — | Global per Unit |
| `WadirMonthlyDisbursementTrendChart` | — | — | — | ✓ | — | — | Global |
| `DirectorStatsOverview` | — | — | — | — | ✓ | — | Global |
| `DirectorExecutiveApprovalQueue` | — | — | — | — | ✓ | — | Global (`verified_wadir_bakku`) |
| `DirectorDepartmentPerformance` | — | — | — | — | ✓ | — | Global per Unit |
| `DirectorFinancialBreakdownMatrix` | — | — | — | — | ✓ | — | Global per Akun |
| `DirectorFiscalYearDistributionChart` | — | — | — | — | ✓ | — | Global Lintas TA |
| `DepartmentStatsOverview` | — | — | — | — | — | ✓ | Unit aktif (`ContextManager`) |
| `DepartmentTrackingTable` | — | — | — | — | — | ✓ | Unit aktif (`ContextManager`) |
| `DepartmentTopAccounts` | — | — | — | — | — | ✓ | Unit aktif (`ContextManager`) |

## 3. Permission Baru (`DashboardRbacSeeder`)
- `View:RkaOverviewWidget`
- `View:FinanceMonitoringTable`
- `ViewAllUnits:FundRequest` — Izin eksplisit akses baca lintas unit pada `FundRequestPolicy`.
- `ViewAllUnits:RkaHeader` — Izin eksplisit akses baca lintas unit pada `RkaHeaderPolicy`.
- `ViewAllUnits:DepartmentBudget` — Izin eksplisit akses baca lintas unit untuk `DepartmentBudget`.
