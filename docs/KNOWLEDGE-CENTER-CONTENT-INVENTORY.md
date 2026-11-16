# Inventaris Konten Knowledge Center
**Aplikasi SIMRKA / e-RKA Politeknik Kampar**

---

## 1. Daftar Artikel Awal Terverifikasi

| No | Judul Artikel | Kategori | Target Role | Modul | Status | Verifikasi Fitur |
|---:|---|---|---|---|---|---|
| 1 | Mengenal e-RKA / SIMRKA & Pengaturan Peran/Unit Aktif | Panduan Umum | Semua Peran | `umum` | Published | Terverifikasi (`ContextManager`) |
| 2 | Panduan Pemohon: Menyusun & Mengajukan RKA Unit | Panduan Unit & Pemohon | `pemohon` | `rka` | Published | Terverifikasi (`RkaHeaderResource`) |
| 3 | Panduan Pemohon: Mengajukan Pencairan Dana & Cetak PDF | Panduan Unit & Pemohon | `pemohon` | `fund_request` | Published | Terverifikasi (`FundRequestResource`) |
| 4 | Panduan Keuangan: Jadwal, Pagu Unit, & Verifikasi Pengajuan Dana | Keuangan & Verifikasi | `keuangan` | `fund_request` | Published | Terverifikasi (`VerifyFundRequest`) |
| 5 | Panduan Wadir II (BAKKU): Persetujuan Pengajuan & Penyerapan Unit | Pimpinan & Persetujuan | `wadir_keuangan` | `fund_request` | Published | Terverifikasi (`WadirPendingApprovalTable`) |
| 6 | Panduan Direktur: Persetujuan Final & Pemantauan Kinerja Anggaran | Pimpinan & Persetujuan | `direktur` | `dashboard` | Published | Terverifikasi (`DirectorStatsOverview`) |
| 7 | Panduan Staf Keuangan: Pemantauan RKA & Pengajuan Dana (Read-Only) | Pemantauan (Read-Only) | `staf_keuangan` | `dashboard` | Published | Terverifikasi (`FinanceMonitoringTable`) |
| 8 | Panduan Super Admin: Pengguna, Peran & Izin, Identitas Brand, & Backup | Administrasi Sistem | `super_admin` | `system` | Published | Terverifikasi (`BrandIdentitySettings`, `BackupManagement`) |

---

## 2. Daftar Topik Lanjutan yang Dapat Ditambahkan Bertahap

Topik berikut tersedia di aplikasi dan dapat ditambahkan sebagai artikel terpisah oleh administrator melalui **Pusat Bantuan → Kelola Artikel Panduan**:
- Panduan teknis **Import Approved RKA (Workbook Excel 12–13 Sheet)** dan format template resmi.
- Panduan teknis **Rekonsiliasi Antarunit & Penanganan Item PENDING**.
- Panduan lengkap **Import Master Data Kode Akun (COA)** melalui Excel.
