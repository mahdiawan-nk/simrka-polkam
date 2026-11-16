# Integrasi Item Daftar Penyesuaian Section A ke Sheet RKA / Belanja

**Tanggal:** 2026-11-16  
**Branch:** `feature/rka-approved-excel-import`

---

## 1. Root Cause

Pada dokumen Approved RKA Excel asli (`10_RKA_2026-2027_P3M.xlsx`, `19_RKA_2026-2027_Prodi_TIF.xlsx`, dll.), item usulan yang mengalami penyesuaian (`MOVED`, `PENDING`, `REJECTED`, `REMOVED`, `BUDGETED_ELSEWHERE`) sering kali tidak lagi tercantum pada worksheet `BELANJA` dan hanya berada di worksheet `DAFTAR PENYESUAIAN` Section A.

Sebelumnya, `ApprovedRkaImportService` hanya membuat `RkaItem` dari worksheet `BELANJA`. Akibatnya, ketika baris Section A tidak ditemukan di `BELANJA` maupun di `rka_items` Draft:
- `rka_adjustments` tetap dibuat dengan `rka_item_id = NULL` dan `rka_indicator_id = NULL`.
- Tidak ada `RkaItem` yang dibuat di `rka_items` untuk baris Section A tersebut.
- Halaman Review Sheet RKA (`ReviewRkaPage` / `RkaReviewService::calculateBudgetHierarchy()`) yang mengambil data dari `rka_items` tidak dapat menampilkan item Section A tersebut.

---

## 2. Perbedaan Section A dan Section B

| Aspek | Section A | Section B |
|---|---|---|
| Makna Bisnis | Usulan asli milik unit asal yang dipindah/ditunda/ditolak/tidak dianggarkan. | Pos yang dikelola unit ini untuk unit lain / tambahan review. |
| Pembuatan `RkaItem` | **Ya** (jika belum ada di `rka_items` unit asal dan kandidat tidak ambigu). | **Tidak pernah** membuat `RkaItem` baru (hanya menautkan ke item pengelola eksisting). |
| Kepemilikan `rka_header_id` | Tetap milik unit asal (tidak pernah dipindah ke unit tujuan). | Tetap milik unit pengelola. |
| Kolom Unit di `rka_adjustments` | `destination_unit_id` (khusus `MOVED`). | `target_unit_id` (unit asal/sumber). |
| `approved_total` / `approved_amount` | `0.00` (tidak masuk alokasi `DepartmentBudget` unit asal). | Mengikuti data Excel (`BELANJA` / `DAFTAR PENYESUAIAN`). |

---

## 3. Alur Pembuatan & Identifikasi `RkaItem` Section A

Implementasi berada pada `ApprovedRkaImportService::ensureSectionAOriginItemId()` dan `resolveIndicatorForSectionAItem()`:

1. **Cek `$itemIdMap[$originRef]`** (bila baris Section A sudah cocok dengan item di worksheet `BELANJA` pada file yang sama).
2. **Cek `rka_items` berdasarkan `source_ref_id = $row['adj_ref_id']`** pada `rka_header_id` target untuk menjamin idempotency saat import berulang.
3. **Cek `rka_items` eksisting** pada `rka_header_id` target menggunakan identitas gabungan (`account_id` + `description` ternormalisasi + `proposed_total` dalam toleransi `< 1.0`):
   - `1` kandidat → gunakan item tersebut (`rka_item_id` terhubung, status diselaraskan).
   - `> 1` kandidat (`-1` / `MATCH_AMBIGUOUS`) → **jangan pilih acak dan jangan buat item duplikat** (`rka_item_id = NULL`).
   - `0` kandidat → buat `RkaItem` baru pada `rka_header_id` unit asal dengan `source_ref_id = $adjRef`, `rka_indicator_id` dari `resolveIndicatorForSectionAItem()`, `proposed_volume`, `unit_symbol`, `unit_price`, `approved_volume = 0`, `approved_total = 0`, `status = $actionType`, `is_trimmed = true`, `is_imported = false`.

---

## 4. Mapping Status & Dampak Perhitungan

| Status Excel | `rka_adjustments.action_type` | `rka_items.status` | `approved_total` | `DepartmentBudget` |
|---|---|---|---|---|
| Dipindahkan | `MOVED` | `MOVED` | `0.00` | Tidak dihitung |
| Pending | `PENDING` | `PENDING` | `0.00` | Tidak dihitung |
| Ditolak | `REJECTED` | `REJECTED` | `0.00` | Tidak dihitung |
| Disesuaikan Lalu Ditolak | `REJECTED_WITH_ADJUSTMENT` | `REJECTED_WITH_ADJUSTMENT` | `0.00` | Tidak dihitung |
| Dihapus / Dipangkas | `REMOVED` | `REMOVED` | `0.00` | Tidak dihitung |
| Dianggarkan di Unit Lain | `BUDGETED_ELSEWHERE` | `BUDGETED_ELSEWHERE` | `0.00` | Tidak dihitung |

- Pada `ReviewRkaPage`, item `REJECTED` dan `REJECTED_WITH_ADJUSTMENT` tampil di mode **Item Final** maupun **Semua Item**; item `MOVED`, `PENDING`, `REMOVED`, dan `BUDGETED_ELSEWHERE` tampil di mode **Semua Item** (`showAllItems = true`, default untuk Finance/Direktur dan dapat diaktifkan oleh Pemohon via tombol "Semua Item").
- `DepartmentBudget` (`syncDepartmentBudgetForApprovedHeader()`) hanya menjumlahkan `RkaItem` berstatus `APPROVED`, `ACCEPTED`, `REDUCED`, `TRIMMED` dengan `approved_total > 0`, sehingga item Section A tidak pernah menimbulkan alokasi ganda.

---

## 5. Hasil Pengujian

Seluruh suite Feature Import Approved RKA diuji pada MySQL `erka` (`DatabaseTransactions`):
- `ApprovedRkaAdjustmentConsistencyTest` (termasuk skenario Section A tampil di Sheet RKA/Belanja, item ambigu tidak salah ditautkan, Section B tidak menduplikasi item, dan idempotency)
- `NativeApprovedRkaImportTest` (`05_RKA_2026-2027_UPT_ICT.xlsx`: `33` item; `10_RKA_2026-2027_P3M.xlsx`: `53` item = `30` BELANJA + `23` Section A; `19_RKA_2026-2027_Prodi_TIF.xlsx`: `106` item = `69` BELANJA + `37` Section A)
- `DraftRkaApprovedImportTest`, `AdaptiveDaftarPenyesuaianImportTest`, `ManualUnitAssignmentImportTest`, `CrossUnitReconciliationTest`, `ApprovedRkaImportAndUnlockTest`, `RkaApprovedExcelExportTest`.
