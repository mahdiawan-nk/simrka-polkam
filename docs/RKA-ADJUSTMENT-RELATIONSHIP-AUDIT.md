# Audit Relasi `RkaAdjustment` — Workflow Manual vs Native Approved RKA Import

## 1. Root Cause Masalah pada Native Import Sebelumnya

Pada implementasi awal `NativeRkaWorkbookParser` dan `ApprovedRkaImportService`:
- Kolom **`Unit Asal`** pada worksheet `DAFTAR PENYESUAIAN` Bagian B (`P3M`, `UPT Media Center`, `Prodi D3 TIF`, `Prodi D4 ABI`) hanya disimpan sebagai string `unit_asal` dan tidak dipetakan ke `departments.id`.
- `buildAdjustmentPayload()` membaca `$row['target_department_id'] ?? null`, sehingga `target_unit_id` selalu `NULL` pada hasil Native Import.
- `rka_item_id` dan `imported_rka_item_id` tidak dicocokkan terhadap `RkaItem` yang sudah ada.

## 2. Semantik Kolom `rka_adjustments` pada Workflow Manual (`RkaReviewService` & `AddAdjustmentForm`)

Berdasarkan audit `App\Services\RkaReviewService`, `AddAdjustmentForm`, dan `App\Models\RkaHeader`:

| Kolom | Section A (`SECTION_A`) | Section B (`SECTION_B`) |
|---|---|---|
| `rka_header_id` | ID `RkaHeader` unit yang mengusulkan item | ID `RkaHeader` unit pengelola pos anggaran (mis. `UPT ICT`) |
| `rka_item_id` | ID `RkaItem` asal yang ditinjau (jika masih ada/unik) | ID `RkaItem` asal di unit asal (jika ada di DB & unik) |
| `destination_unit_id` | ID `Department` unit tujuan pengalihan (`MOVED` saja; `NULL` jika tidak diketahui/bukan `MOVED`) | Selalu `NULL` |
| `target_unit_id` | Selalu `NULL` | ID `Department` unit asal / penerima manfaat (`Unit Asal` pada `DAFTAR PENYESUAIAN` Bagian B) |
| `imported_rka_item_id` | Selalu `NULL` | ID `RkaItem` operasional di `RkaHeader` pengelola hasil `importAdjustmentToItem()` atau pencocokan unik `MATCH_UNIQUE` |
| `is_imported` | `false` | `true` hanya jika sudah terhubung ke `imported_rka_item_id` yang nyata; `false` jika belum/histori saja |
| `adjusted_by` | ID user reviewer (diisi `NULL` pada Native Import karena Excel tidak memuat user ID reviewer) | ID user reviewer (`NULL` pada Native Import) |

## 3. Aturan NULL & Anti-Relasi Palsu

1. **Section A (`P3M`, `Prodi TIF`):** Item pada `DAFTAR PENYESUAIAN` Bagian A sudah dipindah/tidak dianggarkan di unit asal, sehingga tidak ada di sheet `Belanja` unit asal (`rka_item_id = NULL` jika `MATCH_NONE`). Status `Dipindah ke pos pusat` tanpa nama unit tujuan spesifik pada kolom tujuan **tidak boleh** diisi ke `destination_unit_id` secara spekulatif (`destination_unit_id = NULL`).
2. **Section B (`UPT ICT`):** Kolom `Unit Asal` (`P3M`, `UPT Media Center`, `Prodi D3 TIF`, `Prodi D4 ABI`) wajib dipetakan secara deterministik ke `Department` melalui `DepartmentResolver` dan disimpan ke `target_unit_id`. Jika `Unit Asal` tidak ditemukan atau ambigu, preview menampilkan `UNIT BELUM DITEMUKAN` dan import diblokir.
3. **Pencocokan Item (`imported_rka_item_id`):** Pada Bagian B workbook `UPT ICT`, kolom `E`/`F` menggunakan formula referensi langsung ke sheet `Belanja Barang dan Jasa` (mis. `='Belanja Barang dan Jasa'!F36`). Jika item di baris referensi tersebut ditemukan secara unik (`MATCH_UNIQUE`) di `RkaItem` milik `RkaHeader` pengelola, `imported_rka_item_id` diisi dan `is_imported = true` (mencegah duplikasi jika kelak dijalankan `importAdjustmentToItem()`). Jika tidak unik atau tidak ditemukan, dibiarkan `NULL` dan `is_imported = false`.
