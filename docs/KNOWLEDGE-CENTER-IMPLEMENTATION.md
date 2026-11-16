# Implementasi Knowledge Center (Pusat Panduan e-RKA)
**Aplikasi SIMRKA / e-RKA Politeknik Kampar**

---

## 1. Arsitektur Komponen

| Komponen | Lokasi | Fungsi |
|---|---|---|
| Migrasi | `database/migrations/2026_11_17_023520_create_knowledge_center_tables.php` | Tabel `knowledge_categories`, `knowledge_articles`, `knowledge_article_role`, `knowledge_article_feedback` |
| Models | `app/Models/KnowledgeCategory.php`, `KnowledgeArticle.php`, `KnowledgeArticleFeedback.php` | Relasi Eloquent, status konstanta, scope `published()` & `accessibleByRoleIds()` |
| Service | `app/Services/Knowledge/KnowledgeCenterService.php` | Resolusi role aktif (`ContextManager`), query artikel berizin, ekstraksi Daftar Isi otomatis, deteksi artikel usang |
| Policies | `app/Policies/KnowledgeCategoryPolicy.php`, `KnowledgeArticlePolicy.php` | Otorisasi manajemen kategori & artikel terintegrasi Filament Shield |
| Reader Page | `app/Filament/Pages/Help/KnowledgeCenterPage.php` + `resources/views/filament/pages/help/knowledge-center-page.blade.php` | Halaman **Pusat Bantuan → Pusat Panduan** (pencarian, filter kategori/modul, TOC, prev/next, feedback) |
| Admin Resources | `KnowledgeCategoryResource` & `KnowledgeArticleResource` | Pengelolaan kategori, editor `RichEditor`, penargetan role, lampiran PDF, & metadata verifikasi |
| Seeder | `database/seeders/KnowledgeCenterSeeder.php` | Inisialisasi permission Shield, 6 kategori, dan 8 panduan awal berbasis fitur aktual |

---

## 2. Daftar Permission (Filament Shield)

- **Akses Baca (semua role aktif)**: `View:KnowledgeCenterPage`
- **Manajemen Artikel (`super_admin`)**: `ViewAny:KnowledgeArticle`, `View:KnowledgeArticle`, `Create:KnowledgeArticle`, `Update:KnowledgeArticle`, `Delete:KnowledgeArticle`, `DeleteAny:KnowledgeArticle`, `Publish:KnowledgeArticle`, `Reorder:KnowledgeArticle`
- **Manajemen Kategori (`super_admin`)**: `ViewAny:KnowledgeCategory`, `View:KnowledgeCategory`, `Create:KnowledgeCategory`, `Update:KnowledgeCategory`, `Delete:KnowledgeCategory`, `DeleteAny:KnowledgeCategory`, `Reorder:KnowledgeCategory`

---

## 3. Mekanisme Keamanan & Pembatasan Akses

1. **Status Publikasi**: Halaman pembaca hanya memuat artikel berstatus `published` pada kategori `is_active = true`. Artikel `draft` dan `archived` disembunyikan dan ditolak (`403`) jika diakses via URL langsung.
2. **Pembatasan Peran Aktif (`ContextManager`)**:
   - `KnowledgeCenterService::resolveEffectiveRoleIds()` memprioritaskan role aktif dari `ContextManager` (atau fallback ke seluruh role user jika tidak ada context).
   - Artikel yang tidak memiliki relasi role (`roles` kosong) bersifat umum; artikel dengan relasi role hanya dapat dibaca oleh role tersebut.
   - Saat pengguna multi-role mengganti peran aktif, daftar panduan dan hasil pencarian otomatis menyesuaikan.
3. **Proteksi URL Langsung**: Parameter `?artikel=<slug>` divalidasi ulang di backend pada `KnowledgeCenterPage::mount()` dan `openArticle()`; akses tidak sah mengembalikan `403 Forbidden`.

---

## 4. Pemeliharaan Dokumentasi

- Setiap artikel memiliki metadata `module_key`, `resource_fqcn`, `doc_version`, `last_verified_at`, dan `maintainer_name`.
- `KnowledgeCenterService::getArticlesNeedingVerification()` dapat digunakan untuk mendeteksi artikel yang class Resource-nya sudah berubah atau belum diverifikasi lebih dari 6 bulan.
