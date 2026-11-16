# Daftar Modul dan Fitur Aplikasi e-RKA / SIMRKA

Dokumen ini menginventarisasi seluruh modul dan fitur aktual yang ditemukan di codebase, disertai klasifikasi status implementasi secara objektif.

## Klasifikasi Status yang Digunakan
1. **Selesai dan Berfungsi**: Fitur terimplementasi lengkap dari UI hingga backend, database, dan otorisasi.
2. **Terimplementasi Sebagian**: Fungsi utama tersedia, namun memerlukan kelengkapan operasional atau integrasi lanjutan.
3. **Lingkungan Terbatas (Non-Production)**: Fitur sengaja dibatasi hanya untuk lingkungan pengembangan/pengujian.

---

## Inventaris Modul dan Fitur

| No | Modul | Fitur | Deskripsi | Role Pengguna | Status |
|---:|---|---|---|---|---|
| 1 | Autentikasi & Sesi | Halaman Masuk & Context Switcher | Login dua area modern, *remember me*, *rate limiting*, serta pergantian peran & unit kerja aktif (termasuk PLT) | Seluruh Role | Selesai dan Berfungsi |
| 2 | Dasbor Analitik | Dasbor Berbasis Peran (22 Widget) | Ringkasan pagu, realisasi, penyerapan unit, grafik tren pencairan, grafik jenis pencairan, & antrean persetujuan | Seluruh Role (sesuai izin widget) | Selesai dan Berfungsi |
| 3 | Manajemen RKA | Pengusulan & Review RKA | Pengelolaan dokumen RKA per unit (program, indikator, item belanja), pengajuan, review, revisi, dan pengesahan | Pemohon, Keuangan, Wadir II, Direktur, Staf Keuangan (baca) | Selesai dan Berfungsi |
| 4 | Manajemen RKA | Impor RKA Approved (Excel) | Mengimpor workbook Excel RKA resmi (12–13 worksheet) dengan pratinjau validasi sebelum disimpan ke database | Keuangan, Super Admin | Selesai dan Berfungsi |
| 5 | Manajemen RKA | Permintaan Buka Kunci RKA | Pengajuan dan persetujuan pembukaan kunci RKA Approved untuk impor ulang dengan batas waktu berlaku | Pemohon, Keuangan, Wadir II | Selesai dan Berfungsi |
| 6 | Manajemen RKA | Rekonsiliasi Antarunit | Monitoring, pencocokan otomatis/manual penyesuaian anggaran antarunit, dan koreksi relasi penyesuaian | Keuangan, Wadir II, Super Admin | Selesai dan Berfungsi |
| 7 | Manajemen RKA | Jadwal, Pengaturan RKA, & Generate Config | Pengaturan tahun anggaran/fase aktif, pejabat penandatangan PDF, dan template generate RKA awal | Keuangan, Super Admin | Selesai dan Berfungsi |
| 8 | Transaksi Pencairan | Pengajuan Pencairan Dana (*Fund Request*) | Pembuatan pengajuan dari item RKA Approved, validasi sisa pagu, unggah lampiran, pelacakan status, & cetak PDF | Pemohon, Keuangan, Wadir II, Direktur, Staf Keuangan (baca) | Selesai dan Berfungsi |
| 9 | Transaksi Pencairan | Verifikasi Item Keuangan | Pemeriksaan & penentuan nominal disetujui per item pengajuan dana beserta catatan penyesuaian | Keuangan, Wadir II, Direktur | Selesai dan Berfungsi |
| 10 | Manajemen Anggaran | Kode Akun (COA) & Alokasi Pagu Unit | Master 72 kode akun 3 level (Kelompok, Jenis, Rincian), impor template Excel COA, dan penetapan pagu tahunan unit | Keuangan, Super Admin | Selesai dan Berfungsi |
| 11 | Master Data | Unit Kerja / Departemen | Pengelolaan 27 unit kerja dan struktur hierarki Direktorat/Bidang/Bagian/Prodi/UPT | Super Admin, Keuangan | Selesai dan Berfungsi |
| 12 | Pengaturan Akses | Pengguna & Filament Shield (RBAC) | Manajemen pengguna, penugasan departemen, pengaturan peran & izin (Resource, Page, Widget) | Super Admin | Selesai dan Berfungsi |
| 13 | Pengaturan Sistem | Identitas Aplikasi Dinamis | Pengubahan nama brand, nama institusi, tagline, logo (terang/gelap/compact/login), judul browser, & favicon secara langsung + live preview | Super Admin | Selesai dan Berfungsi |
| 14 | Pusat Bantuan | Knowledge Center & Buku Panduan PDF | Pusat panduan interaktif berbasis peran aktif, langkah visual + screenshot + anotasi + diagram alur, serta unduh PDF per peran & panduan lengkap | Seluruh Role (baca), Super Admin (kelola & panduan lengkap) | Selesai dan Berfungsi |
| 15 | Database & Pemeliharaan | Manajemen Backup Database | Pembuatan backup manual/terpilih, riwayat backup, dan unduh file `.sql`/`.csv` secara aman | Super Admin | Terimplementasi Sebagian *(butuh worker queue aktif & uji restore)* |
| 16 | Alat Pengembang | Pembersihan Data RKA | Utilitas pratinjau dan pembersihan data RKA uji dengan frasa konfirmasi | Super Admin (non-production) | Lingkungan Terbatas *(Non-Production)* |
