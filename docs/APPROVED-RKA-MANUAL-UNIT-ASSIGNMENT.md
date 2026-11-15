# Audit & Implementasi Manual Unit Assignment pada Import Approved RKA

Dokumen ini berisi hasil audit implementasi existing serta spesifikasi implementasi fitur **Manual Unit Assignment** oleh role `pemohon` pada tab `Daftar Penyesuaian` halaman Preview Import Approved RKA.

---

## 1. Hasil Audit Implementasi Existing

1. **`RkaAdjustment` (`app/Models/RkaAdjustment.php`) & Tabel `rka_adjustments`**:
   - Sudah memiliki `destination_unit_id` (nullable FK -> `departments.id`), `target_unit_id` (nullable FK -> `departments.id`), `rka_header_id`, `rka_item_id`, `imported_rka_item_id`, `account_id`, `section_type` (`A`/`B`), `action_type`, `proposed_volume`, `unit_symbol`, `unit_price`, `proposed_amount`, `approved_amount`, `notes`, `adjusted_by`, `is_imported`.
   - Tidak diperlukan migration baru karena seluruh kolom dan foreign key sudah tersedia.

2. **Workflow Manual (`RkaReviewService::reviewItem()`, `addAdjustment()`, `importAdjustmentToItem()`) & Backup CSV (`data-rka/rka_adjustments_20261115_132958.csv`)**:
   - **Section A**: `destination_unit_id` = unit tujuan (hanya relevan untuk `MOVED`), `target_unit_id = NULL`, `approved_amount = 0.00`. Status non-`MOVED` (`REJECTED`, `BUDGETED_ELSEWHERE`, `PENDING`, `REMOVED`) tetap berada di Section A tanpa pasangan Section B.
   - **Section B**: `destination_unit_id = NULL`, `target_unit_id` = unit asal/sumber penerima manfaat (`targetUnit` pada `adjustment-sections.blade.php`), menyimpan `proposed_amount` dan `approved_amount`.
   - **UI Detail RKA (`adjustment-sections.blade.php`)**:
     - Section A menampilkan `$adj->destinationUnit?->name ?? '—'`.
     - Section B menampilkan `$adj->targetUnit?->code ?? 'Internal / Pusat'`.

3. **Import Approved RKA (`ImportApprovedRkaPage`, `ApprovedRkaImportService`, `NativeRkaWorkbookParser`)**:
   - Preview disimpan di cache (`approved_rka_preview:{token}`) sebelum eksekusi `executeImport()` / `executeReimport()`.
   - Sebelum perubahan ini, Pemohon belum dapat menentukan atau mengubah `destination_department_id` (Section A) maupun `target_department_id` (Section B) secara manual di halaman Preview, dan baris Section A `MOVED` belum divalidasi kewajiban `destination_unit_id`-nya sebelum finalisasi import.

---

## 2. Aturan Section A & Section B untuk Manual Unit Assignment

| Aspek | Section A (`Tentukan Unit Tujuan`) | Section B (`Tentukan Unit Asal/Sumber`) |
|---|---|---|
| Kolom FK Target | `destination_unit_id` (`departments.id`) | `target_unit_id` (`departments.id`) |
| Kolom FK Lawan | Selalu `NULL` (`target_unit_id = NULL`) | Selalu `NULL` (`destination_unit_id = NULL`) |
| Wajib Sebelum Finalisasi | Wajib jika `action_type = MOVED` | Wajib untuk setiap baris Section B |
| Status Non-Wajib | `PENDING`, `REJECTED`, `BUDGETED_ELSEWHERE`, `REMOVED` (`TIDAK MEMERLUKAN UNIT`) | — |
| Larangan | Unit tujuan !== unit pemilik RKA asal; tidak mengubah `action_type`, `proposed_amount`, `approved_amount`, `rka_item_id` | Unit sumber !== unit pemilik RKA pengelola; tidak mengubah `proposed_amount`, `approved_amount`, `imported_rka_item_id` |

---

## 3. Status Relasi pada Preview (`Daftar Penyesuaian`)

- `BELUM DITENTUKAN`: Baris wajib unit (`Section A MOVED` atau `Section B`) belum memiliki unit.
- `UNIT TELAH DITENTUKAN`: Unit valid (`!== unit pemilik RKA`) sudah dipilih atau terpetakan.
- `TIDAK MEMERLUKAN UNIT`: Baris Section A non-`MOVED` (`PENDING`, `REJECTED`, `BUDGETED_ELSEWHERE`, `REMOVED`).
- `UNIT TIDAK VALID`: Unit dari Excel gagal dipetakan atau sama dengan unit pemilik RKA; harus diperbaiki sebelum import.

---

## 4. Ringkasan Implementasi

1. **Parser (`app/Services/ApprovedRkaImport/NativeRkaWorkbookParser.php`)**:
   - Baris `Section A` dengan `action_type === 'MOVED'` diinisialisasi dengan `relation_status = 'BELUM DITENTUKAN'` dan `validation_status = 'PERLU PERHATIAN'`.
   - Baris `Section A` non-`MOVED` diinisialisasi dengan `relation_status = 'TIDAK MEMERLUKAN UNIT'` dan `validation_status = 'SESUAI'`.
   - Baris `Section B` yang unit asalnya gagal dipetakan atau sama dengan unit pemilik RKA ditandai `UNIT TIDAK VALID` (`PERLU PERHATIAN` / non-fatal warning) sehingga Pemohon dapat memperbaikinya secara manual di halaman Preview.
2. **Service (`app/Services/ApprovedRkaImport/ApprovedRkaImportService.php`)**:
   - `summarizeUnitAssignments()` menghitung `incomplete_unit_count`, `complete_unit_count`, dan `missing_unit_items` pada summary preview.
   - `assignUnitOnPreview()` memvalidasi kepemilikan preview, otorisasi departemen Pemohon, keberadaan `Department`, larangan memilih unit pemilik RKA sendiri, serta larangan menentukan unit pada Section A non-`MOVED`; memperbarui cache preview dan mencatat 1 `RkaLog` (`RkaLog::ACTION_APPROVED_IMPORT_UNIT_ASSIGNED`).
   - `validateAdjustmentUnitsBeforeFinalize()` dipanggil pada `executeImport()` dan `executeReimport()` untuk memblokir finalisasi apabila masih ada `Section A MOVED` tanpa `destination_department_id` atau `Section B` tanpa `target_department_id`.
3. **UI Preview (`ImportApprovedRkaPage.php` & `import-approved-rka-page.blade.php`)**:
   - Menambahkan Filament Action `assignUnit` berupa modal yang menampilkan konteks item penyesuaian dan `Select` searchable dari master `departments` (`Nama Unit (CODE)`), kecuali unit pemilik RKA.
   - Menambahkan badge kelengkapan unit, banner daftar item yang belum lengkap, kolom `Unit Tujuan / Unit Asal`, `Status Relasi`, tombol aksi per baris (`Tentukan Unit Tujuan` / `Tentukan Unit Asal/Sumber` / `Edit Unit`), serta menonaktifkan tombol konfirmasi import selama `incomplete_unit_count > 0`.

---

## 5. Hasil Verifikasi & Pengujian

- **Laravel Pint**: `docker compose exec app vendor/bin/pint --dirty --format agent` — lulus tanpa error.
- **Regression Test Suite (`php artisan test ... --compact`)**: **31 passed (580 assertions)** pada MySQL Docker:
  - `AdaptiveDaftarPenyesuaianImportTest` (4 passed)
  - `ManualUnitAssignmentImportTest` (4 passed)
  - `DepartmentResolverTest` (5 passed)
  - `NativeApprovedRkaImportTest` (5 passed)
  - `CrossUnitReconciliationTest` (6 passed)
  - `ApprovedRkaImportAndUnlockTest` (5 passed)
  - `RkaApprovedExcelExportTest` (2 passed)

---

## 6. Adaptive Unit Mapping & Dynamic Header Mapping (`DAFTAR PENYESUAIAN`)

1. **Dynamic Header Mapping (`NativeRkaWorkbookParser::detectDaftarPenyesuaianHeaderMap()`)**:
   - Mendeteksi posisi kolom secara independen per section (`A` dan `B`) berdasarkan nama header ternormalisasi (`no`, `kode`, `uraian`, `volume`, `unit_tujuan`, `unit_asal`, `jumlah_usulan`, `jumlah_disetujui`, `status`, `keterangan`), mendukung variasi huruf besar/kecil, spasi, dan akhiran `(Rp)`.
   - Menangani template **dengan kolom `Unit Tujuan`** (mis. `10_RKA_2026-2027_P3M.xlsx`: `E: Unit Tujuan`, `F: Jumlah Usulan (Rp)`, `G: Status`, `H: Keterangan`) maupun **tanpa kolom `Unit Tujuan`** (mis. `19_RKA_2026-2027_Prodi_TIF.xlsx`: `E: Jumlah Usulan (Rp)`, `F: Status`, `G: Keterangan`) serta Section B dengan atau tanpa `Unit Asal` / `Unit Asal/Sumber` dan variasi urutan kolom.
   - Mendeteksi header duplikat/ambigu pada section yang sama dan memblokir finalisasi import (`has_fatal_errors = true`).
2. **Pemetaan Unit & Sumber Nilai (`EXCEL` vs `MANUAL`)**:
   - **Section A**: Membaca `Unit Tujuan` jika tersedia -> resolve via `DepartmentResolver` -> `destination_department_id` (`unit_source = 'EXCEL'`), sedangkan `target_department_id = null`. Status non-`MOVED` ditandai `TIDAK MEMERLUKAN UNIT`.
   - **Section B**: Membaca `Unit Asal` / `Unit Asal/Sumber` jika tersedia -> resolve via `DepartmentResolver` -> `target_department_id` (`unit_source = 'EXCEL'`), sedangkan `destination_department_id = null`.
   - Edit atau pemilihan manual melalui modal `assignUnit` mengubah `unit_source` menjadi `'MANUAL'`, mempertahankan nilai Excel asli (`unit_tujuan_excel` / `unit_asal_excel`) untuk tampilan preview, dan menjadi nilai final saat disimpan ke database.
