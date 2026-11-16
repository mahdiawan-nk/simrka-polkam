# Pemetaan Fitur & Peran Aplikasi (Feature Mapping)
**Dasar Implementasi Knowledge Center — e-RKA / SIMRKA Politeknik Kampar**

---

## 1. Stack Teknologi Aktual

- **Backend**: PHP 8.3, Laravel `^13.17`, Filament `^5.0`, Livewire
- **RBAC**: `bezhansalleh/filament-shield` `^4.3` + `spatie/laravel-permission`
- **Multi-Role & Multi-Unit Context**: `App\Services\Context\ContextManager` (`getActiveRole()`, `getActiveDepartment()`, `isPltContext()`)
- **Dokumen & Excel**: `barryvdh/laravel-dompdf` `^3.1`, `maatwebsite/excel` `^4.0`
- **Frontend**: Tailwind CSS v4 (`@tailwindcss/vite` `^4.3.3`), Vite `^8.0.0`

---

## 2. Inventarisasi Modul Aktif

| Grup Menu | Menu / Fitur | Resource / Page | Tujuan Utama |
|---|---|---|---|
| **Dasbor** | Dasbor Utama | `Dashboard` + 19 Widgets | Ringkasan pagu, realisasi, antrean verifikasi, & grafik tren sesuai role aktif |
| **Manajemen RKA** | Pengusulan & Review RKA | `RkaHeaderResource` | Penyusunan RKA unit (indikator & item), tinjauan berjenjang, ekspor/impor RKA Approved |
| **Manajemen RKA** | Jadwal Pengusulan RKA | `RkaScheduleResource` | Mengatur periode tahun anggaran & fase aktif (pengusulan/review/pelaksanaan) |
| **Manajemen RKA** | Pengaturan RKA | `RkaSettingResource` | Konfigurasi pejabat penandatangan, kop surat PDF, dan tempat tanda tangan |
| **Manajemen RKA** | Konfigurasi Generate RKA | `RkaGenerateConfigResource` | Template awal generate RKA tahunan per unit |
| **Manajemen RKA** | Permintaan Buka Kunci RKA | `ManageUnlockRequestsPage` | Pengajuan & persetujuan buka kunci RKA Approved untuk import ulang |
| **Manajemen RKA** | Rekonsiliasi Antarunit | `ManageReconciliationsPage` | Pemantauan & koreksi pencocokan adjustment RKA antarunit |
| **Transaksi** | Pengajuan Pencairan Dana | `FundRequestResource` | Pengajuan pencairan (Panjar/Reimburse/Langsung), verifikasi item, persetujuan, & cetak PDF |
| **Manajemen Anggaran** | Alokasi Pagu Unit | `DepartmentBudgetResource` | Penetapan pagu anggaran tahunan per unit kerja |
| **Manajemen Anggaran** | Kode Akun (COA) | `AccountResource` | Master data kode rekening belanja 3 level (Kelompok, Jenis, Rincian) & impor Excel |
| **Master Data** | Unit / Departemen | `DepartmentResource` | Master data unit kerja & hierarki organisasi |
| **Pengaturan Akses** | Pengguna | `UserResource` | Manajemen akun pengguna, reset password, & penugasan unit kerja |
| **Pengaturan Akses** | Peran & Izin | `RoleResource` (Shield) | Pengaturan hak akses Resource, Page, dan Widget per role |
| **Pengaturan Sistem** | Identitas Aplikasi | `BrandIdentitySettings` | Pengaturan dinamis nama brand, institusi, logo, favicon, & judul browser |
| **Database** | Manajemen Backup Database | `BackupManagement` | Backup database manual/terjadwal & unduh file `.sql` aman |
| **Alat Pengembang** | Pembersihan Data RKA | `CleanDataRkaPage` | Pembersihan data uji RKA (hanya aktif di lingkungan non-production) |

---

## 3. Matriks Role & Cakupan Panduan

| Role Aktual | Kewenangan Utama | Cakupan Panduan Knowledge Center |
|---|---|---|
| `super_admin` | Administrasi sistem, pengguna, peran & izin, identitas aplikasi, backup, alat pengembang | Manajemen pengguna & RBAC Shield, identitas brand dinamis, backup database, pemantauan kesehatan sistem |
| `direktur` | Persetujuan akhir eksekutif RKA & Pengajuan Dana, pemantauan kinerja institusi | Dasbor Direktur, persetujuan akhir RKA & Fund Request, pengembalian revisi, pemantauan penyerapan unit |
| `wadir_keuangan` | Verifikasi & persetujuan tingkat Wakil Direktur II (BAKKU) untuk RKA & Fund Request | Dasbor Wadir II, antrean persetujuan Fund Request & RKA, izin buka kunci RKA, tren pencairan bulanan |
| `keuangan` | Pengaturan jadwal/pagu/COA, review teknis RKA, verifikasi per item Fund Request, rekonsiliasi | Dasbor Keuangan, manajemen jadwal/pagu/COA, verifikasi item Fund Request, tinjauan RKA, rekonsiliasi & buka kunci RKA |
| `staf_keuangan` | **Read-Only** pemantauan seluruh unit (RKA, Fund Request, Pagu Unit) | Dasbor pemantauan Keuangan, cara melihat daftar & detail RKA/Pengajuan Dana tanpa aksi ubah/setujui |
| `pemohon` | Pengusulan RKA unit, pengajuan pencairan dana unit, pelacakan status, cetak PDF | Dasbor Unit, pengisian indikator & item RKA, pengajuan pencairan dana dari RKA Approved, pelacakan status & cetak PDF |
