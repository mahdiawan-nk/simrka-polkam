# Perbaikan Otorisasi Widget Filament Shield — SIMRKA Politeknik Kampar

## 1. Root Cause
1. **Global `Gate::before` Bypass (`bezhansalleh/filament-shield` v4.3.1):**
   Konfigurasi `super_admin.define_via_gate = true` pada `config/filament-shield.php` membuat `FilamentShieldServiceProvider` mendaftarkan `Gate::before(...)` yang mengembalikan `true` untuk seluruh `$ability` apabila user memiliki role `super_admin`. Akibatnya, `HasWidgetShield::canView()` (`$user->can($permission)`) selalu lolos meskipun role `super_admin` tidak memiliki permission widget di `role_has_permissions`.
2. **`DashboardContextBanner` Belum Menggunakan `HasWidgetShield`:**
   Widget `App\Filament\Widgets\DashboardContextBanner` belum memakai trait `HasWidgetShield`, dan permission `View:DashboardContextBanner` belum terdaftar di tabel `permissions`.

## 2. File yang Diperbaiki
- `config/filament-shield.php` — Mengatur `super_admin.define_via_gate => false` agar registrasi `Gate::before` dikelola secara terarah.
- `app/Providers/AppServiceProvider.php` — Mendaftarkan `Gate::before` untuk `super_admin` yang tetap meloloskan otorisasi Resource, Page, dan Policy lainnya (`return true`), namun mengembalikan `null` apabila `$ability` merupakan permission widget terdaftar di `FilamentShield::getWidgets()` sehingga diperiksa secara eksplisit melalui Spatie Permission.
- `app/Filament/Widgets/DashboardContextBanner.php` — Menambahkan trait `BezhanSalleh\FilamentShield\Traits\HasWidgetShield`.
- `database/seeders/DashboardRbacSeeder.php` — Mendaftarkan permission `View:DashboardContextBanner` serta memberikannya ke role operasional non-`super_admin` tanpa mengubah pilihan widget `super_admin`.
- `tests/Feature/Authorization/WidgetPermissionTest.php` — Pengujian otorisasi widget lengkap (10 skenario).

## 3. Penanganan Super Admin Bypass
- `super_admin` tetap memiliki akses penuh pada **Resource** (`FundRequest`, `RkaHeader`, dll.) dan **Page** (`BackupManagement`, dll.).
- Khusus **Widget**, seluruh role—termasuk `super_admin`—wajib memiliki permission `View:<WidgetClass>` yang aktif pada `role_has_permissions` atau `model_has_permissions`.

## 4. Widget & Sinkronisasi Permission
- Seluruh **22 widget** pada `app/Filament/Widgets/` kini menggunakan `HasWidgetShield` dan terikat langsung dengan tab **Widgets** di `/admin/shield/roles/{id}/edit`.
- Permission `View:DashboardContextBanner`, `View:RkaOverviewWidget`, dan `View:FinanceMonitoringTable` telah tersinkronisasi di tabel `permissions`, sementara `super_admin` tetap mempertahankan status tanpa permission widget sampai diaktifkan secara eksplisit melalui halaman edit role Shield.

## 5. Hasil Pengujian
- `tests/Feature/Authorization/WidgetPermissionTest.php`: Seluruh skenario (Super Admin tanpa permission widget = DENY, Super Admin dengan permission eksplisit = ALLOW selektif, role biasa, partial permission, pencabutan permission & flush cache, Resource & Page authorization tetap berjalan, direct gate & dashboard rendering) lulus.
- `tests/Feature/Dashboard/DashboardWidgetsAndRbacTest.php`: Seluruh test regresi dashboard & RBAC lulus.

## 6. Instruksi Pengelolaan Permission Widget ke Depan
1. Setiap widget baru pada `app/Filament/Widgets/` wajib menggunakan trait `BezhanSalleh\FilamentShield\Traits\HasWidgetShield`.
2. Atur visibilitas widget untuk setiap role (termasuk `super_admin`) melalui menu **Pengaturan Akses → Peran & Izin** (`/admin/shield/roles/{id}/edit`, tab **Widgets**).
3. Apabila menambah widget baru, jalankan `php artisan shield:generate --option=policies_and_permissions` atau tambahkan permission `View:<NamaClassWidget>` lalu bersihkan cache permission (`php artisan permission:cache-reset`).
