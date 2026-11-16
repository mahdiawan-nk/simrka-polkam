# Referensi Bukti Implementasi

Seluruh pernyataan dalam paket laporan kerja ini merujuk langsung pada berkas kode dan dokumentasi aktual berikut:

| Klaim / Modul | Bukti Implementasi Aktual | Lokasi File |
|---|---|---|
| Stack Laravel 13, Filament 5, Shield 4.3, DomPDF, Excel, Pest | Daftar dependensi & versi paket | `composer.json`, `package.json`, `Dockerfile` |
| Konfigurasi Panel, Tema Vite, Palet Warna, Login Kustom, Brand Dinamis | Registrasi panel admin & warna hex `#17324D` | `app/Providers/Filament/AdminPanelProvider.php` |
| Sistem Desain Modern Academic Finance & Tipografi | Token CSS terpusat & `.kc-article-content` | `resources/css/filament/admin/theme.css`, `docs/BRAND-DESIGN-SYSTEM.md` |
| Standardisasi Bahasa Indonesia | Locale `id` & kamus istilah | `config/app.php`, `.env.example`, `docs/UI-INDONESIAN-TERMINOLOGY.md` |
| Otorisasi Panel Production (`FilamentUser`) | Implementasi kontrak `FilamentUser` | `app/Models/User.php` |
| Otorisasi Shield Page & Widget + Anti-Bypass `super_admin` | `Gate::before` & `HasPageShield` | `app/Providers/AppServiceProvider.php`, `config/filament-shield.php`, `app/Filament/Pages/**/*` |
| Pencegahan IDOR Lintas Unit | Verifikasi `user_departments` pada Policy | `app/Policies/FundRequestPolicy.php`, `app/Policies/RkaHeaderPolicy.php` |
| Workflow Perencanaan, Review, Impor Approved, Buka Kunci, & Rekonsiliasi RKA | Service & Action transaksi + `lockForUpdate` | `app/Services/RkaApprovalService.php`, `RkaItemService.php`, `RkaReviewService.php`, `ApprovedRkaImportService.php`, `RkaUnlockRequestService.php`, `RkaReconciliationService.php` |
| Workflow Fund Request & Cetak PDF | Perhitungan pagu terkunci & controller PDF | `app/Services/FundRequest/FundRequestBudgetService.php`, `FundRequestDetailService.php`, `app/Http/Controllers/FundRequestPdfController.php`, `resources/views/pdf/fund-request.blade.php` |
| Pengaturan Identitas Brand Dinamis & Halaman Login | Service cache, sanitasi SVG, halaman settings, & login 2 area | `app/Services/Branding/BrandSettingsService.php`, `app/Filament/Pages/Settings/BrandIdentitySettings.php`, `app/Filament/Pages/Auth/Login.php` |
| Knowledge Center (Web + Langkah Visual + Buku Panduan PDF) | Models, Service, Page, Resources, Controller, & Template PDF | `app/Models/KnowledgeArticle.php`, `KnowledgeArticleStep.php`, `app/Services/Knowledge/KnowledgeCenterService.php`, `KnowledgeBookPdfService.php`, `app/Http/Controllers/KnowledgeBookPdfController.php`, `resources/views/pdf/knowledge-handbook.blade.php` |
| Default Database Seeder Idempotent | 27 Unit, 72 COA, 6 Role, Shield Permissions, Admin dari `.env` | `database/seeders/DatabaseSeeder.php`, `Departmentseeder.php`, `AccountDataSeeder.php`, `RoleSeeder.php`, `PermissionSeeder.php`, `InitialAdminSeeder.php`, `ApplicationSettingsSeeder.php`, `KnowledgeCenterSeeder.php` |
| Template Deployment Production | Konfigurasi container, queue, scheduler, Nginx TLS, & env | `docker-compose.prod.yml`, `nginx/conf.d/prod.conf.example`, `.env.production.example`, `docs/PRODUCTION_GO_LIVE_CHECKLIST.md` |
| Pengujian Otomatis Terverifikasi (25 Lulus) | 5 file Feature Test terisolasi | `tests/Feature/BrandIdentityAndLoginTest.php`, `RbacPageNavigationAuthorizationTest.php`, `ProductionSecurityHardeningTest.php`, `DefaultDatabaseSeederTest.php`, `KnowledgeCenterTest.php` |
