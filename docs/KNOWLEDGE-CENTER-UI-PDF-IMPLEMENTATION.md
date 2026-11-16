# Implementasi Redesign Knowledge Center & Buku Panduan PDF
**Aplikasi SIMRKA / e-RKA Politeknik Kampar**

---

## 1. Perbaikan Layout & Tipografi UI

- **Root Cause Penyempitan Layout**:
  1. Class `prose prose-slate` pada `knowledge-center-page.blade.php` tidak aktif karena `@tailwindcss/typography` tidak terpasang.
  2. Wrapper `.fi-page-content` di Filament 5 memakai `grid flex-1 auto-cols-fr`, sementara kolom konten/sidebar belum memiliki `w-full min-w-0`.
- **Perbaikan yang Diterapkan**:
  - Menambahkan tipografi terstruktur `.kc-article-content` pada `resources/css/filament/admin/theme.css` menggunakan CSS variables brand (`--brand-primary`, `--brand-secondary`, `--brand-highlight`, `--border`, `--surface-muted`).
  - Memperbarui `resources/views/filament/pages/help/knowledge-center-page.blade.php` dengan `w-full min-w-0`, komposisi desktop **75% konten utama (`lg:col-span-9`) / 25% sidebar sticky (`lg:col-span-3`)**, serta panel **Daftar Isi collapsible** (Alpine.js) untuk tablet dan mobile.
  - Menambahkan tombol **Unduh Buku Panduan** pada header halaman utama, tombol **Unduh Buku Panduan Lengkap** khusus `super_admin`, dan tombol **Unduh Artikel PDF** pada halaman detail artikel.

---

## 2. Arsitektur Buku Panduan PDF

- **Engine**: `barryvdh/laravel-dompdf` (`^3.1`) — library PDF yang sudah terpasang di project.
- **Service**: `App\Services\Knowledge\KnowledgeBookPdfService`
  - `generateForCurrentRole(User $user)` → menghasilkan PDF berisi artikel `published` yang boleh dibaca oleh peran aktif pengguna (`ContextManager` + `KnowledgeCenterService`).
  - `generateCompleteHandbook(User $user)` → khusus `super_admin`, memuat seluruh artikel `published` lintas kategori dan peran.
  - `generateSingleArticle(User $user, string $slug)` → mengunduh satu artikel dalam format buku panduan PDF.
- **Template PDF**: `resources/views/pdf/knowledge-handbook.blade.php`
  - **Cover Page**: menggunakan identitas brand dinamis dari `BrandSettingsService` (logo, nama institusi, nama brand, judul dokumen, cakupan peran, versi, tanggal terbit), tanpa nomor halaman di cover.
  - **Halaman Identitas & Kata Pengantar**, **Daftar Isi**, **Isi per Bab/Kategori**, **Penutup**, serta **Running Header/Footer + Nomor Halaman Otomatis** (`Halaman X dari Y`).

---

## 3. Keamanan & Otorisasi Endpoint Download

- Controller: `App\Http\Controllers\KnowledgeBookPdfController`
- Routes (`routes/web.php`, dilindungi `Filament\Http\Middleware\Authenticate`):
  - `GET /knowledge-center/handbook/role` (`knowledge-center.handbook.role`)
  - `GET /knowledge-center/handbook/complete` (`knowledge-center.handbook.complete`) — menolak selain `super_admin` dengan `403 Forbidden`.
  - `GET /knowledge-center/handbook/article/{slug}` (`knowledge-center.handbook.article`) — memvalidasi izin peran aktif dan status `published`.
- Seluruh response PDF dikirim langsung dengan header `Cache-Control: private, no-store` dan `X-Content-Type-Options: nosniff` (tidak disimpan di lokasi publik).

---

## 4. Verifikasi & Pengujian

- `tests/Feature/KnowledgeCenterTest.php` memverifikasi:
  - Layout reader, visibilitas artikel berdasarkan peran aktif, perpindahan multi-role, blokir `draft`/`archived`, dan feedback.
  - Pembuatan PDF untuk `pemohon`, penolakan `403` untuk non-`super_admin` pada endpoint panduan lengkap, akses `super_admin` pada panduan lengkap, serta integrasi cover dinamis dari `BrandSettingsService`.
