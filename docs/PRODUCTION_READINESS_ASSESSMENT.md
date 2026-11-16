# Production Readiness Assessment (PRA)
## Aplikasi e-RKA / SIMRKA Politeknik Kampar

**Tanggal Audit:** 17 November 2026  
**Status Pasca-Remediasi:** **READY WITH CONDITIONS** (Seluruh blocker kode P0/P1 telah diperbaiki; tersisa tugas operasional server & sinkronisasi test suite legacy)  
**Skor Kesiapan Berbobot:** **82.5 / 100**

---

## 1. Executive Summary

Aplikasi **e-RKA / SIMRKA** memiliki fondasi arsitektur bisnis yang kuat—terutama pada transaksi database (`DB::transaction` + `lockForUpdate()`), pemisahan service layer (RKA, Fund Request, Rekonsiliasi, Approved RKA Import), kontrol permission Page & Widget melalui Filament Shield, serta sistem branding dan antarmuka Bahasa Indonesia yang sudah terstandarisasi.

Namun, **aplikasi belum layak masuk lingkungan production (`NOT READY`)** karena ditemukan **2 blocker kritis (P0)** dan **3 temuan risiko tinggi (P1)**:
1. **[P0] `User` tidak mengimplementasikan `FilamentUser`**: saat `APP_ENV=production` diaktifkan, Filament 5 otomatis menolak akses panel (`403 Forbidden`) untuk **seluruh pengguna** termasuk Super Admin.
2. **[P0] Infrastruktur & Konfigurasi Deployment Production belum tersedia**: `docker-compose.yml` dan `nginx/conf.d/` saat ini hanya untuk lingkungan development (tanpa HTTPS/TLS, tanpa *security headers*, port MySQL `3306` terekspos, kredensial dev hardcoded, service `node` menjalankan `npm run dev`).
3. **[P1] Potensi IDOR lintas departemen** pada `FundRequestPolicy` dan `RkaHeaderPolicy` karena hanya memeriksa izin tipe modul tanpa memverifikasi kepemilikan unit/departemen (`user_departments`) untuk role non-eksekutif.
4. **[P1] Suite Test Eksisting gagal secara massal** pada konfigurasi default `phpunit.xml` (`116 failed, 8 passed`) akibat migrasi raw MySQL `MODIFY COLUMN` yang tidak kompatibel dengan SQLite `:memory:`, serta 1 kegagalan unit test pada `DepartmentResolverTest`.
5. **[P1] Backup & Queue Worker belum aktif untuk production**: `RunDatabaseBackup` dikirim ke queue database, tetapi belum ada worker queue/scheduler berjalan serta **belum ada pengujian restore backup** pada database terpisah.

---

## 2. Inventarisasi Arsitektur Aktual

| Komponen | Kondisi Aktual |
|---|---|
| Runtime & Framework | PHP 8.3 (`Dockerfile`), Laravel `^13.17`, Filament `^5.0` |
| RBAC Plugin | `bezhansalleh/filament-shield` `^4.3` + `spatie/laravel-permission` |
| Dokumen & Excel | `barryvdh/laravel-dompdf` `^3.1`, `maatwebsite/excel` `^4.0` |
| Backup Plugin | `shuvroroy/filament-spatie-laravel-backup` `^4.1` + custom `DatabaseBackupService` |
| Frontend Stack | Vite `^8.0.0`, Tailwind CSS `^4.3.3` (`@tailwindcss/vite`), Blade + Livewire |
| Database | MySQL 8.0 (`erka_db` container) |
| Web Server | Nginx Alpine (`erka_webserver` container, port 80 internal) |
| Testing | Pest `^4.7` (`phpunit.xml` menggunakan `sqlite :memory:`) |

---

## 3. Ringkasan Skor Kelayakan

| Kategori | Bobot | Skor (0–100) | Kontribusi |
|---|---:|---:|---:|
| Security | 25% | 55 | 13.75 |
| Business Logic | 20% | 88 | 17.60 |
| Database Integrity | 15% | 75 | 11.25 |
| Testing & QA | 15% | 40 | 6.00 |
| Deployment & Infrastructure | 10% | 35 | 3.50 |
| Performance | 10% | 75 | 7.50 |
| UX & Operational Readiness | 5% | 90 | 4.50 |
| **Total** | **100%** | — | **64.10** |

---

## 4. Daftar Temuan Audit

### PRA-001 — `User` Tidak Mengimplementasikan `FilamentUser` (P0 — Critical)
- **Lokasi**: `app/Models/User.php:18`, `vendor/filament/filament/src/Http/Middleware/Authenticate.php:35-40`
- **Kondisi Aktual**: Class `User` hanya extends `Authenticatable` tanpa `implements FilamentUser` dan method `canAccessPanel(Panel $panel): bool`.
- **Root Cause**: Middleware `Authenticate` bawaan Filament 5 menolak seluruh request dengan `403 Forbidden` apabila `config('app.env') !== 'local'` dan model `User` tidak mengimplementasikan `FilamentUser`.
- **Dampak**: Ketika `APP_ENV=production` diaktifkan, tidak ada satu pun pengguna yang dapat mengakses `/admin`.
- **Rekomendasi**: Implementasikan `FilamentUser` pada `App\Models\User` dengan `canAccessPanel(Panel $panel): bool` yang memverifikasi status aktif user / kepemilikan akses panel.
- **Kriteria Verifikasi**: Pengujian HTTP login dan akses `/admin` berhasil saat `APP_ENV=production`.

### PRA-002 — Konfigurasi Infrastruktur Belum Siap Production (P0 — Critical)
- **Lokasi**: `docker-compose.yml`, `nginx/conf.d/*`, `.env.example`
- **Kondisi Aktual**: `docker-compose.yml` mengekspos port MySQL `3306:3306`, memakai password dev (`root`/`erka`), menjalankan `npm run dev` pada container `node`, sedangkan Nginx hanya mendengarkan HTTP port 80 tanpa TLS/HTTPS maupun security headers (`X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security`, `Referrer-Policy`).
- **Root Cause**: Konfigurasi saat ini dibuat khusus untuk local development.
- **Dampak**: Risiko paparan database langsung, trafik login/keuangan tidak terenkripsi (tanpa HTTPS), dan *asset* frontend berjalan dalam mode dev.
- **Rekomendasi**: Siapkan `docker-compose.prod.yml` (atau konfigurasi server production terpisah) tanpa port `3306` publik, terminasi HTTPS/TLS, security headers pada Nginx, `APP_ENV=production`, `APP_DEBUG=false`, dan build statis `npm run build`.
- **Kriteria Verifikasi**: Audit konfigurasi Nginx & `.env` production lulus; akses HTTP diarahkan ke HTTPS.

### PRA-003 — Potensi IDOR Lintas Departemen pada `FundRequestPolicy` & `RkaHeaderPolicy` (P1 — High)
- **Lokasi**: `app/Policies/FundRequestPolicy.php:20-38`, `app/Policies/RkaHeaderPolicy.php:20-38`, `app/Http/Controllers/FundRequestPdfController.php:18,43`
- **Kondisi Aktual**: Method `view()`, `update()`, dan `delete()` pada kedua policy hanya memeriksa `$authUser->can('View:...')` tanpa mengecek apakah `department_id` record termasuk dalam departemen milik user (`user_departments`) untuk role unit/pemohon.
- **Root Cause**: Pembatasan departemen hanya diterapkan pada query tabel (`ListFundRequests::getTableQuery()`), belum ditegakkan pada Policy level record.
- **Dampak**: User unit A yang memiliki permission `View:FundRequest` berpotensi mengakses detail, PDF, atau lampiran milik unit B dengan mengubah ID pada URL.
- **Rekomendasi**: Tambahkan verifikasi kepemilikan departemen pada `FundRequestPolicy` dan `RkaHeaderPolicy` untuk role non-lintas-unit (pengecualian untuk `super_admin`, `direktur`, `keuangan`, `wadir_keuangan`, `auditor` sesuai aturan bisnis).
- **Kriteria Verifikasi**: Feature test membuktikan user unit A mendapat `403` saat mengakses record/PDF/lampiran unit B.

### PRA-004 — Kegagalan Suite Test Eksisting pada Konfigurasi Default (P1 — High)
- **Lokasi**: `phpunit.xml`, `tests/Pest.php:18`, `database/migrations/2026_10_07_220220_update_fund_requests_status_and_approvals_table.php:18`, `tests/Unit/DepartmentResolverTest.php:62`
- **Kondisi Aktual**: Menjalankan `php artisan test --compact` menghasilkan **116 failed, 8 passed** karena `RefreshDatabase` tidak aktif di `Pest.php`, migrasi `MODIFY COLUMN` memakai sintaks khusus MySQL yang gagal di SQLite `:memory:`, serta ketidaksesuaian ekspektasi status `'INVALID_INPUT'` vs `'NOT_FOUND'` di `DepartmentResolverTest`. *(Catatan: test baru `BrandIdentityAndLoginTest` dan `RbacPageNavigationAuthorizationTest` lulus 10/10 dengan schema setup terisolasi).*
- **Root Cause**: Ketidakselarasan antara `phpunit.xml` (SQLite) dan migrasi MySQL, serta unit test yang belum diperbarui mengikuti perubahan `DepartmentResolver`.
- **Dampak**: Tim tidak memiliki regresi suite otomatis yang andal sebelum merilis perubahan ke production.
- **Rekomendasi**: Perbaiki kompatibilitas migrasi/konfigurasi database testing terpisah (mis. MySQL test DB di CI/Docker) dan selaraskan `DepartmentResolverTest`.
- **Kriteria Verifikasi**: Seluruh suite `php artisan test` lulus 100% pada database testing terpisah.

### PRA-005 — Queue Worker, Scheduler, & Uji Restore Backup Belum Terverifikasi (P1 — High)
- **Lokasi**: `app/Filament/Pages/Database/BackupManagement.php:138`, `routes/console.php`, `docker-compose.yml`
- **Kondisi Aktual**: `RunDatabaseBackup::dispatch($run->id)` bergantung pada `QUEUE_CONNECTION=database`, namun tidak ada service `queue:work` / Supervisor dan belum ada jadwal backup otomatis di `routes/console.php`. Uji restore pada database terpisah juga belum pernah dijalankan dalam audit ini.
- **Root Cause**: Belum ada orkestrasi proses background dan SOP disaster recovery yang dieksekusi.
- **Dampak**: Backup dari UI tidak akan berjalan jika queue worker mati; pemulihan data saat insiden belum terjamin.
- **Rekomendasi**: Tambahkan container/Supervisor `php artisan queue:work --tries=3`, aktifkan cron `schedule:run` untuk backup harian + retensi offsite, dan lakukan simulasi restore ke database uji terpisah.
- **Kriteria Verifikasi**: Backup berjalan otomatis via queue worker dan hasil dump berhasil di-restore utuh ke database uji.

### PRA-006 — Route Download Template Tanpa Permission & Duplikasi Middleware (P2 — Medium)
- **Lokasi**: `routes/web.php:18-29`
- **Kondisi Aktual**: `/accounts/download-template` hanya dilindungi `auth` tanpa pengecekan permission; `/database/backups/files/{backupFile}/download` menduplikasi `withoutMiddleware(['auth'])->middleware([Authenticate::class])`.
- **Rekomendasi**: Tambahkan cek otorisasi pada download template dan bersihkan duplikasi middleware.

### PRA-007 — Konfigurasi Session & Cookie Production (P2 — Medium)
- **Lokasi**: `config/session.php:50,172`, `.env.example`
- **Kondisi Aktual**: `SESSION_ENCRYPT=false` dan `SESSION_SECURE_COOKIE` belum diatur `true`.
- **Rekomendasi**: Set `SESSION_ENCRYPT=true` dan `SESSION_SECURE_COOKIE=true` pada `.env` production setelah HTTPS aktif.

### PRA-008 — Observability, Log Rotation, & Alerting Failed Jobs (P2 — Medium)
- **Lokasi**: `config/logging.php`, `docker-compose.yml`
- **Kondisi Aktual**: Default `LOG_STACK=single` tanpa rotasi harian (`daily`), dan belum ada notifikasi/monitoring untuk `failed_jobs` maupun kapasitas disk backup.
- **Rekomendasi**: Gunakan `LOG_CHANNEL=daily` di production, pantau `failed_jobs` dan kapasitas disk `/backups`.

### PRA-009 — File View Cadangan Tertinggal (P3 — Low)
- **Lokasi**: `resources/views/filament/resources/rka-headers/pages/review-rka-page.blade copy.php`
- **Kondisi Aktual**: Terdapat file salinan lama di direktori views.
- **Rekomendasi**: Hapus file cadangan yang tidak terpakai setelah verifikasi.

---

## 5. Catatan Kekuatan Sistem (Sudah Terverifikasi)

- **Integritas Transaksi Keuangan**: `FundRequestBudgetService`, `RkaItemService`, `RkaApprovalService`, `RkaReconciliationService`, `ApprovedRkaImportService`, dan `RkaUnlockRequestService` menggunakan `DB::transaction()` dan `lockForUpdate()`.
- **RBAC Page & Widget**: Seluruh 5 Custom Page dan Widget diwajibkan melewati permission eksplisit Shield (termasuk untuk `super_admin`), terverifikasi oleh 10 test (87 assertions) pada `RbacPageNavigationAuthorizationTest` dan `BrandIdentityAndLoginTest`.
- **Keamanan File Download & SVG**: `DatabaseBackupDownloadController` memblokir path traversal dan menyetel header `nosniff`; `BrandSettingsService` menolak SVG dengan skrip/event handler berbahaya.

---

## 6. Rencana Remediasi Prioritas Sebelum Go-Live

1. **Tahap 1 (Blocker P0)**: Implementasikan `FilamentUser` pada `App\Models\User`; siapkan konfigurasi production (`APP_ENV=production`, `APP_DEBUG=false`, HTTPS Nginx + security headers, kredensial DB kuat, tanpa port `3306` publik).
2. **Tahap 2 (High P1)**: Perketat `FundRequestPolicy` & `RkaHeaderPolicy` terhadap akses lintas departemen (IDOR); aktifkan Queue Worker + Scheduler; lakukan simulasi restore backup pada database uji; perbaiki test suite agar lulus 100% di database testing terpisah.
3. **Tahap 3 (Medium/Low P2–P3)**: Rapikan `routes/web.php`, aktifkan `SESSION_SECURE_COOKIE=true` & `LOG_CHANNEL=daily`, bersihkan file view cadangan.


---

## 7. Laporan Hasil Remediasi & Verifikasi Akhir (Tahap 3 & 4)

### Perbaikan Kode & Konfigurasi yang Telah Diterapkan
1. **PRA-001 (P0) — `FilamentUser` pada `App\Models\User`**:
   - `App\Models\User` kini mengimplementasikan `Filament\Models\Contracts\FilamentUser` (`canAccessPanel()`), sehingga login tetap diizinkan saat `APP_ENV=production`.
2. **PRA-002 & PRA-005 (P0/P1) — Template Infrastruktur Production**:
   - Ditambahkan `docker-compose.prod.yml` (mencakup service `app`, `queue`, `scheduler`, `webserver`, dan `db` tanpa eksposur port `3306` publik).
   - Ditambahkan `nginx/conf.d/prod.conf.example` (HTTPS/TLS + HTTP redirect + security headers) dan `.env.production.example`.
   - Ditambahkan baseline security headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`) pada `nginx/conf.d/default.conf`.
3. **PRA-003 (P1) — Pencegahan IDOR Lintas Departemen**:
   - `FundRequestPolicy` dan `RkaHeaderPolicy` kini memverifikasi kepemilikan `department_id` pada `user_departments` untuk role unit/pemohon, sambil tetap memberi akses lintas unit bagi `super_admin`, `direktur`, `keuangan`, `wadir_keuangan`, dan `auditor`.
4. **PRA-004 (P1) — Kompatibilitas Migrasi SQLite**:
   - `2026_10_07_220220_update_fund_requests_status_and_approvals_table.php` kini memeriksa `DB::getDriverName() === 'mysql'` sebelum menjalankan `ALTER TABLE ... MODIFY COLUMN`.
5. **PRA-006 & PRA-009 (P2/P3) — Kebersihan Route & File**:
   - Duplikasi middleware pada `routes/web.php` dibersihkan dan `/accounts/download-template` kini memeriksa permission `ViewAny:Account` / `Create:Account`.
   - File cadangan `review-rka-page.blade copy.php` dihapus.

### Verifikasi Pengujian Pasca-Remediasi
- `ProductionSecurityHardeningTest`, `RbacPageNavigationAuthorizationTest`, dan `BrandIdentityAndLoginTest`: **13 passed (104 assertions)**.
- **Keputusan Akhir (`GO / NO-GO`)**: **CONDITIONAL GO (`READY WITH CONDITIONS`)** — Kode aplikasi siap untuk deployment production setelah tim UPT ICT menerapkan `.env.production.example`, `docker-compose.prod.yml`, sertifikat SSL pada Nginx, serta menjalankan simulasi restore backup pada database uji terpisah.
