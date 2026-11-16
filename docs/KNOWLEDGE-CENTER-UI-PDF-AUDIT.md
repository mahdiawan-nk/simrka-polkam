# Audit UI Knowledge Center & Arsitektur Buku Panduan PDF
**Aplikasi SIMRKA / e-RKA Politeknik Kampar**

---

## 1. Temuan Masalah & Root Cause

### A. Layout & Tipografi Halaman Knowledge Center
1. **Tidak terpasangnya `@tailwindcss/typography`**:
   - View `resources/views/filament/pages/help/knowledge-center-page.blade.php` menggunakan class `prose prose-slate`, padahal `package.json` hanya memasang `tailwindcss` (`^4.3.3`) dan `@tailwindcss/vite` tanpa plugin `@tailwindcss/typography`. Akibatnya, elemen `<h2>`, `<h3>`, `<p>`, `<ul>`, `<ol>`, `<table>`, `<blockquote>`, dan `<code>` di dalam konten artikel tidak memiliki gaya tipografi maupun jarak yang memadai.
2. **Grid Sizing & Overflow (`min-w-0`)**:
   - Wrapper `.fi-page-content` di Filament 5 menggunakan `grid flex-1 auto-cols-fr`. Tanpa `w-full min-w-0` pada container artikel dan `min-w-0` pada kolom grid (`article` & `aside`), elemen lebar (tabel/kode/URL panjang) dapat mendistorsi proporsi kolom.
   - Proporsi sebelumnya (`lg:col-span-8` / `lg:col-span-4` = 66% / 33%) belum optimal untuk kenyamanan baca (ideal 70–75% konten utama & 25–30% sidebar daftar isi sticky pada desktop, serta panel buka/tutup pada tablet/mobile).
3. **Navigasi & Pengalaman Pembaca**:
   - Belum ada tombol unduh **Buku Panduan PDF** sesuai peran aktif maupun **Unduh Buku Panduan Lengkap** khusus Super Admin.
   - Belum ada tombol *Daftar Isi* yang dapat dibuka/tutup (*collapsible*) pada layar tablet/mobile.

---

## 2. Library PDF & Arsitektur yang Tersedia

- **Library PDF Terpasang**: `barryvdh/laravel-dompdf` (`^3.1`) sudah terpasang dan digunakan oleh `FundRequestPdfController` (`Pdf::loadView(...)->setPaper('a4', 'portrait')`). Tidak perlu memasang library PDF baru.
- **Identitas Brand Dinamis**: `App\Services\Branding\BrandSettingsService` sudah tersedia untuk menyediakan nama brand, nama institusi, tagline, logo (`public` disk), dan metadata dokumen tanpa hardcoding.
- **Kontrol Akses**: `App\Services\Knowledge\KnowledgeCenterService` + `KnowledgeCenterPage` (`HasPageShield` + `ContextManager`).

---

## 3. Rekomendasi Arsitektur Implementasi

1. **Perbaikan UI (`knowledge-center-page.blade.php` & `resources/css/filament/admin/theme.css`)**:
   - Tambahkan class khusus `.kc-article-content` di `theme.css` menggunakan CSS variables brand (`--brand-primary`, `--brand-secondary`, `--border`, `--surface-muted`, `--text-primary`, `--text-secondary`) untuk menata `h2`, `h3`, `p`, `ul`, `ol`, `table` (responsive scroll wrapper), `blockquote`, `pre`/`code`, `img`, dan `a` tanpa menambah package baru.
   - Gunakan proporsi desktop `lg:grid-cols-12` dengan `lg:col-span-9` (75% konten utama, `min-w-0`) dan `lg:col-span-3` (25% sticky TOC + panduan terkait, `min-w-0`), serta Alpine.js `x-data` collapsible TOC untuk tablet/mobile.
2. **Service PDF Terpisah (`App\Services\Knowledge\KnowledgeBookPdfService`)**:
   - `generateForCurrentRole(User $user)`: mengambil hanya artikel `published` yang boleh dibaca oleh peran aktif pengguna (`ContextManager` / `KnowledgeCenterService`), dikelompokkan per kategori dan diurutkan berdasarkan `sort_order`.
   - `generateCompleteHandbook(User $user)`: khusus pengguna yang memiliki peran `super_admin` dan permission `View:KnowledgeCenterPage` (plus verifikasi backend `403`), memuat seluruh artikel `published` lintas kategori dan peran.
   - Menggunakan DomPDF `page_script` / CSS `@page` untuk header, footer, nomor halaman (`Halaman X dari Y`), cover terpisah tanpa nomor halaman, serta daftar isi dengan penomoran bagian/bab.
3. **Endpoint Download Aman (`KnowledgeBookPdfController`)**:
   - Route `GET /knowledge-center/handbook/role` dan `GET /knowledge-center/handbook/complete` dengan middleware `Authenticate::class` dan verifikasi ulang permission + role aktif di backend setiap request (tidak disimpan di lokasi publik dan tidak di-cache lintas role).
