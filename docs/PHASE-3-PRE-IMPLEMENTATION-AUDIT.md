# PHASE 3 — Pre-Implementation Audit Report

**Tanggal Audit:** 2026-11-15  
**Branch Aktif:** `feature/rka-approved-excel-import`  
**Tujuan:** Audit menyeluruh terhadap komponen RKA Core, Native Import, Workflow Manual/Review, Database Schema, dan Otorisasi sebelum implementasi Cross-Unit Reconciliation Engine & Finance Reconciliation Monitoring.

---

## 1. Audit RKA Core Models & Relasi

| Komponen | Lokasi | Peran & Temuan Utama |
|---|---|---|
| `RkaHeader` | `app/Models/RkaHeader.php` | Header RKA per `(schedule_id, department_id)` (unique constraint `unique_schedule_department`). Menyimpan `status` (`DRAFT`, `SUBMITTED`, `REVIEWED`, `APPROVED`, dst.), `is_locked`, `approved_at`, `pagu_budget`. Memiliki relasi `indicators()`, `items()`, `adjustments()`, `incomingMovedAdjustments()`, `incomingSharedCosts()`, `importBatches()`, `unlockRequests()`. |
| `RkaIndicator` | `app/Models/RkaIndicator.php` | Program/indikator per `RkaHeader`. Untuk Native Import, indikator dikelompokkan berdasarkan nama worksheet Belanja (`Belanja Pegawai`, `Belanja Barang dan Jasa`, dst.). |
| `RkaItem` | `app/Models/RkaItem.php` | Item belanja operasional per `RkaIndicator` & `Account`. Menyimpan `form_type` (`5.1`, `5.2`, `5.3`, `5.4`, `5.5`, `2.1`), `account_id`, `description`, `proposed_volume`, `unit_symbol`, `unit_price`, `proposed_total`, `approved_volume`, `approved_total`, `status`. |
| `RkaAdjustment` | `app/Models/RkaAdjustment.php` | Penyesuaian Section A (`SECTION_A`) dan Section B (`SECTION_B`). Kolom kunci: `source_ref_id`, `rka_header_id`, `rka_item_id`, `section_type`, `account_id`, `description`, `destination_unit_id`, `target_unit_id`, `proposed_volume`, `unit_symbol`, `unit_price`, `proposed_amount`, `approved_amount`, `action_type` (`MOVED`, `NOT_BUDGETED`, `PENDING`, `SHARED_COST`, `ADDITIONAL`), `is_imported`, `imported_rka_item_id`, `adjusted_by`. Memiliki `scopeVisibleToUser(Builder $query, User $user)`. |
| `RkaApproval` | `app/Models/RkaApproval.php` | Riwayat approval berjenjang untuk workflow manual. Native Import tidak membuat `RkaApproval` karena langsung `APPROVED` & terkunci. |
| `RkaLog` | `app/Models/RkaLog.php` | Audit trail aktivitas RKA. Menggunakan `RkaActivityLogService` dengan dukungan `idempotency_key` dan `subject_type`/`subject_id` agar satu business event menghasilkan tepat satu log. |
| `RkaSchedule` | `app/Models/RkaSchedule.php` | Jadwal/periode RKA (`fiscal_year`, `period_range`, `phase_name`). Menjadi boundary wajib untuk cross-unit matching agar item lintas periode tidak pernah tercampur. |
| `RkaSetting` | `app/Models/RkaSetting.php` | Pengaturan penandatangan dan metadata RKA aktif. |
| `RkaGenerateConfig` | `app/Models/RkaGenerateConfig.php` | Konfigurasi generate RKA awal oleh `RkaGenerateService`. |
| `DepartmentBudget` | `app/Models/DepartmentBudget.php` | Pagu anggaran departemen hasil sinkronisasi workflow approval. Tidak menimpa `rka_headers.pagu_budget` saat Native Import. |
| `FundRequest` | `app/Models/FundRequest.php` | Pencairan dana berbasis `RkaItem`. |
| `Department` | `app/Models/Department.php` | Master unit kerja/prodi (`code`, `name`). |
| `Account` | `app/Models/Account.php` | Master kode rekening (`code`, `name`, `parent_id`). |
| `User` | `app/Models/User.php` | Pengguna dengan Spatie Roles/Permissions dan relasi `departments()` (`user_departments`). |
| `RkaItemReview` | `app/Models/RkaItemReview.php` | **Tidak aktif** — sesuai batasan keras, tidak boleh diaktifkan atau dibuatkan migration. |

---

## 2. Audit Native Import & Unlock Workflow

| Komponen | Lokasi | Status Audit |
|---|---|---|
| `ImportApprovedRkaPage` | `app/Filament/Resources/RkaHeaders/Pages/ImportApprovedRkaPage.php` | Halaman preview & eksekusi import untuk role `pemohon` (hanya unit yang diampunya) dan `super_admin`. Validasi server-side terhadap `department_id` & `schedule_id`. |
| `NativeRkaWorkbookParser` | `app/Services/ApprovedRkaImport/NativeRkaWorkbookParser.php` | Membaca 12–13 worksheet workbook asli Politeknik Kampar (`RINGKASAN`, `DAFTAR PENYESUAIAN`, `BERITA ACARA`, `IDENTITAS`, `REKAP`, 6 sheet `Belanja`, mengabaikan sheet teknis `TB`/`DAFTAR`). Sudah membaca `Usulan awal unit` (`RINGKASAN!C6`) dan memetakan Section B `Unit Asal` via `DepartmentResolver`. |
| `DepartmentResolver` | `app/Services/ApprovedRkaImport/DepartmentResolver.php` | Resolver deterministik berbasis `UNIT_ALIASES`, exact match `code`/`name`, serta kode dalam tanda kurung; mengembalikan `RESOLVED`, `NOT_FOUND`, atau `AMBIGUOUS`. |
| `ApprovedRkaImportService` | `app/Services/ApprovedRkaImport/ApprovedRkaImportService.php` | Menjalankan `preview()` (read-only + cache token), `executeImport()`, dan `executeReimport()` dalam `DB::transaction()`. Menyimpan `pagu_budget` dari `RINGKASAN!C6` dan mengunci `RkaHeader` sebagai `APPROVED`. |
| `RkaApprovedImportBatch` | `app/Models/RkaApprovedImportBatch.php` | Menyimpan histori batch import (`INITIAL` / `REIMPORT`), checksum SHA-256, `unlock_request_id`, dan `summary` JSON. |
| `RkaUnlockRequestService` & `ManageUnlockRequestsPage` | `app/Services/ApprovedRkaImport/RkaUnlockRequestService.php`, `app/Filament/Pages/Rka/ManageUnlockRequestsPage.php` | Mengelola permintaan buka kunci oleh `pemohon` dan persetujuan/penolakan oleh `keuangan` / `wadir_keuangan` / `super_admin`. |

---

## 3. Audit Workflow Manual & Review (Acuan Semantik Bisnis)

1. **`RkaReviewService` & `AddAdjustmentForm`**:
   - **Section A** (`SECTION_A`): Item yang diusulkan unit asal lalu dipindah (`MOVED`) atau tidak dianggarkan (`NOT_BUDGETED`) atau ditunda (`PENDING`). `destination_unit_id` hanya diisi jika `MOVED` ke unit tujuan yang diketahui; `target_unit_id = NULL`, `imported_rka_item_id = NULL`, `is_imported = false`.
   - **Section B** (`SECTION_B`): Pos yang dikelola unit pengelola untuk unit penerima manfaat (`Unit Asal` di Excel). `target_unit_id` = unit penerima manfaat; `destination_unit_id = NULL`; `imported_rka_item_id` terisi apabila item operasional ada di sheet `Belanja` unit pengelola (`is_imported = true`).
2. **`RkaAdjustmentPolicy` & `RkaAdjustment::scopeVisibleToUser()`**:
   - Pemohon melihat seluruh adjustment pada `RkaHeader` miliknya.
   - Unit tujuan (`destination_unit_id`) / penerima manfaat (`target_unit_id`) hanya melihat adjustment lintas unit apabila `rka_headers.status = 'APPROVED'`.

---

## 4. Audit Database Schema & Kesenjangan

Dari hasil `SHOW TABLES` dan `DESCRIBE`:
- Belum terdapat tabel rekonsiliasi antarunit (`rka_adjustment_matches`) maupun tabel histori koreksi rekonsiliasi (`rka_reconciliation_histories`).
- `rka_adjustments` sudah memiliki `source_ref_id`, `rka_header_id`, `rka_item_id`, `imported_rka_item_id`, `destination_unit_id`, `target_unit_id`, `account_id`, `action_type`, `is_imported`.
- `rka_approved_import_batches` sudah memiliki `id`, `rka_header_id`, `department_id`, `schedule_id`, `import_type`, `file_checksum`.

### Kesimpulan Audit
Tidak ada blocker bisnis maupun konflik skema. Sistem siap untuk langkah **Design & Implementasi**:
1. Migration `rka_adjustment_matches` dan `rka_reconciliation_histories` dengan foreign key, unique constraint idempotent, dan index pencarian `(schedule_id, reconciliation_status)`, `(source_department_id, target_department_id)`.
2. `CrossUnitItemMatcher` & `RkaReconciliationService` yang dijalankan via `DB::afterCommit()` setelah import/reimport.
3. Halaman Filament `ManageReconciliationsPage` (`Manajemen RKA → Rekonsiliasi Antarunit`) dengan `RkaAdjustmentMatchPolicy`.
