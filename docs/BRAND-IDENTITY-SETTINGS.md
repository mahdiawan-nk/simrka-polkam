# Pengaturan Identitas Brand Dinamis & Halaman Login
**Aplikasi SIMRKA / e-RKA — Laravel + Filament 5**

---

## 1. Arsitektur & Komponen Utama

| Komponen | Lokasi | Peran |
|---|---|---|
| Migrasi & Tabel | `database/migrations/2026_11_17_010006_create_brand_settings_table.php` | Menyimpan konfigurasi identitas brand & dokumen |
| Model | `app/Models/BrandSetting.php` | Model Eloquent untuk tabel `brand_settings` |
| Service Terpusat | `app/Services/Branding/BrandSettingsService.php` | Sumber tunggal konfigurasi, cache, validasi SVG, dan fallback aman |
| Halaman Pengaturan | `app/Filament/Pages/Settings/BrandIdentitySettings.php` | UI pengaturan Super Admin + Live Preview |
| View Pengaturan | `resources/views/filament/pages/settings/brand-identity-settings.blade.php` | Form pengaturan & panel pratinjau langsung |
| Halaman Login | `app/Filament/Pages/Auth/Login.php` & `resources/views/filament/pages/auth/login.blade.php` | Halaman masuk dua area responsif (*Modern Institutional*) |
| Logo Header | `resources/views/filament/logo.blade.php` | Logo dinamis topbar/sidebar dengan dukungan light/dark mode |
| Panel Provider | `app/Providers/Filament/AdminPanelProvider.php` | Integrasi `brandName()`, `brandLogo()`, `favicon()`, dan `login()` |

---

## 2. Field yang Tersedia

### A. Identitas Brand
- `brand_name` (Nama Brand, default: `e-RKA`)
- `institution_short_name` (Singkatan Sistem/Institusi, default: `SIMRKA`)
- `app_full_name` (Nama Lengkap Aplikasi, default: `Sistem Perencanaan & Anggaran`)
- `institution_name` (Nama Institusi, default: `Politeknik Kampar`)
- `tagline` (Tagline, default: `Sistem Perencanaan dan Anggaran`)
- `short_description` (Deskripsi singkat pada panel branding halaman login)

### B. Logo & Favicon
- `logo_path` (Logo Utama — fallback seluruh varian)
- `logo_light_path` (Logo Mode Terang)
- `logo_dark_path` (Logo Mode Gelap)
- `logo_compact_path` (Logo Compact/Ikon)
- `login_logo_path` (Logo Halaman Login)
- `favicon_path` (Favicon Browser)

### C. Browser & Dokumen
- `browser_title` (Judul tab browser)
- `document_institution_name`, `document_tagline`, `document_footer_text`, `document_logo_path` (Identitas opsional dokumen cetak)

---

## 3. Penyimpanan, Keamanan Upload, & Cache

- **Lokasi Penyimpanan**: Disk `public` (`storage/app/public/branding/`), diakses browser melalui symlink `public/storage/branding/`.
- **Keamanan Upload**:
  - Logo dibatasi maksimal **2 MB** dengan format `PNG`, `JPEG`, `WebP`, atau `SVG`.
  - Favicon dibatasi maksimal **512 KB** dengan format `PNG`, `ICO`, atau `SVG`.
  - File SVG divalidasi oleh `BrandSettingsService::containsUnsafeSvgContent()` untuk menolak `<script>`, `on*=`, `javascript:`, `<foreignObject>`, `<iframe>`, `<object>`, dan `<embed>`.
  - File lama yang diganti hanya dihapus **setelah** transaksi database berhasil dan dipastikan tidak lagi dipakai oleh slot varian lain.
- **Mekanisme Cache**:
  - Konfigurasi di-cache selamanya pada key `brand_settings.active` (`Cache::rememberForever`).
  - Setiap pembaruan atau reset melalui `BrandSettingsService::updateSettings()` otomatis menginvalidasi cache sehingga perubahan langsung aktif tanpa restart atau deployment ulang.

---

## 4. Mekanisme Fallback

Jika tabel `brand_settings` kosong, file di storage terhapus, atau cache belum tersedia:
- `BrandSettingsService::getSettings()` mengembalikan nilai default institusi (`e-RKA`, `Politeknik Kampar`, `SIMRKA`).
- `BrandSettingsService::resolvePublicUrl()` mengecek keberadaan file di disk `public`; jika tidak ada, mengembalikan `null` sehingga komponen Blade menampilkan ikon SVG bawaan (tidak pernah menampilkan *broken image*).

---

## 5. Permission & Keamanan Akses

- Halaman **Pengaturan Sistem → Identitas Aplikasi** menggunakan `HasPageShield` serta pengecekan eksplisit `super_admin` di `BrandIdentitySettings::canAccess()`, `mount()`, `save()`, dan action `resetDefaults`.
- Pengguna selain `super_admin` menerima respons `403 Forbidden` jika mengakses URL halaman maupun endpoint Livewire-nya secara langsung.

---

## 6. Cara Mengganti Identitas Aplikasi

1. Login sebagai **Super Admin**.
2. Buka menu **Pengaturan Sistem → Identitas Aplikasi**.
3. Ubah nama brand, nama institusi, tagline, judul browser, atau unggah logo/favicon pada tab terkait.
4. Periksa tampilan pada panel **Pratinjau Sementara (Belum Disimpan)** di sisi kanan.
5. Klik **Simpan Perubahan** — seluruh header, halaman login, judul tab browser, dan favicon akan langsung diperbarui.
