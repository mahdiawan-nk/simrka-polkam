# Production Go-Live Checklist — e-RKA / SIMRKA Politeknik Kampar
**Untuk Tim UPT ICT & Pengelola Sistem**

> **Status Saat Ini:** `NOT READY` — Seluruh item `[P0]` dan `[P1]` di bawah ini wajib diselesaikan dan diverifikasi sebelum sistem dibuka ke pengguna institusi.

---

## A. Blocker Kritis (P0 — Wajib Sebelum Go-Live)

- [ ] **[P0] Implementasi `FilamentUser` pada `App\Models\User`**
  - Pastikan `User` mengimplementasikan `Filament\Models\Contracts\FilamentUser` dengan method `canAccessPanel(Panel $panel): bool` agar login tidak ditolak (`403`) saat `APP_ENV=production`.
- [ ] **[P0] Environment Production (`.env`)**
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://<domain-resmi-institusi>`
  - `APP_LOCALE=id` dan `APP_FALLBACK_LOCALE=id`
  - `APP_KEY` unik & aman (tidak menggunakan key development).
  - Password database & `MYSQL_ROOT_PASSWORD` menggunakan secret kuat.
- [ ] **[P0] Web Server, HTTPS, & Keamanan Jaringan**
  - Aktifkan sertifikat SSL/TLS (HTTPS) dan redirect HTTP → HTTPS.
  - Tambahkan security headers di Nginx (`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security`).
  - Tutup port `3306` (MySQL) dan `5173` (Vite dev) dari akses publik/firewall luar.
  - Pastikan container `node` tidak menjalankan `npm run dev` di production; gunakan hasil build statis `npm run build` di `public/build/`.

---

## B. Perbaikan Risiko Tinggi (P1 — Wajib Diverifikasi)

- [ ] **[P1] Pencegahan IDOR Lintas Departemen**
  - Perketat `FundRequestPolicy` dan `RkaHeaderPolicy` untuk memvalidasi kepemilikan `department_id` terhadap `user_departments` bagi role unit/pemohon.
  - Uji akses silang (unit A mencoba membuka URL detail, PDF, dan lampiran milik unit B) → harus mengembalikan `403 Forbidden`.
- [ ] **[P1] Queue Worker & Scheduler**
  - Jalankan proses background `php artisan queue:work --sleep=3 --tries=3` melalui Supervisor atau service Docker khusus worker.
  - Jalankan cron `* * * * * php /var/www/html/artisan schedule:run` untuk tugas terjadwal dan backup otomatis.
- [ ] **[P1] Backup & Uji Restore Database**
  - Konfigurasikan jadwal backup otomatis harian + retensi + salinan offsite.
  - Lakukan **simulasi restore** file `.sql` hasil backup ke database uji terpisah dan verifikasi integritas tabel serta saldo anggaran.
- [ ] **[P1] Test Suite pada Database Testing Terpisah**
  - Perbaiki konfigurasi database testing (gunakan MySQL test DB untuk kompatibilitas migrasi `MODIFY COLUMN`) dan selaraskan `DepartmentResolverTest` hingga seluruh test lulus 100%.

---

## C. Penguatan Operasional (P2–P3)

- [ ] **[P2] Session & Logging**
  - Set `SESSION_ENCRYPT=true` dan `SESSION_SECURE_COOKIE=true`.
  - Set `LOG_CHANNEL=daily` agar log aplikasi terotasi dan tidak memenuhi disk.
  - Siapkan pemantauan tabel `failed_jobs` dan kapasitas disk `/backups`.
- [ ] **[P2] Perapihan Route**
  - Tambahkan cek permission pada `/accounts/download-template` dan hapus duplikasi middleware di `routes/web.php`.
- [ ] **[P3] Kebersihan Kode**
  - Hapus `resources/views/filament/resources/rka-headers/pages/review-rka-page.blade copy.php`.

---

## D. Langkah Deployment & Verifikasi Pasca-Rilis

1. Jalankan backup penuh database sebelum migrasi:
   ```bash
   docker compose exec db mysqldump -u <user> -p <db> > pre_deploy_backup.sql
   ```
2. Aktifkan mode maintenance saat migrasi:
   ```bash
   docker compose exec app php artisan down
   docker compose exec app php artisan migrate --force
   docker compose exec node npm run build
   docker compose exec app php artisan optimize
   docker compose exec app php artisan filament:optimize
   docker compose exec app php artisan queue:restart
   docker compose exec app php artisan up
   ```
3. Verifikasi manual pasca-deploy:
   - Login dengan `super_admin`, `direktur`, `keuangan`, `wadir_keuangan`, dan `pemohon`.
   - Verifikasi sidebar navigation sesuai permission Shield masing-masing role.
   - Verifikasi cetak PDF Fund Request dan akses lampiran.
   - Verifikasi logo/brand dan favicon tampil normal dari `public/storage/branding/`.
