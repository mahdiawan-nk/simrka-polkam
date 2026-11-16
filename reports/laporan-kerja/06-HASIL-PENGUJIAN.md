# Hasil Pengujian dan Verifikasi

Dokumen ini mencatat seluruh pengujian yang benar-benar dijalankan pada codebase saat ini beserta batasannya secara objektif.

---

## 1. Pengujian Fitur Terverifikasi (25 Test — 195 Assertions Lulus 100%)

Perintah yang dijalankan:
```bash
docker compose exec app php artisan test --filter="KnowledgeCenterTest|DefaultDatabaseSeederTest|ProductionSecurityHardeningTest|RbacPageNavigationAuthorizationTest|BrandIdentityAndLoginTest"
```

| Suite Test | Jumlah Test | Hasil | Cakupan Pengujian |
|---|---:|---|---|
| `BrandIdentityAndLoginTest` | 6 | **6 Lulus** | Fallback branding, simpan & invalidasi cache, tolak SVG berbahaya, otorisasi Super Admin, upload logo/favicon, dan alur login |
| `RbacPageNavigationAuthorizationTest` | 4 | **4 Lulus** | Isolasi izin halaman `super_admin` & role lain di Shield, pencabutan izin langsung memblokir navigasi & URL (`403`), non-regresi Policy & Widget |
| `ProductionSecurityHardeningTest` | 3 | **3 Lulus** | `FilamentUser` saat `APP_ENV=production`, pencegahan IDOR lintas departemen pada `FundRequestPolicy` & `RkaHeaderPolicy`, proteksi download template COA |
| `DefaultDatabaseSeederTest` | 2 | **2 Lulus** | Penolakan jika `INITIAL_ADMIN_*` kosong, inisialisasi 27 unit, 72 COA, 6 role, permission Shield, admin awal, settings, & idempotensi re-seed |
| `KnowledgeCenterTest` | 10 | **10 Lulus** | Visibilitas per role aktif, blokir `draft`/`archived` & URL langsung, switch multi-role, pencarian, feedback, manajemen admin, langkah visual/screenshot/anotasi, serta generate & otorisasi PDF Buku Panduan |
| **Total Terfokus** | **25** | **25 Lulus (195 Assertions)** | **0 Gagal** |

---

## 2. Pengujian Build, Kualitas Kode, & Suite Lama

| Pemeriksaan | Hasil Aktual | Catatan / Batasan |
|---|---|---|
| `docker compose exec node npm run build` | **Lulus (0 Error, 0 Warning)** | Menghasilkan bundle CSS/JS production di `public/build/` |
| `docker compose exec app vendor/bin/pint --dirty --format agent` | **Lulus** | Seluruh file PHP sesuai standar kode Laravel Pint |
| `docker compose exec app php artisan test --compact` *(suite lama keseluruhan, dijalankan sebelum remediasi)* | **116 Gagal, 8 Lulus** | Kegagalan terjadi pada konfigurasi default `phpunit.xml` (`sqlite :memory:`) karena `tests/Pest.php` menonaktifkan `RefreshDatabase` serta `tests/Unit/DepartmentResolverTest.php:62` berbeda ekspektasi (`INVALID_INPUT` vs `NOT_FOUND`) |

---

## 3. Pengujian yang Belum Dilakukan

- **Uji Visual Otomatis via Browser (Chrome DevTools)**: Tidak dapat dijalankan otomatis karena *remote debugging* Chrome (`DevToolsActivePort`) tidak aktif di host pengujian.
- **Uji Restore Backup & Load Testing di Server Production**: Simulasi restore dump `.sql` dan pengujian beban bersamaan pada server production belum dijalankan.
