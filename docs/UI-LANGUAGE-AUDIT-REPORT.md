# Laporan Audit Bahasa Antarmuka & Tipografi (UI Language Audit Report)
**Aplikasi SIMRKA / e-RKA — Standardisasi Bahasa Indonesia Profesional**

---

## 1. Ringkasan Eksekutif

Audit menyeluruh dilakukan terhadap seluruh komponen antarmuka Filament 5, Livewire, Blade, konfigurasi lokalisasi Laravel, serta plugin pihak ketiga (Filament Shield & Filament Spatie Backup).

**Temuan Akar Masalah Utama:**
`APP_LOCALE` pada `.env`, `.env.example`, dan `config/app.php` sebelumnya masih disetel ke `en`, padahal Filament 5 (`filament/filament`), Filament Shield (`bezhansalleh/filament-shield`), dan `filament-spatie-backup` telah menyediakan paket terjemahan Bahasa Indonesia (`id`) secara bawaan. Selain itu, seluruh 9 *Resource* Filament belum mendefinisikan `$modelLabel` dan `$pluralModelLabel`, serta sejumlah *Widget* dan *Custom Page* masih menggunakan judul campuran Inggris–Indonesia.

---

## 2. Cakupan Audit

- **Konfigurasi Lokalisasi**: `config/app.php`, `.env`, `.env.example`, `lang/vendor/`
- **Filament Resources (9 Resource)**: `Accounts`, `DepartmentBudgets`, `Departments`, `FundRequests`, `RkaGenerateConfigs`, `RkaHeaders`, `RkaSchedules`, `RkaSettings`, `Users`
- **Custom Filament Pages (4 Page)**: `BackupManagement`, `CleanDataRkaPage`, `ManageReconciliationsPage`, `ManageUnlockRequestsPage`
- **Filament Widgets (19 Widget + 6 Blade Views)**
- **Tema & Tipografi**: `resources/css/filament/admin/theme.css`

---

## 3. Perubahan yang Dilakukan

### A. Lokalisasi Native Laravel & Filament 5
- Mengubah `APP_LOCALE=id` dan `APP_FALLBACK_LOCALE=id` di `.env` dan `.env.example`, serta default `config/app.php` menjadi `'id'`.
- Dengan aktifnya locale `id`, seluruh teks antarmuka bawaan Filament 5 dan Filament Shield (*Filters*, *Reset*, *All*, *Apply filters*, *Search*, *Summary*, *Showing results*, *Per page*, *Save changes*, *Cancel*, *Delete selected*, *No records found*, halaman Login, serta *Peran & Izin*) otomatis menggunakan Bahasa Indonesia resmi tanpa memodifikasi `vendor/`.

### B. Standardisasi Label Entitas pada Seluruh Filament Resource
Menambahkan `$modelLabel` dan `$pluralModelLabel` berbahasa Indonesia pada seluruh 9 Resource:
- `FundRequestResource` → `Pengajuan Pencairan Dana`
- `AccountResource` → `Kode Akun` / `Daftar Kode Akun (COA)`
- `DepartmentBudgetResource` → `Alokasi Pagu Unit`
- `DepartmentResource` → `Unit Kerja` / `Daftar Unit Kerja`
- `RkaGenerateConfigResource` → `Konfigurasi Generate RKA`
- `RkaHeaderResource` → `Dokumen RKA` / `Pengusulan & Review RKA`
- `RkaScheduleResource` → `Jadwal Pengusulan RKA`
- `RkaSettingResource` → `Pengaturan RKA`
- `UserResource` → `Pengguna` / `Daftar Pengguna`

### C. Standardisasi Tabel, Aksi, & Widget
- `FundRequestsTable`: `Lihat` → `Lihat Detail`, `Log Approval` → `Log Persetujuan`.
- `FinanceMonitoringTable`: `Lihat` → `Lihat Detail`.
- `DepartmentTrackingTable`: `Tracking Pengajuan Dana Terakhir` → `Pelacakan Pengajuan Dana Terakhir`, `Status Timeline` → `Status Pengajuan`, `Submitted` → `Diajukan`, `Approved` → `Disetujui`, `Rejected` → `Ditolak`, `Detail` → `Lihat Detail`.
- Menyeragamkan judul dan deskripsi Bahasa Indonesia pada seluruh widget (`DirectorFiscalYearDistributionChart`, `DirectorFinancialBreakdownMatrix`, `DirectorExecutiveApprovalQueue`, `DirectorStatsOverview`, `FinanceVerificationQueue`, `WadirMonthlyDisbursementTrendChart`, `WadirDepartmentUtilizationTable`, `WadirPendingApprovalTable`, `WadirStatsOverview`, `DepartmentTopAccounts`, `SuperAdminStatsOverview`, serta view Blade widget terkait).
- Menyeragamkan judul *Custom Page* (`BackupManagement`, `CleanDataRkaPage`, `ManageUnlockRequestsPage`).

### D. Standardisasi Tipografi
Pada `resources/css/filament/admin/theme.css`:
- Menyelaraskan skala judul halaman (`.fi-header-heading`: `text-xl sm:text-2xl font-bold`), header tabel (`.fi-ta-header-cell`: `text-[11px] font-semibold`), dan badge (`.fi-badge`: `text-[11px] font-semibold tracking-normal` tanpa paksaan uppercase berlebihan).

---

## 4. Istilah Teknis & Domain yang Sengaja Dipertahankan

Sesuai pedoman `docs/UI-INDONESIAN-TERMINOLOGY.md`, istilah berikut dipertahankan karena baku di lingkungan akademik/keuangan atau merupakan nama sistem:
- **SIMRKA / e-RKA**, **RKA**, **COA**, **BAKKU**, **KABAKKU**, **Wadir II / Wadir BAKKU**
- **Draft**, **Panjar**, **Reimburse**, **PDF**, **Backup**

---

## 5. Dokumen Referensi

- Kamus Istilah Standar: `docs/UI-INDONESIAN-TERMINOLOGY.md`
- Sistem Warna Brand: `docs/BRAND-DESIGN-SYSTEM.md`
