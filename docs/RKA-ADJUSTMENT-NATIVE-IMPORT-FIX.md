# Perbaikan Relasi `RkaAdjustment` pada Native Approved RKA Import

Dokumen ini merangkum audit bisnis, perbaikan arsitektur, dan hasil verifikasi untuk penyelarasan penyimpanan `RkaAdjustment` pada fitur **Native Approved RKA Import** agar konsisten dengan workflow RKA manual (`RkaReviewService`, `AddAdjustmentForm`, `RkaHeader`).

---

## 1. Akar Masalah (Root Cause)

Pada implementasi awal Native Approved RKA Import, `NativeRkaWorkbookParser` menyimpan baris `DAFTAR PENYESUAIAN` Section B (contoh: workbook `05_RKA_2026-2027_UPT_ICT.xlsx`) dengan:
- `target_unit_id = NULL` (nama `Unit Asal` seperti `P3M`, `UPT Media Center`, `Prodi D3 TIF`, `Prodi D4 ABI` tidak dipetakan ke `departments.id`),
- `imported_rka_item_id = NULL` dan `is_imported = false`, meskipun pada Excel asli kolom nilai disetujui mengacu langsung ke item pada sheet `Belanja` unit pengelola (contoh: `='Belanja Barang dan Jasa'!F36`).

Hal ini menyebabkan unit penerima manfaat (`target_unit_id`) tidak dapat melihat alokasi *shared cost* lintas unit setelah RKA berstatus `APPROVED`, serta tidak terhubung ke `RkaItem` operasional di unit pengelola.

---

## 2. Semantik Relasi `RkaAdjustment` (Section A vs Section B)

| Kolom | Section A (`SECTION_A`) | Section B (`SECTION_B`) |
|---|---|---|
| `destination_unit_id` | Terisi hanya jika `action_type = MOVED` dan unit tujuan eksplisit diketahui; selain itu `NULL` (tidak pernah mengarang unit pusat) | Selalu `NULL` |
| `target_unit_id` | Selalu `NULL` | Wajib terisi `departments.id` unit penerima manfaat (`Unit Asal` di Excel `DAFTAR PENYESUAIAN` Bagian B) melalui `DepartmentResolver` |
| `rka_item_id` | Terisi hanya jika pencocokan ke item asal di sheet `Belanja` bersifat `MATCH_UNIQUE`; selain itu `NULL` | `NULL` |
| `imported_rka_item_id` | Selalu `NULL` | Terisi `rka_items.id` milik unit pengelola hanya jika referensi formula/pencocokan ke sheet `Belanja` bersifat `MATCH_UNIQUE`; selain itu `NULL` |
| `is_imported` | Selalu `false` | `true` hanya jika `imported_rka_item_id !== null`; selain itu `false` |
| `adjusted_by` | `NULL` (dokumen historis hasil import, bukan review interaktif di aplikasi) | `NULL` |

---

## 3. Komponen yang Dibuat dan Diperbarui

### 3.1 `DepartmentResolver`
File: `app/Services/ApprovedRkaImport/DepartmentResolver.php`
- Memetakan nama `Unit Asal` dari Excel ke `departments.id` secara deterministik:
  1. `UNIT_ALIASES` eksplisit (`P3M`, `PRODI-TIF`, `PRODI-ABI`, `UPT-ICT`, `UPT-MEDIA`, `UPT-PPKPK`, `BPM`, `BAKKU`, `BAG-UMUM`).
  2. Pencocokan persis terhadap `departments.code` / `departments.name`.
  3. Pencocokan kode di dalam tanda kurung jika tidak ambigu.
- Mengembalikan status `RESOLVED`, `NOT_FOUND`, atau `AMBIGUOUS`. Baris Section B yang berstatus `NOT_FOUND` atau `AMBIGUOUS` ditandai error `UNIT BELUM DITEMUKAN` dan memblokir proses import.

### 3.2 `NativeRkaWorkbookParser`
File: `app/Services/ApprovedRkaImport/NativeRkaWorkbookParser.php`
- Menggunakan `DepartmentResolver` untuk memetakan `Unit Asal` pada Section B ke `target_department_id` dan `target_unit_name`.
- Membaca referensi formula lintas sheet pada kolom nilai Section B (misalnya `='Belanja Barang dan Jasa'!F36`) untuk menghasilkan `imported_item_id` saat `MATCH_UNIQUE`.
- Melakukan pencocokan berlapis pada Section A untuk `origin_item_id` hanya saat `MATCH_UNIQUE`.
- Menyertakan metadata `relation_status` dan `item_match_status` untuk kebutuhan audit di preview UI.

### 3.3 `ApprovedRkaImportService`
File: `app/Services/ApprovedRkaImport/ApprovedRkaImportService.php`
- Pada `buildAdjustmentPayload()`:
  - Memetakan `destination_unit_id` untuk Section A `MOVED` menggunakan `$row['destination_department_id'] ?? $targetDeptId` (selain itu `null`).
  - Memetakan `target_unit_id` untuk Section B ke `$targetDeptId` (selain itu `null`).
  - Memetakan `imported_rka_item_id` dari `$itemIdMap` dan menyetel `is_imported = ($importedItemId !== null)`.

### 3.4 Visibilitas Lintas Unit & Otorisasi
- `app/Models/RkaAdjustment.php`: Menambahkan `scopeVisibleToUser(Builder $query, User $user)`:
  - Pemohon (`departments` yang diikuti user) dapat melihat seluruh `RkaAdjustment` pada `RkaHeader` milik unitnya sendiri.
  - Unit tujuan (`destination_unit_id`) atau unit penerima manfaat (`target_unit_id`) dapat melihat adjustment lintas unit **hanya jika** `rka_headers.status = 'APPROVED'`.
  - Pengguna non-unit (seperti role pengelola keuangan/pimpinan) mengikuti akses review global yang berlaku.
- `app/Policies/RkaAdjustmentPolicy.php`: Menambahkan `viewAny` dan `view` yang menegakkan aturan visibilitas yang sama di lapisan policy.

### 3.5 Preview UI
File: `resources/views/filament/resources/rka-headers/pages/import-approved-rka-page.blade.php`
- Memperbarui tab `Daftar Penyesuaian` untuk menampilkan kolom **Unit Sumber / Target**, **Pencocokan Item**, dan **Status Relasi** sebelum eksekusi import.

---

## 4. Hasil Verifikasi & Pengujian

### 4.1 Laravel Pint
```bash
docker compose exec app vendor/bin/pint --dirty --format agent
```
Hasil: `passed`.

### 4.2 Feature & Regression Tests
```bash
docker compose exec -e DB_CONNECTION=mysql -e DB_HOST=db -e DB_PORT=3306 -e DB_DATABASE=erka -e DB_USERNAME=erka -e DB_PASSWORD=erka app php artisan test tests/Feature/NativeApprovedRkaImportTest.php tests/Feature/ApprovedRkaImportAndUnlockTest.php tests/Feature/RkaApprovedExcelExportTest.php tests/Feature/RkaAuditFixesTest.php tests/Feature/RkaActivityLogTest.php tests/Feature/Development --compact
```
Hasil: **32 passed (361 assertions)**, termasuk:
- `NativeApprovedRkaImportTest`: 4 passed — memverifikasi:
  - Import native `05_RKA_2026-2027_UPT_ICT.xlsx`: 10 item Section B (`SHARED_COST`), `destination_unit_id = null`, `adjusted_by = null`, `target_unit_id` terpetakan ke `P3M` (2), `UPT-MEDIA` (3), `PRODI-TIF` (3), `PRODI-ABI` (2), serta `imported_rka_item_id` terisi dan `is_imported = true` (10); serta `rka_headers.pagu_budget = 156583000.00` (berasal dari `RINGKASAN!C6`).
  - Visibilitas lintas unit `APPROVED` vs `DRAFT` melalui `RkaAdjustment::visibleToUser()` dan `RkaAdjustmentPolicy::view()`.
  - Import native `10_RKA_2026-2027_P3M.xlsx` (`rka_headers.pagu_budget = 496090000.00` dari `RINGKASAN!C6`) dan `19_RKA_2026-2027_Prodi_TIF.xlsx` (`rka_headers.pagu_budget = 728338000.00` dari `RINGKASAN!C6`).
  - Pemblokiran import apabila nilai `Usulan awal unit` pada worksheet `RINGKASAN` kosong/tidak terbaca tanpa menebak `pagu_budget`.
- Seluruh test regresi (`ApprovedRkaImportAndUnlockTest`, `RkaApprovedExcelExportTest`, `RkaAuditFixesTest`, `RkaActivityLogTest`, `Development\RkaDataCleanupTest`) lulus tanpa kegagalan (total **33 passed, 386 assertions**).

---

## 5. Mapping Ringkasan dan Pagu Budget

### 5.1 Audit Sumber Nilai Excel Asli

Sebelum perbaikan, `ApprovedRkaImportService` mengisi `rka_headers.pagu_budget` dengan `$preview['summary']['total_proposed']` (agregasi worksheet `BELANJA` / `REKAP`), sehingga nilainya berbeda dengan **Usulan Awal Unit** pada workbook resmi. Padahal di `RkaReviewService`, `$record->pagu_budget` digunakan sebagai **Usulan Awal Unit** (`$usulanAwal = (float) $record->pagu_budget`).

Hasil audit langsung pada tiga workbook resmi di `data-rka/`:

| Workbook | Worksheet | Label Sumber (`B6`) | Koordinat Nilai (Merged) | Nilai Asli `Usulan Awal Unit` | Total `BELANJA` Usulan (`RINGKASAN!C11`) | Total Disetujui (`RINGKASAN!C12`) | Nilai Disimpan ke `rka_headers.pagu_budget` |
|---|---|---|---|---|---|---|---|
| `05_RKA_2026-2027_UPT_ICT.xlsx` | `RINGKASAN` | `Usulan awal unit` | `C6` (`C6:D6`) | `156583000` | `174202000` | `173902000` | `156583000.00` |
| `10_RKA_2026-2027_P3M.xlsx` | `RINGKASAN` | `Usulan awal unit` | `C6` (`C6:D6`) | `496090000` | `408500000` | `340600000` | `496090000.00` |
| `19_RKA_2026-2027_Prodi_TIF.xlsx` | `RINGKASAN` | `Usulan awal unit` | `C6` (`C6:D6`) | `728338000` | `535254000` | `346464000` | `728338000.00` |

> **Catatan:** Pada `IDENTITAS!B13` ("Pagu Anggaran Unit (Rp)"), `UPT_ICT` dan `P3M` bernilai kosong (`PAGU BELUM DIISI`), sedangkan `Prodi_TIF` berisi `346500000` (batas pagu anggaran persetujuan, bukan Usulan Awal Unit). Oleh karena itu, sumber kebenaran `rka_headers.pagu_budget` adalah baris `Usulan awal unit` pada worksheet `RINGKASAN`.

### 5.2 Aturan Parsing & Penyimpanan

1. `NativeRkaWorkbookParser::parseRingkasan()` mencari baris dengan label `Usulan awal unit` pada kolom `B` worksheet `RINGKASAN` dan membaca nilai numerik pada kolom `C` (dengan fallback `D` jika `C` kosong) serta mencatat koordinat sumbernya (`RINGKASAN!C6`).
2. Jika `usulan_awal_unit` bernilai `null` atau `< 0`, parser menambahkan warning dan fatal error sehingga preview memblokir import tanpa mengisi `0` atau nilai tebakan dari `BELANJA`.
3. `ApprovedRkaImportService::resolvePaguBudgetForImport()` mengambil `pagu_awal_unit` untuk format `native` dan menyimpannya ke `rka_headers.pagu_budget` di dalam transaksi `DB::transaction` pada `executeImport()` maupun `executeReimport()`.
4. Tidak ada observer, model event, maupun sinkronisasi `DepartmentBudget` yang menimpa `rka_headers.pagu_budget` saat import.
5. Preview UI (`import-approved-rka-page.blade.php`) menampilkan **Pagu Awal Unit (Sumber Excel)** beserta sumber worksheet/cell dan status validasi pagu secara terpisah dari **Total Item BELANJA Usulan/Disetujui** dan **Ringkasan Penyesuaian Section A/B**.
