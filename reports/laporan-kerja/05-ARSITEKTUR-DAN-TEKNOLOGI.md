# Arsitektur dan Teknologi Aplikasi

---

## 1. Stack Teknologi Terverifikasi

| Lapisan | Teknologi & Versi (`composer.json` / `package.json` / `Dockerfile`) |
|---|---|
| Bahasa & Runtime | PHP 8.3 (PHP-FPM + OPcache, `zip`, `intl`, `pdo_mysql`, `gd`) |
| Backend Framework | Laravel Framework `^13.17` |
| Admin Panel & UI | Filament `^5.0` (Livewire + Alpine.js) |
| RBAC & Permission | `bezhansalleh/filament-shield` `^4.3` + `spatie/laravel-permission` |
| PDF & Spreadsheet | `barryvdh/laravel-dompdf` `^3.1`, `maatwebsite/excel` `^4.0` |
| Backup Plugin | `shuvroroy/filament-spatie-laravel-backup` `^4.1` + `DatabaseBackupService` |
| Frontend Build & CSS | Vite `^8.0.0`, Tailwind CSS `^4.3.3` (`@tailwindcss/vite`), Node.js 22 Alpine |
| Database | MySQL 8.0 (`erka_db`) |
| Web Server | Nginx Alpine (`erka_webserver`) |
| Testing & Formatter | Pest `^4.7`, Laravel Pint `^1.27` |

---

## 2. Struktur Aplikasi Backend & Frontend

- **Database**: 50 file migrasi, 35 Eloquent Models (`Account`, `Department`, `DepartmentBudget`, `RkaHeader`, `RkaIndicator`, `RkaItem`, `RkaAdjustment`, `RkaAdjustmentMatch`, `RkaSchedule`, `RkaSetting`, `RkaUnlockRequest`, `FundRequest`, `FundRequestItem`, `FundRequestVerification`, `FundRequestApproval`, `BrandSetting`, `KnowledgeCategory`, `KnowledgeArticle`, `KnowledgeArticleStep`, `KnowledgeArticleFeedback`, `BackupRun`, `BackupFile`, dll.).
- **Service Layer**: 23 class service di `app/Services/` yang memisahkan logika bisnis dari Resource/Page Filament.
- **Filament UI**: 11 Filament Resources (+ `RoleResource` dari Shield), 7 Custom Pages, 22 Widgets, dan 15 Policies.
- **Sistem Desain & Tema**: `resources/css/filament/admin/theme.css` dengan token warna terpusat (*Modern Academic Finance*), terhubung ke `AdminPanelProvider` dan dikompilasi melalui Vite (`public/build/`).
- **Lokalisasi**: `APP_LOCALE=id` menggunakan paket bahasa Indonesia bawaan Laravel & Filament 5.

---

## 3. Keamanan, Storage, & Deployment

- **Autentikasi & Otorisasi**: `User` mengimplementasikan `FilamentUser`; `AppServiceProvider::boot()` menegakkan pemeriksaan eksplisit Shield untuk Page & Widget; Policy record mencegah IDOR lintas departemen.
- **Storage**: Asset publik branding & screenshot Knowledge Center disimpan di disk `public` (`storage/app/public/`), sedangkan file impor Excel sensitif dan lampiran transaksi disimpan di disk `local` (`storage/app/private/`) dan diakses melalui controller berotorisasi.
- **Deployment**: Lingkungan lokal menggunakan `docker-compose.yml` (`erka_app`, `erka_db`, `erka_webserver`, `erka_node`). Untuk lingkungan production telah disiapkan template `docker-compose.prod.yml` (termasuk `queue` & `scheduler`), `nginx/conf.d/prod.conf.example` (TLS/HTTPS & security headers), serta `.env.production.example`.
