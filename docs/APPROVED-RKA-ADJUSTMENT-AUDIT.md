# Audit Implementasi Import Approved RKA — Section A & Section B

**Tanggal Audit:** 2026-11-16  
**Branch:** `feature/rka-approved-excel-import`  
**Tujuan:** Memastikan penyimpanan `rka_items` dan `rka_adjustments` (Section A & Section B) pada fitur Import Approved RKA konsisten dengan keputusan bisnis, proses review manual (`RkaReviewService`), dan integritas relasi antarunit.

---

## 1. Struktur Database Aktual (Verified from MySQL `erka`)

### 1.1 `rka_items`
- **Primary Key:** `id`
- **Referensi & Relasi:**
  - `source_ref_id` (`varchar(64)`, nullable, indexed `idx_rka_items_source_ref`)
  - `rka_header_id` (`bigint unsigned`, FK → `rka_headers.id` ON DELETE CASCADE)
  - `rka_indicator_id` (`bigint unsigned`, FK → `rka_indicators.id` ON DELETE CASCADE)
  - `account_id` (`bigint unsigned`, FK → `accounts.id` ON DELETE CASCADE)
- **Nilai & Status:**
  - `form_type` (`varchar(10)`), `description` (`text`), `group_header` (`varchar(255)`, nullable)
  - `proposed_volume` (`decimal(12,2)`), `unit_symbol` (`varchar(50)`), `unit_price` (`decimal(15,2)`)
  - `proposed_total` (`decimal(15,2)` STORED GENERATED `proposed_volume * unit_price`)
  - `approved_volume` (`decimal(12,2)`, nullable), `approved_total` (`decimal(15,2)`, nullable)
  - `status` (`varchar(30)`, default `'PENDING'`), `is_trimmed` (`tinyint(1)`), `price_trimed` (`decimal(15,2)`), `is_imported` (`tinyint(1)`), `review_notes` (`text`, nullable)

### 1.2 `rka_adjustments`
- **Primary Key:** `id`
- **Referensi & Relasi:**
  - `source_ref_id` (`varchar(64)`, nullable, indexed `idx_rka_adjustments_source_ref`)
  - `rka_header_id` (`bigint unsigned`, FK → `rka_headers.id` ON DELETE CASCADE)
  - `rka_indicator_id` (`bigint unsigned`, nullable, FK → `rka_indicators.id` ON DELETE SET NULL)
  - `rka_item_id` (`bigint unsigned`, nullable, FK → `rka_items.id` ON DELETE SET NULL)
  - `imported_rka_item_id` (`bigint unsigned`, nullable, FK → `rka_items.id` ON DELETE SET NULL)
  - `account_id` (`bigint unsigned`, nullable, FK → `accounts.id` ON DELETE SET NULL)
  - `destination_unit_id` (`bigint unsigned`, nullable, FK → `departments.id` ON DELETE SET NULL) — **Section A: Unit tujuan pemindahan**
  - `target_unit_id` (`bigint unsigned`, nullable, FK → `departments.id` ON DELETE SET NULL) — **Section B: Unit asal/sumber usulan**
  - `adjusted_by` (`bigint unsigned`, nullable, FK → `users.id` ON DELETE SET NULL)
- **Atribut Adjustment:**
  - `section_type` (`char(1)`: `'A'` atau `'B'`), `section_name` (`varchar(255)`)
  - `description` (`text`), `proposed_volume` (`decimal(12,2)`), `unit_symbol` (`varchar(50)`), `unit_price` (`decimal(15,2)`)
  - `proposed_amount` (`decimal(15,2)`), `approved_amount` (`decimal(15,2)`)
  - `action_type` (`varchar(50)`, default `'MOVED'`), `notes` (`text`, nullable), `is_imported` (`tinyint(1)`, default `0`)

### 1.3 Data Aktual di Database Development (`rka_adjustments`)
| `section_type` | `action_type` | `is_imported` | Jumlah | `rka_item_id != NULL` | `imported_rka_item_id != NULL` | `destination_unit_id != NULL` | `target_unit_id != NULL` |
|---|---|---:|---:|---:|---:|---:|---:|
| A | `BUDGETED_ELSEWHERE` | 0 | 2 | 0 | 0 | 0 | 0 |
| A | `MOVED` | 0 | 58 | 0 | 0 | 58 | 0 |

---

## 2. Jawaban 10 Pertanyaan Audit Wajib

1. **Bagaimana item Section A saat ini disimpan?**
   - `NativeRkaWorkbookParser::parseDaftarPenyesuaian()` membaca blok `A.` dan memetakan status teks via `mapAdjustmentActionType()` (`MOVED`, `BUDGETED_ELSEWHERE`, `REJECTED`, `REMOVED`, `PENDING` — **belum** memetakan `REJECTED_WITH_ADJUSTMENT`).
   - Pencocokan item asal hanya memeriksa `$belanjaRows` Excel (`kode_rekening` + `uraian` ternormalisasi + `abs(nilai_usulan - jumlah_usulan) < 1.0`) bila unik (`MATCH_UNIQUE`), menghasilkan `origin_item_id`.
   - `ApprovedRkaImportService::buildAdjustmentPayload()` menyimpan `section_type = 'A'`, `rka_item_id` dari `$itemIdMap[$originRef]`, `destination_unit_id` khusus `ACTION_MOVED`, `target_unit_id = null`, **tetapi** `rka_indicator_id` tidak diisi dan `approved_amount` hanya dipaksa `0` untuk `PENDING` (padahal di review manual seluruh status Section A memiliki `approved_amount = 0`).

2. **Bagaimana item Section B saat ini disimpan?**
   - `NativeRkaWorkbookParser` meng-hardcode `action_type = RkaAdjustment::ACTION_SHARED_COST` untuk seluruh baris Section B tanpa membaca status/keterangan Excel (`MOVED`, `ADDED_BY_REVIEWER`, `SHARED_COST`).
   - Jika sel `Jumlah Usulan` Section B berupa formula referensi ke sheet `BELANJA` (mis. `='Belanja Barang dan Jasa'!F36`), parser mengisi `imported_item_id` dan menyetel `origin_item_id = null`.
   - Di `buildAdjustmentPayload()`, `section_type = 'B'`, `target_unit_id` diisi dari `target_department_id` (unit asal/sumber), `destination_unit_id = null`, `rka_item_id = null`, dan `imported_rka_item_id` diisi dari `$itemIdMap[$importedRef]`.

3. **Apakah Section A membuat item baru atau menggunakan item asal?**
   - Import tidak membuat `RkaItem` baru dari baris Section A. Jika item asal tersedia di `BELANJA` Excel (atau di `rka_items` RKA Draft), adjustment seharusnya terhubung ke `rka_item_id` item asal tersebut. Jika item asal sudah tidak ada di `BELANJA` (karena dipindah/ditolak pada dokumen final), `rka_item_id` dibiarkan `null` (tidak membuat relasi/item palsu).

4. **Apakah Section B membuat duplikasi item?**
   - `persistAdjustments()` / `persistAdjustmentsForDraft()` tidak membuat `RkaItem` baru dari baris Section B. Item belanja tetap bersumber dari sheet `BELANJA` milik RKA pengelola. Namun, jika `rka_items` pada RKA pengelola sudah memiliki item belanja tersebut dan tidak terdeteksi lewat formula, `rka_item_id` / `imported_rka_item_id` tetap kosong.

5. **Apakah `rka_item_id` sudah terisi dengan benar?**
   - **Belum optimal:**
     - Pada Section A: belum mengecek `RkaItem` eksisting di database pada `rka_header_id` target ketika tidak ada di `belanjaRows` Excel.
     - Pada Section B: selalu `NULL` saat import native karena `origin_item_id` disetel `null`, bahkan ketika item belanja pengelola sudah teridentifikasi unik via formula referensi sheet `BELANJA` atau pencocokan unik (`kode_rekening` + `uraian` + `nilai`).

6. **Apakah `imported_rka_item_id` digunakan dengan tepat?**
   - Pada review manual (`RkaReviewService::importAdjustmentToItem()`), `imported_rka_item_id` menyimpan ID `RkaItem` realisasi pada unit pengelola (`is_imported = true`). Pada `RkaReconciliationService`, relasi dibaca sebagai `$adj->imported_rka_item_id ?? $adj->rka_item_id`. Saat item sudah teridentifikasi di `BELANJA` unit pengelola pada import Excel, mengisi `rka_item_id` sekaligus `imported_rka_item_id` (ketika berasal dari realisasi Section B di unit pengelola) menjaga kompatibilitas dengan `RkaReviewService` dan `RkaReconciliationService` tanpa membuat duplikasi item.

7. **Mengapa `destination_unit_id` dan `target_unit_id` masih NULL pada data tertentu?**
   - Untuk Section A non-`MOVED` (`BUDGETED_ELSEWHERE`, `REJECTED`, `REJECTED_WITH_ADJUSTMENT`, `REMOVED`, `PENDING`), `destination_unit_id = NULL` dan `target_unit_id = NULL` adalah **benar** sesuai aturan bisnis (tidak ada unit tujuan pemindahan).
   - Untuk Section A `MOVED`, `destination_unit_id` wajib terisi (baik dari Excel maupun pemilihan manual).
   - Untuk Section B, `target_unit_id` menyimpan unit asal/sumber (atau `NULL` hanya bila pos internal `ADDED_BY_REVIEWER` yang memang tidak memiliki unit sumber eksternal).

8. **Apakah proses import menggunakan mekanisme yang sama dengan review manual?**
   - **Belum sepenuhnya:**
     - `RkaReviewService` mengisi `rka_indicator_id` pada Section A dari `$item->rka_indicator_id`, menyetel `approved_amount = 0` untuk seluruh aksi Section A (`MOVED`, `REJECTED`, `REJECTED_WITH_ADJUSTMENT`, `REMOVED`, `BUDGETED_ELSEWHERE`, `PENDING`), dan menggunakan `updateOrCreate(['rka_header_id', 'rka_item_id', 'section_type'], ...)` untuk mencegah duplikasi.
     - `NativeRkaWorkbookParser` belum memetakan `REJECTED_WITH_ADJUSTMENT` di Section A serta meng-hardcode seluruh Section B menjadi `ACTION_SHARED_COST` (mengabaikan `MOVED` dan `ADDED_BY_REVIEWER`).

9. **Apakah status item pada `rka_items` konsisten dengan `rka_adjustments`?**
   - Jika item asal Section A ada pada `rka_items` unit asal, status `rka_items.status` saat import belum otomatis diselaraskan dengan `action_type` Section A (`MOVED`, `REJECTED`, `REJECTED_WITH_ADJUSTMENT`, `REMOVED`, `BUDGETED_ELSEWHERE`, `PENDING`).

10. **Apakah proses import dapat menghasilkan adjustment ganda?**
    - `persistAdjustmentsForDraft()` sudah mengecek `source_ref_id`, namun `persistAdjustments()` (mode `INITIAL`) masih memanggil `RkaAdjustment::create()` langsung tanpa cek idempotency `source_ref_id` maupun `rka_item_id + section_type`.

---

## 3. Rencana Perubahan (Tanpa Migration Baru)

Schema tabel `rka_items` dan `rka_adjustments` sudah memadai; tidak diperlukan migration baru.

1. **`NativeRkaWorkbookParser.php`**:
   - Perbarui `mapAdjustmentActionType()` untuk Section A agar mendukung `REJECTED_WITH_ADJUSTMENT`, `MOVED`, `BUDGETED_ELSEWHERE`, `REJECTED`, `REMOVED`, `PENDING`.
   - Tambahkan `mapSectionBActionType()` agar Section B membaca kolom `Status` / `Keterangan` (`MOVED`, `ADDED_BY_REVIEWER`, `SHARED_COST`) daripada selalu hardcode `SHARED_COST`.
   - Pada Section B: jika formula referensi tidak ada (nilai literal/perhitungan lain), lakukan pencocokan unik (`kode_rekening` + `uraian` ternormalisasi + `nilai_usulan` / `nilai_disetujui` dalam toleransi `< 1.0`) terhadap `belanjaRows` unit pengelola. Bila unik, isi `imported_item_id` (dan `origin_item_id` tidak perlu terpisah dari RKA lain; di `buildAdjustmentPayload()` hubungkan `rka_item_id` ke item pengelola yang teridentifikasi tanpa membuat item baru). Bila ambigu (`> 1`) atau tidak ketemu (`0`), biarkan `null` dan beri status `MATCH_AMBIGUOUS` / `MATCH_NONE` tanpa menebak.
2. **`ApprovedRkaImportService.php`**:
   - Pada `buildAdjustmentPayload()`:
     - Isi `rka_indicator_id` dari `$item->rka_indicator_id` (jika `rka_item_id` ditemukan).
     - Pastikan `approved_amount = 0.0` untuk seluruh aksi Section A (`MOVED`, `REJECTED`, `REJECTED_WITH_ADJUSTMENT`, `REMOVED`, `BUDGETED_ELSEWHERE`, `PENDING`), konsisten dengan `RkaReviewService`.
     - Pastikan `destination_unit_id` hanya terisi pada Section A `ACTION_MOVED`, dan `target_unit_id` hanya terisi pada Section B.
     - Untuk Section B, jika item belanja unit pengelola teridentifikasi unik (`$importedItemId`), isi baik `rka_item_id` maupun `imported_rka_item_id` ke ID `RkaItem` tersebut (tanpa membuat item baru), dan `is_imported = true`.
   - Pada `persistAdjustments()` & `persistAdjustmentsForDraft()`:
     - Gunakan idempotency berdasarkan `rka_header_id` + `source_ref_id` (dan bila `rka_item_id !== null`, gunakan `updateOrCreate(['rka_header_id', 'rka_item_id', 'section_type'], ...)` setara `RkaReviewService`).
     - Untuk Draft/Reimport: jika Section A belum punya `origin_item_id` dari `belanjaRows` Excel, lakukan pencocokan unik terhadap `RkaItem` eksisting di `rka_header_id` tersebut (`account_id` + `description` ternormalisasi + `proposed_total`). Jika ditemukan unik, hubungkan `rka_item_id` dan selaraskan `rka_items.status` dengan `action_type` Section A (`approved_volume = 0`, `approved_total = 0` untuk item yang tidak dianggarkan di unit asal) **tanpa menghapus item asal**.
3. **Verifikasi Tampilan Review RKA (`adjustment-sections.blade.php`)**:
   - Pastikan Section A menampilkan No, Kode, Uraian, Vol, Satuan, Harga, Jumlah, Status/Aksi, Unit Tujuan (`destinationUnit?->name ?? '—'`), Keterangan.
   - Pastikan Section B menampilkan No, Kode, Uraian, Unit Asal/Sumber (`targetUnit?->name` / `code` — jangan hardcode nama unit), Vol, Satuan, Harga, Usulan, Disetujui, Keterangan.
4. **Pengujian Skenario 1–8 (`ApprovedRkaAdjustmentConsistencyTest.php`)**:
   - Uji seluruh 8 skenario wajib pada data P3M, Prodi TIF, dan UPT ICT.
