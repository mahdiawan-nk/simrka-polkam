# Audit Otorisasi Widget Filament Shield — SIMRKA Politeknik Kampar

## 1. Lingkungan & Versi Package
- **Package:** `bezhansalleh/filament-shield` v4.3.1 (Filament 5.x, `spatie/laravel-permission`).
- **Konfigurasi:** `config/filament-shield.php`
  - `widgets.subject = 'class'`, `widgets.prefix = 'view'` (format permission: `View:<WidgetClassName>`).
  - `widgets.exclude = [AccountWidget::class, FilamentInfoWidget::class]`.
  - `super_admin.enabled = true`, `super_admin.name = 'super_admin'`, `super_admin.define_via_gate = true`, `super_admin.intercept_gate = 'before'`.

## 2. Root Cause yang Terbukti

### Root Cause 1 (Utama): Global `Gate::before` Bypass untuk `super_admin`
Pada `vendor/bezhansalleh/filament-shield/src/FilamentShieldServiceProvider.php` (baris 55–61), karena `super_admin.define_via_gate = true` dan `intercept_gate = 'before'`, Shield mendaftarkan gate global:
```php
Gate::before(fn (object $user, string $ability): ?bool => $user->hasRole(Utils::getSuperAdminName()) ? true : null);
```
Sementara itu, trait `BezhanSalleh\FilamentShield\Traits\HasWidgetShield` (`canView()`) memeriksa `$user->can($permission)` (contoh: `$user->can('View:SuperAdminStatsOverview')`).
Karena `Gate::before` selalu mengembalikan `true` untuk `super_admin` pada *semua* `$ability` sebelum Spatie Permission memeriksa tabel `role_has_permissions`, seluruh widget tetap tampil bagi `super_admin` meskipun **tidak ada satu pun** permission widget yang dicentang untuk `super_admin` di database (`role_has_permissions` hanya berisi 10 permission `View:<Model>` Resource, dan 0 permission widget).

### Root Cause 2 (Sekunder): `DashboardContextBanner` Belum Menggunakan `HasWidgetShield`
Dari 22 widget pada `app/Filament/Widgets/`:
- 21 widget telah menggunakan trait `HasWidgetShield`.
- 1 widget (`app/Filament/Widgets/DashboardContextBanner.php`) belum menggunakan `HasWidgetShield`, sehingga `canView()` mewarisi default `true` dari `Filament\Widgets\Widget` dan melewati pemeriksaan permission Shield untuk semua role.
- Permission `View:DashboardContextBanner` juga belum terdaftar di tabel `permissions`.

## 3. Dampak Terhadap Resource & Page
- `Gate::before` tetap dibutuhkan untuk Resource, Page, dan Policy lain yang bergantung pada perilaku Super Admin bypass.
- Oleh karena itu, perbaikan harus **memisahkan** otorisasi permission widget agar tidak di-bypass oleh `Gate::before`, tanpa merusak bypass `super_admin` untuk Resource dan Page.

## 4. Rekomendasi Perbaikan
1. Set `super_admin.define_via_gate => false` pada `config/filament-shield.php` agar `FilamentShieldServiceProvider` tidak mendaftarkan `Gate::before` tanpa pengecualian.
2. Daftarkan `Gate::before` terarah pada `App\Providers\AppServiceProvider::boot()` yang tetap mengembalikan `true` untuk `super_admin` pada semua ability **kecuali** ability yang merupakan permission widget terdaftar di `FilamentShield::getWidgets()` (mengembalikan `null` agar diperiksa secara eksplisit oleh Spatie Permission).
3. Tambahkan trait `HasWidgetShield` pada `DashboardContextBanner.php` dan sinkronkan permission `View:DashboardContextBanner` ke tabel `permissions` (tanpa mengubah permission widget milik `super_admin`).
