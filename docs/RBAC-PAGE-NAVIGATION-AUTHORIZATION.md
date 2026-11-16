# RBAC Filament Shield — Otorisasi Halaman & Visibilitas Navigasi Sidebar
**Aplikasi SIMRKA / e-RKA — Filament 5 + Filament Shield 4.x**

---

## 1. Root Cause Analysis

Setelah audit menyeluruh terhadap konfigurasi Shield, `AppServiceProvider`, dan seluruh Custom Page di `app/Filament/Pages/`, ditemukan dua penyebab utama mengapa seluruh menu halaman tetap tampil meskipun permission halaman tidak dicentang untuk role `super_admin`:

1. **`Gate::before()` di `app/Providers/AppServiceProvider.php` melewati pemeriksaan permission Page untuk `super_admin`**
   - Sebelumnya `AppServiceProvider::boot()` hanya mengecualikan permission **Widget** (`isShieldWidgetPermission`) dari bypass `Gate::before()`, sehingga seluruh permission **Page** (`View:BackupManagement`, `View:BrandIdentitySettings`, `View:CleanDataRkaPage`, `View:ManageReconciliationsPage`, `View:ManageUnlockRequestsPage`) otomatis mengembalikan `true` bagi `super_admin`.
2. **Inkonsistensi implementasi `HasPageShield` pada Custom Pages**
   - `BrandIdentitySettings`: memakai `HasPageShield`, tetapi menimpa `canAccess()` dengan pengecekan hardcoded `hasRole('super_admin')`.
   - `CleanDataRkaPage`, `ManageReconciliationsPage`, dan `ManageUnlockRequestsPage`: belum menggunakan trait `HasPageShield` dari Filament Shield.

---

## 2. Konfigurasi Shield & Konvensi Penamaan Permission

- **Konfigurasi**: `config/filament-shield.php` menggunakan `'super_admin' => ['define_via_gate' => false]` karena kontrol bypass `super_admin` dikelola secara terukur di `AppServiceProvider::boot()`.
- **Konvensi Nama Permission Page** (PascalCase dengan pemisah `:` sesuai `config/filament-shield.php`):
  - `View:BackupManagement`
  - `View:BrandIdentitySettings`
  - `View:CleanDataRkaPage`
  - `View:ManageReconciliationsPage`
  - `View:ManageUnlockRequestsPage`

Seluruh 5 permission tersebut sudah terdaftar di tabel `permissions` tanpa duplikasi.

---

## 3. Implementasi Page Authorization & Navigation Visibility

1. **`app/Providers/AppServiceProvider.php`**
   - Mengganti `isShieldWidgetPermission()` menjadi `isExplicitlyEnforcedShieldPermission()` yang memeriksa baik `FilamentShield::getWidgets()` maupun `FilamentShield::getPages()`.
   - Ketika `Gate::before()` menerima ability milik Widget atau Page, callback mengembalikan `null` sehingga Laravel/Spatie Permission memeriksa permission eksplisit yang diberikan di Shield.
   - Kemampuan Policy/Resource lainnya tetap berjalan normal tanpa regresi.

2. **Seluruh Custom Page menggunakan `HasPageShield`**
   - `BackupManagement`: `use HasPageShield;`
   - `BrandIdentitySettings`: `use HasPageShield;` (pengecekan hardcoded `hasRole('super_admin')` dihapus agar sepenuhnya mengikuti `View:BrandIdentitySettings`)
   - `CleanDataRkaPage`: `use HasPageShield;` dikombinasikan dengan guard lingkungan `RkaDataCleanupService::isAvailable()` agar tetap aman dari eksekusi di production
   - `ManageReconciliationsPage`: `use HasPageShield;`
   - `ManageUnlockRequestsPage`: `use HasPageShield;`

3. **Visibilitas Sidebar & Proteksi URL Langsung**
   - `HasPageShield::shouldRegisterNavigation()` mengembalikan `static::canAccess() && parent::shouldRegisterNavigation()`, sehingga halaman tanpa permission otomatis hilang dari sidebar.
   - `Filament\Pages\Page` memanggil `authorizeAccess()` saat halaman diakses via URL langsung maupun Livewire request dan mengembalikan `403 Forbidden` jika `canAccess()` bernilai `false`.
   - Navigation Group yang seluruh item di dalamnya tersembunyi otomatis tidak dirender oleh Filament 5.

---

## 4. Invalidasi Cache Permission

- Spatie Permission otomatis membersihkan cache `spatie.permission.cache` setiap kali role/permission disimpan melalui halaman **Peran & Izin** (`RoleResource`) atau method `syncPermissions()`, `givePermissionTo()`, dan `revokePermissionTo()`.
- Navigasi Filament dihitung dinamis per request berdasarkan `shouldRegisterNavigation()`, sehingga perubahan permission langsung efektif tanpa perlu menjalankan `optimize:clear` secara manual.

---

## 5. Hasil Testing

Pengujian otomatis pada `tests/Feature/RbacPageNavigationAuthorizationTest.php` dan `tests/Feature/BrandIdentityAndLoginTest.php`:
- **10 passed (87 assertions)** — mencakup:
  - Skenario A & B: `super_admin` hanya melihat dan mengakses halaman yang dicentang di Shield.
  - Skenario C: pencabutan permission langsung menyembunyikan menu dan memblokir akses URL (`403`).
  - Skenario D: role lain (`keuangan`) hanya melihat halaman yang diizinkan untuknya.
  - Skenario E: Resource policy `super_admin` tetap berjalan dan Widget permission tetap terproteksi Shield.

---

## 6. Panduan Menambahkan Custom Page Baru

1. Buat class Page yang extends `Filament\Pages\Page`.
2. Tambahkan `use BezhanSalleh\FilamentShield\Traits\HasPageShield;`.
3. Jangan menimpa `canAccess()` dengan pengecekan role hardcoded (`hasRole(...)`) kecuali ada batasan lingkungan khusus (seperti `CleanDataRkaPage`).
4. Jalankan `docker compose exec app php artisan shield:generate --all --panel=admin` untuk mendaftarkan permission `View:<NamaPage>` ke Shield, lalu atur hak aksesnya melalui menu **Peran & Izin**.
