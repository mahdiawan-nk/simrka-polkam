# LAPORAN PENGEMBANGAN APLIKASI e-RKA / SIMRKA
**Sistem Informasi Perencanaan, Penganggaran, dan Pencairan Dana Institusi**

---

## 1. Halaman Judul

| Atribut | Keterangan |
|---|---|
| **Judul Dokumen** | Laporan Kerja Pengembangan Aplikasi e-RKA / SIMRKA |
| **Institusi** | Politeknik Kampar |
| **Nama Aplikasi** | e-RKA / SIMRKA (*Sistem Perencanaan dan Anggaran*) |
| **Periode Pengembangan Teraudit** | Oktober – November 2026 (Berdasarkan histori migrasi dan kode aktual per 17 November 2026) |
| **Tanggal Penyusunan** | 17 November 2026 |
| **Sumber Data** | Audit langsung terhadap codebase, konfigurasi, skema database, dan hasil pengujian aktual |

---

## 2. Ringkasan Eksekutif

Aplikasi **e-RKA / SIMRKA Politeknik Kampar** telah dikembangkan sebagai platform web terpadu untuk mengelola siklus anggaran institusi secara digital—mulai dari penetapan pagu unit, penyusunan Rencana Kerja dan Anggaran (RKA), peninjauan dan persetujuan berjenjang, impor/ekspor workbook RKA resmi, rekonsiliasi penyesuaian antarunit, hingga pengajuan dan verifikasi pencairan dana (*Fund Request*) beserta cetak dokumen PDF.

Selain modul inti keuangan, aplikasi juga dilengkapi sistem kontrol akses berbasis peran (**Filament Shield**), tata letak berbahasa Indonesia profesional dengan identitas visual **Modern Academic Finance**, halaman masuk (*login*) dua area, pengaturan identitas brand dinamis tanpa mengubah kode, manajemen backup database, serta **Knowledge Center (Pusat Panduan Visual)** yang dilengkapi fitur unduh buku panduan PDF sesuai peran pengguna.

Berdasarkan hasil audit kode dan pengujian otomatis pada lingkungan pengujian, **fungsi inti aplikasi telah berjalan baik**, namun kesiapan rilis ke server *production* berada pada status **`READY WITH CONDITIONS` (Siap dengan Syarat)** karena masih terdapat pekerjaan operasional server (pengaktifan SSL/HTTPS, *queue worker*, *scheduler*, uji *restore* backup pada database terpisah) serta penyesuaian sebagian *test suite* lama terhadap konfigurasi database pengujian.

---

## 3. Latar Belakang

Pengelolaan perencanaan anggaran (RKA) dan realisasi pencairan dana pada institusi pendidikan tinggi melibatkan banyak unit kerja (prodi, UPT, bagian, dan badan), kode akun belanja bertingkat, serta tahapan verifikasi dan persetujuan berjenjang (Unit Kerja → Keuangan → Wakil Direktur II → Direktur).

Untuk mendukung ketertiban administrasi, mencegah pengajuan melebihi sisa pagu, dan menyediakan jejak audit yang dapat ditelusuri, dikembangkan sistem e-RKA / SIMRKA berbasis web yang menyatukan seluruh proses tersebut dalam satu panel administrasi terpadu.

---

## 4. Maksud dan Tujuan

Pengembangan aplikasi ini bertujuan untuk:
1. **Digitalisasi Perencanaan Anggaran**: Menyediakan penyusunan RKA terstruktur per unit kerja lengkap dengan indikator kinerja, rincian belanja, dan jadwal pengusulan.
2. **Standardisasi Proses Pengusulan & Pencairan**: Menyeragamkan alur pengajuan pencairan dana berbasis item RKA yang telah disetujui (*RKA Approved*).
3. **Pengendalian Pagu & Realisasi**: Mencegah pengajuan melebihi pagu unit maupun sisa anggaran per item melalui validasi sistem dan penguncian transaksi database.
4. **Pengendalian Persetujuan Berjenjang**: Mengatur kewenangan pemeriksaan dan persetujuan sesuai peran masing-masing pejabat/unit.
5. **Transparansi & Pemantauan**: Menyediakan dasbor analitik dan tabel pemantauan sesuai peran pengguna.
6. **Dokumentasi Aktivitas & Kemudahan Penggunaan**: Mencatat riwayat persetujuan/aktivitas serta menyediakan Pusat Panduan Visual beserta buku panduan PDF bagi setiap peran.

---

## 5. Ruang Lingkup Pekerjaan

Ruang lingkup implementasi yang ditemukan dalam codebase meliputi:
- **Modul Perencanaan & Penganggaran RKA**: Jadwal, Pengaturan RKA, Konfigurasi Generate, Pengusulan & Review RKA, Impor Workbook RKA Approved (12–13 worksheet), Permintaan Buka Kunci RKA, dan Rekonsiliasi Antarunit.
- **Modul Pengajuan Pencairan Dana (*Fund Request*)**: Pembuatan pengajuan dari RKA Approved, verifikasi per item oleh Keuangan, persetujuan berjenjang, dan cetak formulir PDF.
- **Modul Manajemen Anggaran & Master Data**: Pengelolaan 72 Kode Akun (COA) 3 tingkat, 27 Unit Kerja/Departemen, dan Alokasi Pagu Unit.
- **Dasbor Analitik Berbasis Peran**: 22 widget KPI, grafik, dan antrean kerja yang disesuaikan per peran.
- **Administrasi Sistem, Keamanan, & Tata Kelola**: Manajemen Pengguna, Filament Shield (RBAC), Identitas Aplikasi Dinamis, Manajemen Backup Database, serta Knowledge Center & Buku Panduan PDF.

---

## 6. Teknologi dan Arsitektur

Aplikasi dibangun menggunakan teknologi modern yang terstandarisasi dan berjalan di dalam kontainer Docker:
- **Mesin Aplikasi**: PHP 8.3 dengan framework **Laravel 13** (`^13.17`) dan panel administrasi **Filament 5** (`^5.0`).
- **Keamanan Hak Akses**: **Filament Shield** (`^4.3`) dan *Spatie Laravel Permission*.
- **Antarmuka**: **Livewire**, **Tailwind CSS v4**, dan **Vite 8** dengan desain *Modern Academic Finance* serta dukungan penuh Bahasa Indonesia.
- **Pengolahan Dokumen**: **DomPDF** untuk cetak formulir pencairan dana & buku panduan PDF, serta **Laravel Excel** untuk impor/ekspor data RKA dan COA.
- **Basis Data & Infrastruktur**: **MySQL 8.0** dan **Nginx** dalam kontainer Docker (`erka_app`, `erka_db`, `erka_webserver`, `erka_node`).

---

## 7. Hasil Pengembangan

Secara fungsional, seluruh modul utama telah diimplementasikan dengan alur transaksi database yang aman (`DB::transaction` dan `lockForUpdate` untuk mencegah konflik data bersamaan):
1. **Perencanaan & Review RKA** berjalan lengkap dari tahap *Draft*, *Submitted*, *In Review*, *Revision Required*, hingga *Approved* dan sinkronisasi ke pagu unit, termasuk impor RKA Approved dan rekonsiliasi antarunit.
2. **Pengajuan Pencairan Dana** terhubung langsung ke item RKA Approved, memeriksa sisa anggaran tersedia secara otomatis, mendukung verifikasi per item oleh Keuangan, serta menghasilkan PDF resmi.
3. **Kontrol Akses (RBAC)** telah diperbaiki agar seluruh halaman kustom, resource, widget, dan akses record antarunit terlindungi di sisi server.
4. **Identitas Brand & Halaman Login** telah diperbarui agar profesional dan dapat disesuaikan langsung oleh Super Admin melalui menu *Pengaturan Sistem → Identitas Aplikasi*.
5. **Knowledge Center** telah tersedia sebagai pusat panduan interaktif berbasis peran beserta fitur unduh buku panduan PDF (per peran dan panduan lengkap khusus Super Admin).

---

## 8. Pengelolaan Hak Akses

Sistem mengelola **6 peran aktual** (`super_admin`, `direktur`, `wadir_keuangan`, `keuangan`, `staf_keuangan`, dan `pemohon`) beserta mekanisme perpindahan peran/unit aktif (*Context Switcher*) bagi pengguna yang memiliki penugasan ganda atau PLT.

Pengaturan izin melalui **Peran & Izin (Filament Shield)** kini mengontrol secara langsung baik visibilitas menu di sidebar maupun proteksi akses URL langsung di server untuk seluruh Resource, Halaman Kustom, dan Widget. Selain itu, kebijakan akses record (`FundRequestPolicy` dan `RkaHeaderPolicy`) membatasi pengguna unit agar tidak dapat mengakses dokumen milik unit lain.

---

## 9. Pengujian dan Verifikasi

Pengujian otomatis menggunakan **Pest** mencatat hasil sebagai berikut:
- **25 test case baru/terfokus (195 assertions) LULUS 100%**, mencakup: keamanan panel production & pencegahan IDOR lintas unit, otorisasi halaman & navigasi Shield, idempotensi seeder database awal, identitas brand & halaman login, serta Knowledge Center & pembuatan buku panduan PDF.
- **Catatan Pengujian Suite Lama**: Ketika seluruh suite lama dijalankan dengan konfigurasi default SQLite (`phpunit.xml`), **116 test lama gagal** terutama karena sebagian test lama belum mengaktifkan `RefreshDatabase` atau bergantung pada skema database uji MySQL. Hal ini telah dicatat secara transparan pada [06-HASIL-PENGUJIAN.md](./06-HASIL-PENGUJIAN.md).

---

## 10. Kondisi Aplikasi Saat Ini

| Klasifikasi | Ringkasan |
|---|---|
| **Selesai & Berfungsi** | Modul RKA, Fund Request & PDF, Manajemen COA/Unit/Pagu, RBAC Shield (Resource/Page/Widget), Identitas Brand Dinamis, Halaman Login, Knowledge Center + Visual Steps + Buku Panduan PDF, Default Database Seeder |
| **Terimplementasi Sebagian / Memerlukan Penguatan Operasional** | Manajemen Backup Database (UI & job tersedia, namun worker queue, scheduler cron, dan uji *restore* pada server production belum aktif/teruji), serta sinkronisasi test suite lama ke database uji MySQL |
| **Lingkungan Khusus Non-Production** | Alat Pengembang (*Pembersihan Data RKA*) — sengaja dibatasi hanya aktif di luar environment `production` |

---

## 11. Kendala dan Temuan

Temuan teknis utama yang perlu diperhatikan sebelum operasional penuh (*go-live*):
1. **Operasional Server Production (Prioritas Tinggi)**: *Queue worker* (`queue:work`), *scheduler* (`schedule:run`), sertifikat SSL/HTTPS pada Nginx, dan uji coba *restore* backup database pada database uji terpisah harus diaktifkan di server production. *(Template konfigurasi `docker-compose.prod.yml`, `nginx/conf.d/prod.conf.example`, dan `.env.production.example` telah disiapkan).*
2. **Konsistensi Test Suite Lama (Prioritas Tinggi)**: Sebagian file test lama perlu diselaraskan agar seluruh regresi test dapat berjalan penuh pada database pengujian MySQL terpisah.
3. **Pengisian Screenshot Aktual Knowledge Center (Prioritas Sedang)**: Mesin panduan visual & PDF sudah siap dan teruji; pengisian screenshot tangkapan layar aktual per modul dapat dilakukan bertahap oleh tim UPT ICT sesuai prosedur terdokumentasi.

---

## 12. Rekomendasi dan Rencana Tindak Lanjut

- **Perbaikan Operasional Segera (Sebelum Go-Live)**: Terapkan `.env.production.example`, `docker-compose.prod.yml`, HTTPS Nginx, jalankan `db:seed` inisialisasi awal, dan lakukan satu kali simulasi *restore* backup database.
- **Penyempurnaan Kualitas**: Selaraskan konfigurasi database pengujian (`.env.testing` MySQL) agar seluruh 37 file test dapat berjalan tanpa error skema SQLite.
- **Pengembangan Lanjutan**: Lengkapi tangkapan layar aktual pada artikel Knowledge Center sesuai prioritas modul operasional (RKA, Verifikasi Keuangan, dan Pencairan Dana).

---

## 13. Penutup

Demikian Laporan Kerja Pengembangan Aplikasi **e-RKA / SIMRKA Politeknik Kampar** ini disusun secara objektif berdasarkan kondisi aktual codebase per 17 November 2026 sebagai bahan evaluasi dan pengambilan keputusan pimpinan institusi.

---

## 14. Lampiran

Rincian teknis lengkap tersedia pada dokumen pendamping dalam folder `reports/laporan-kerja/`:
- [02-DAFTAR-MODUL-DAN-FITUR.md](./02-DAFTAR-MODUL-DAN-FITUR.md)
- [03-ROLE-DAN-HAK-AKSES.md](./03-ROLE-DAN-HAK-AKSES.md)
- [04-WORKFLOW-BISNIS.md](./04-WORKFLOW-BISNIS.md)
- [05-ARSITEKTUR-DAN-TEKNOLOGI.md](./05-ARSITEKTUR-DAN-TEKNOLOGI.md)
- [06-HASIL-PENGUJIAN.md](./06-HASIL-PENGUJIAN.md)
- [07-TEMUAN-DAN-REKOMENDASI.md](./07-TEMUAN-DAN-REKOMENDASI.md)
- [08-REFERENSI-BUKTI-IMPLEMENTASI.md](./08-REFERENSI-BUKTI-IMPLEMENTASI.md)
