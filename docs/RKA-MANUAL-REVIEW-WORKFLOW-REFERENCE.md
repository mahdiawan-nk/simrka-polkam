# Referensi Workflow Review RKA Manual & Backup `rka_adjustments`

Dokumen ini mendokumentasikan hasil analisis data aktual workflow review RKA manual pada `data-rka/rka_adjustments_20261115_132958.csv` serta implementasi `RkaReviewService` sebagai acuan semantik bisnis untuk **Native Approved RKA Import** dan **Cross-Unit Reconciliation**.

---

## 1. Temuan dari Backup Database (`rka_adjustments_20261115_132958.csv`)

Total terdapat **123 record** hasil workflow review manual:

| `section_type` | `action_type` | Jumlah Record | `destination_unit_id` | `target_unit_id` | Pasangan Section B |
|---|---|---:|---|---|---|
| `A` | `MOVED` | 58 | Terisi (unit tujuan) | `NULL` | Wajib ada (58 record `B / MOVED`) |
| `B` | `MOVED` | 58 | `NULL` | Terisi (unit asal) | Berpasangan dengan `A / MOVED` |
| `A` | `REJECTED` | 5 | `NULL` | `NULL` | **Tidak ada** |
| `A` | `BUDGETED_ELSEWHERE` | 2 | `NULL` | `NULL` | **Tidak ada** |

Temuan kunci dari CSV:
1. Seluruh 58 pasangan `A / MOVED` <-> `B / MOVED` terhubung menggunakan `rka_item_id` yang sama (contoh: `id=1` di `rka_header_id=114` berpasangan dengan `id=2` di `rka_header_id=119` pada `rka_item_id=133`, `Kertas HVS A4`).
2. `approved_amount` pada `A / MOVED` bernilai `0.00`, sedangkan pada `B / MOVED` bernilai nominal yang disetujui (contoh `65000.00`).
3. `imported_rka_item_id` hanya terisi jika item Section B sudah ditarik menjadi `RkaItem` di unit pengelola (`is_imported = 1`, contoh `License OS` -> `imported_rka_item_id = 570`), dan sering bernilai `NULL` (`is_imported = 0`). Jadi `imported_rka_item_id` **bukan** kunci pasangan utama.
4. `REJECTED` (5 record) dan `BUDGETED_ELSEWHERE` (2 record) berdiri sendiri di Section A tanpa Section B.

---

## 2. Pemetaan `action_type` ke Section (`RkaReviewService` & Native Import)

| `action_type` | Section | Membutuhkan Pasangan Section B? | Memicu Rekonsiliasi Antarunit? | Makna Bisnis |
|---|---|---|---|---|
| `MOVED` | `A` (unit asal) & `B` (unit pengelola) | **Ya** | **Ya** | Item dipindahkan dari unit asal ke unit pengelola |
| `SHARED_COST` | `B` (unit pengelola) | **Ya** (dengan `A / MOVED` unit asal jika ada) | **Ya** (bila `target_unit_id` terisi) | Pos yang dikelola unit pengelola untuk unit lain pada Excel Native |
| `PENDING` | `A` saja | **Tidak** | **Tidak** | Item ditunda dan tetap menjadi tanggung jawab unit asal |
| `REJECTED` | `A` saja | **Tidak** | **Tidak** | Item ditolak tanpa pemindahan ke unit lain |
| `BUDGETED_ELSEWHERE` | `A` saja | **Tidak** | **Tidak** | Sudah dianggarkan di sumber/unit lain tanpa membuat alokasi Section B |
| `REMOVED` | `A` saja | **Tidak** | **Tidak** | Item dihapus saat review (`sectionAStatuses` di `RkaReviewService`) |

---

## 3. Semantik `destination_unit_id` dan `target_unit_id`

- **Section A (`section_type = 'A'`)**:
  - `destination_unit_id`: ID `departments` unit tujuan/pengelola (hanya diisi untuk `MOVED` jika diketahui secara eksplisit; untuk `PENDING`, `REJECTED`, `BUDGETED_ELSEWHERE`, `REMOVED`, atau "pos pusat" yang tidak menyebut unit spesifik -> `NULL`).
  - `target_unit_id`: selalu `NULL`.
- **Section B (`section_type = 'B'`)**:
  - `destination_unit_id`: selalu `NULL`.
  - `target_unit_id`: ID `departments` **unit asal** / penerima manfaat.

---

## 4. Perbedaan Workflow Manual vs Native Approved RKA Import

| Aspek | Workflow Manual (`RkaReviewService`) | Native Approved RKA Import (`PHASE 3`) |
|---|---|---|
| Kunci Pasangan | `rka_item_id` yang sama pada DB saat reviewer memindahkan item | `rka_adjustment_matches` (`source_adjustment_id` <-> `counterpart_adjustment_id`) berbasis `schedule_id`, unit asal/tujuan, `account_id`, uraian ternormalisasi, dan atribut nilai/volume/satuan |
| Urutan Pembentukan | Section A dan Section B dibuat bersamaan dalam satu transaksi review | Unit dapat mengimpor secara terpisah; jika unit lawan belum import -> `WAITING_COUNTERPART`, lalu otomatis `MATCHED` saat unit lawan mengimpor |
| `RkaAdjustment.action_type` untuk `PENDING` | Item ditunda milik unit asal | Tetap bermakna item ditunda unit asal — **tidak pernah** dipakai untuk antrean rekonsiliasi |
| `BELANJA` vs `DAFTAR PENYESUAIAN` | `RkaItem` berasal dari pengajuan unit | `BELANJA` = item Approved milik unit sendiri; `DAFTAR PENYESUAIAN` = satu-satunya sumber pembentukan relasi antarunit |

---

## 5. Desain Rekonsiliasi & Hasil Pengujian

- **Hanya `MOVED` (Section A) dan `SECTION_B` dengan `target_unit_id`** yang diproses oleh `CrossUnitItemMatcher::requiresCrossUnitReconciliation()` dan `RkaReconciliationService`.
- Record `PENDING`, `REJECTED`, `BUDGETED_ELSEWHERE`, dan `REMOVED` di-skip oleh engine rekonsiliasi sehingga tidak pernah membentuk `WAITING_COUNTERPART`, tidak membuat Section B palsu, dan tidak membuat alokasi `DepartmentBudget` palsu.
- Pengujian pada `tests/Feature/CrossUnitReconciliationTest.php` memverifikasi skenario CSV (`MOVED` berpasangan `MATCHED`, sementara `PENDING`, `REJECTED`, `BUDGETED_ELSEWHERE`, `REMOVED` tetap Section A saja dengan `0` match dan `0` perubahan `DepartmentBudget`).
