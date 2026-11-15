# Laporan Perbaikan Temuan Audit RKA — PHASE 1

**Repository:** `mahdiawan-nk/simrka-polkam`  
**Branch:** `feature/rka-approved-excel-import`  
**Tanggal:** 15 November 2026  
**Referensi Audit:** `docs/RKA-APPROVED-TEMPLATE-FLOW-AUDIT.md`

---

## 1. Daftar Temuan Audit dan Status Verifikasi

| ID | Temuan Audit | Status Verifikasi | Ringkasan |
|---|---|---|---|
| A | `RkaReviewService::addAdjustment()` tidak mengisi kolom `unit_symbol` (`NOT NULL`) | **VERIFIED** | `rka_adjustments.unit_symbol` bertipe `varchar(50) NOT NULL` tanpa default; `AddAdjustmentForm` tidak memiliki field `unit_symbol`, `proposed_volume`, `unit_price`. |
| B | `RkaReviewService::importAdjustmentToItem()` tidak mengisi kolom `form_type` (`NOT NULL`) dan berisiko `rka_indicator_id = null` | **VERIFIED** | `rka_items.form_type` bertipe `varchar(10) NOT NULL` tanpa default dan `rka_indicator_id` `NOT NULL`. |
| C | Status `PENDING` dipetakan ke `Section B` pada `RkaReviewService::reviewItem()` | **NEEDS BUSINESS DECISION** | Terverifikasi `PENDING` tidak ada di `$rejectedStatuses` sehingga masuk `Section B`. Perilaku existing dipertahankan sampai ada keputusan bisnis. |
| D | Potensi duplikasi / stale adjustment pada `RkaReviewService::reviewItem()` saat transisi status | **VERIFIED** | Transisi dari `REJECTED` (`A`) atau `MOVED` (`A`+`B`) ke `PENDING` (`B`) sebelumnya hanya menghapus `section_type = 'B'`, menyisakan record `section_type = 'A'` yang usang. |
| E | Konsistensi perhitungan nilai RKA (`calculatePaguRka`, `calculateApprovedTotal`, rekap, `DepartmentBudget`, `FundRequest`) | **ALREADY FIXED / VERIFIED CONSISTENT** | `calculatePaguRka()` mengecualikan `is_imported = 1` (usulan awal unit), sedangkan `calculateApprovedTotal()` & `approveByDirektur()` menjumlahkan nilai final disetujui termasuk item impor Section B. |
| F | Ketidaksesuaian `DepartmentBudget::updateOrCreate()` dengan unique key `dept_account_fiscal_unique` | **VERIFIED** | Unique key MySQL adalah `(department_id, account_id, fiscal_year)`, sedangkan `RkaApprovalService::approveByDirektur()` mencocokkan berdasarkan `(department_id, account_id, schedule_id)`. |
| G | `RkaItemReview` & `RkaLog` belum diaktifkan dalam workflow | **ARCHIVED / OUT OF SCOPE** | Sesuai keputusan arsitektur, `RkaItemReview` dan `RkaLog` tidak diaktifkan pada fase ini. |

---

## 2. Detail Root Cause & Perbaikan yang Diterapkan

### A. `RkaReviewService::addAdjustment()` & `AddAdjustmentForm`
- **Root Cause:** Tabel `rka_adjustments` memiliki kolom `unit_symbol varchar(50) NOT NULL` (ditambahkan pada migrasi `2026_10_17_031130_add_column_table_rka_adjustments.php`), namun `AddAdjustmentForm` tidak menyediakan input `proposed_volume`, `unit_symbol`, dan `unit_price`, serta `RkaReviewService::addAdjustment()` tidak mengisi ketiga kolom tersebut saat memanggil `RkaAdjustment::create()`.
- **Perbaikan:**
  - `app/Filament/Resources/RkaHeaders/Schemas/AddAdjustmentForm.php`: Menambahkan field `proposed_volume`, `unit_symbol` (required, max 50), dan `unit_price` dengan kalkulasi otomatis ke `proposed_amount`.
  - `app/Services/RkaReviewService.php` (`addAdjustment()`): Memvalidasi `unit_symbol` (mengambil dari input `$data['unit_symbol']` atau fallback dari `rka_item_id` jika tersedia; melempar `InvalidArgumentException` jika kosong tanpa hardcode) serta menyimpan `proposed_volume`, `unit_symbol`, dan `unit_price`.

### B. `RkaReviewService::importAdjustmentToItem()`
- **Root Cause:** `RkaReviewService::importAdjustmentToItem()` memanggil `RkaItem::updateOrCreate()` tanpa mengisi `form_type` (`NOT NULL`), tanpa memastikan `rka_indicator_id` valid pada `$record->id` (`NOT NULL`), dan menyertakan `proposed_total` yang sebenarnya merupakan *stored generated column*.
- **Perbaikan (`app/Services/RkaReviewService.php`):**
  - Melakukan lookup `Account` beserta hierarki `parent` hingga Level 1 (`5.1`, `5.2`, `5.3`, `5.4`, `5.5`, `2.1`) untuk menentukan `form_type` secara konsisten dengan `calculateRekapByKelompok()`.
  - Memastikan `rka_indicator_id` berasal dari `RkaIndicator` milik `$record->id` (mencari berdasarkan `rka_indicator_id` atau `account_id` Level 1), dan melempar `InvalidArgumentException` yang informatif jika indikator tujuan belum tersedia.
  - Memvalidasi `unit_symbol` dan menghapus `proposed_total` dari payload `updateOrCreate()`.

### C. Penanganan Status `PENDING` (Diperbarui pada Phase 1 Lanjutan)
- **Keputusan Bisnis:** Status `PENDING` resmi ditetapkan masuk ke **Section A** (`Penyesuaian Unit Asal`), bukan Section B, karena item yang ditunda masih menjadi tanggung jawab unit/divisi asal dan belum disetujui maupun dipindahkan ke unit lain.
- **Perubahan (`app/Services/RkaReviewService.php`):**
  - Menambahkan `'PENDING'` ke dalam `$sectionAStatuses = ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE', 'PENDING']` pada `reviewItem()`.
  - `PENDING` kini tersimpan sebagai `section_type = 'A'`, `approved_amount = 0`, terhubung ke `rka_item_id` asal, `destination_unit_id = null`, `target_unit_id = null`.
- **Dampak Perhitungan:**
  - `RkaReviewService::calculateSummaryData()`: Nilai `tambahan_pusat` (Section B) tidak lagi membengkak oleh item `PENDING`.
  - `RkaApprovalService::approveByDirektur()`: Item `PENDING` tetap tidak masuk agregasi `DepartmentBudget` dan tidak menghasilkan item pada unit tujuan.
- **Hasil Regression Test:** Diverifikasi pada `tests/Feature/RkaAuditFixesTest.php` (`section_type = 'A'`, tidak ada Section B, `tambahan_pusat = 0`, tidak ada `DepartmentBudget`, tidak ada duplikasi saat transisi status).

### D. Konsistensi `RkaAdjustment::updateOrCreate()`
- **Root Cause:** Pada `RkaReviewService::reviewItem()` cabang non-`APPROVED` & non-`MOVED`, pembersihan sebelumnya hanya menghapus `section_type = 'B'` tanpa menghapus `section_type = 'A'` atau adjustment lintas header (bekas `MOVED`) untuk `rka_item_id` yang sama.
- **Perbaikan (`app/Services/RkaReviewService.php`):**
  - Menghapus record `RkaAdjustment` milik `rka_item_id` yang berada pada header lain (`rka_header_id != $record->id`) atau `section_type` lawan (`section_type != $sectionType`) sebelum memanggil `updateOrCreate()`.
  - Penambahan adjustment manual (`SHARED_COST`, `ADDED_BY_REVIEWER`) tetap menggunakan `RkaAdjustment::create()` sehingga tidak pernah saling menimpa.

### F. Sinkronisasi `DepartmentBudget`
- **Root Cause:** Tabel `department_budgets` memiliki unique constraint `dept_account_fiscal_unique (department_id, account_id, fiscal_year)`. `RkaApprovalService::approveByDirektur()` sebelumnya mencari record dengan kunci `(department_id, account_id, schedule_id)`, sehingga pengesahan RKA pada schedule kedua dalam `fiscal_year` yang sama akan memicu error `Duplicate entry`.
- **Perbaikan (`app/Services/RkaApprovalService.php`):**
  - Mengubah kunci `DepartmentBudget::updateOrCreate()` menjadi `['department_id', 'account_id', 'fiscal_year']` (konsisten dengan unique constraint database dan `DepartmentBudgetImporter`), serta menempatkan `schedule_id` dan `allocated_amount` di array nilai.
  - Menolkan (`allocated_amount = 0`) akun pada `schedule_id` & `fiscal_year` yang sama apabila seluruh item pada akun tersebut tidak lagi disetujui setelah revisi.

---

## 3. Daftar File yang Diubah dan Dibuat

| Status | File Path |
|---|---|
| Diubah | `app/Filament/Resources/RkaHeaders/Schemas/AddAdjustmentForm.php` |
| Diubah | `app/Filament/Resources/RkaHeaders/Pages/CreateRkaHeader.php` |
| Diubah | `app/Filament/Resources/RkaHeaders/Pages/EditRkaHeader.php` |
| Diubah | `app/Filament/Resources/RkaHeaders/Pages/RkaFormIndicatorAndItem.php` |
| Diubah | `app/Filament/Resources/RkaHeaders/Pages/ReviewRkaPage.php` |
| Diubah | `app/Filament/Actions/Rka/RkaActions.php` |
| Diubah | `app/Models/RkaLog.php` |
| Diubah | `app/Services/RkaReviewService.php` |
| Diubah | `app/Services/RkaApprovalService.php` |
| Diubah | `app/Services/RkaItemService.php` |
| Diubah | `app/Services/RkaGenerateService.php` |
| Diubah | `resources/views/filament/resources/rka-headers/pages/review-rka-page.blade.php` |
| Diubah | `resources/views/filament/resources/rka-headers/pages/rka-form-indicator-and-item.blade.php` |
| Dibuat | `app/Services/RkaActivityLogService.php` |
| Dibuat | `database/migrations/2026_11_15_143907_add_subject_and_description_to_rka_logs_table.php` |
| Dibuat | `resources/views/filament/resources/rka-headers/components/activity-history.blade.php` |
| Dibuat | `tests/Feature/RkaAuditFixesTest.php` |
| Dibuat | `tests/Feature/RkaActivityLogTest.php` |
| Dibuat | `docs/RKA-ACTIVITY-LOG-DESIGN.md` |
| Dibuat | `docs/RKA-AUDIT-FIXES.md` |

*Dilakukan 1 migration additive (`add_subject_and_description_to_rka_logs_table`) untuk mendukung pelacakan entitas polimorfik (`subject_type`, `subject_id`), `description`, serta index query pada `rka_logs`.*

---

## 4. Aktivasi `RkaLog` untuk Aktivitas RKA Header

### 4.1 Struktur Tabel `rka_logs` yang Digunakan
Tabel `rka_logs` (migrasi awal `2026_10_11_020435_create_rka_logs_table.php` ditambah migrasi additive `2026_11_15_143907_add_subject_and_description_to_rka_logs_table.php`):
- Kolom: `id`, `rka_header_id` (nullable, FK `cascadeOnDelete`), `subject_type` (`varchar(120)`, nullable), `subject_id` (`bigint unsigned`, nullable), `user_id` (NOT NULL, FK ke `users.id`), `action` (`varchar(100)`), `description` (`varchar(500)`, nullable), `old_values` (`json`), `new_values` (`json`), `ip_address` (`varchar(45)`), `created_at` (`timestamp`).
- Index tambahan: `idx_rka_logs_subject (subject_type, subject_id)`, `idx_rka_logs_header_created (rka_header_id, created_at)`, `idx_rka_logs_header_action (rka_header_id, action)`.
- Desain lengkap terdokumentasi di `docs/RKA-ACTIVITY-LOG-DESIGN.md`.

### 4.2 Aktivitas yang Dicatat & Lokasi Implementasi
- **`RkaGenerateService::generate()`**: `GENERATE_RKA` saat `RkaHeader` baru dibuat.
- **`CreateRkaHeader::afterCreate()` / `EditRkaHeader::afterSave()`**: `CREATE_RKA` dan `UPDATE_RKA`.
- **`RkaItemService` & `RkaActions`**: `ADD_INDICATOR`, `ADD_ITEM`, `UPDATE_ITEM`, `DELETE_ITEM`, `CLEAR_ITEMS`, `IMPORT_ITEMS`, `SUBMIT_RKA`.
- **`RkaApprovalService`**: `SUBMIT_RKA`, `RESUBMIT_RKA` (saat `REVISION_REQUIRED` → `SUBMITTED`), `START_REVIEW`, `FORWARD_DIREKTUR`, `REQUEST_REVISION`, `APPROVE_RKA`.
- **`RkaReviewService`**: `START_REVIEW` (transisi otomatis `submitted` → `IN_REVIEW` di `loadRkaRecord()`), `REVIEW_UPDATE`, `BULK_APPROVE`, `ADJUSTMENT_MOVE` (dicatat pada RKA asal dan RKA target bila terdampak), `ADD_ADJUSTMENT`, `DELETE_ADJUSTMENT`, `IMPORT_ADJUSTMENT`.

### 4.3 Mekanisme Transaksi, Service Terpusat & Tampilan Histori
- Pencatatan log dipusatkan melalui `App\Services\RkaActivityLogService::log()` (terintegrasi dengan `RkaLog::record()`, menyaring field sensitif seperti `password`, `token`, `remember_token`, serta mendukung parameter opsional `$idempotencyKey` untuk mencegah duplikasi pada retry).
- Seluruh pemanggilan ditempatkan di dalam `DB::transaction()` **setelah** operasi bisnis berhasil, sehingga rollback otomatis membatalkan log, dan blok duplikat pada `RkaReviewService::reviewItem()` serta `bulkMoveItems()` telah dibersihkan (audit duplikasi & data historis didokumentasikan di `docs/RKA-ACTIVITY-LOG-DESIGN.md`).
- Tampilan histori aktivitas (read-only, paginated 10 per halaman, urut terbaru → terlama) ditambahkan melalui komponen `activity-history.blade.php` pada tab **Riwayat Aktivitas** di `ReviewRkaPage` dan bagian bawah `RkaFormIndicatorAndItem`, lengkap dengan filter berdasarkan aksi & pengguna, badge entitas terdampak (`subject_label #subject_id`), serta detail perubahan (`old_values`/`new_values`), terlindungi oleh `RkaHeaderPolicy::view()`.

---

## 5. Hasil Pengujian

Perintah yang dijalankan di container `app` (terhadap database MySQL `erka` dengan `DatabaseTransactions`):

```bash
docker compose exec -e DB_CONNECTION=mysql -e DB_HOST=db -e DB_PORT=3306 -e DB_DATABASE=erka -e DB_USERNAME=erka -e DB_PASSWORD=erka app php artisan test tests/Feature/RkaActivityLogTest.php tests/Feature/RkaAuditFixesTest.php tests/Feature/RkaReviewServiceTest.php tests/Feature/RkaSchemaAndStatusTest.php --compact
```

- **Total:** 24 passed (160 assertions), 0 failed, 0 skipped.
- **Pint:** `vendor/bin/pint --dirty --format agent` berhasil dijalankan (`passed`).

---

## 6. Risiko yang Masih Tersisa

1. **Data Historis `rka_adjustments` Lama:** Record lama (sebelum perbaikan ini) yang dibuat dengan `unit_symbol` kosong atau `PENDING` di Section B tidak dimigrasi ulang secara otomatis.
2. **Unique Key `DepartmentBudget`:** Karena unique key tetap `(department_id, account_id, fiscal_year)`, pengesahan schedule kedua (misal RKA Perubahan) menimpa `allocated_amount` schedule pertama pada tahun anggaran yang sama (perilaku yang diinginkan untuk pagu aktif tahunan).
