# Dokumentasi Fitur Export Approved RKA to Excel

**Repository:** `mahdiawan-nk/simrka-polkam`  
**Branch:** `feature/rka-approved-excel-import`  
**Acuan Referensi:** `docs/RKA-EXCEL-REFERENCE-ANALYSIS.md` (`data-rka/05_RKA_2026-2027_UPT_ICT.xlsx`)

---

## 1. Tujuan Fitur

Menyediakan dokumen ekspor Excel (`.xlsx`) resmi dan siap cetak untuk dokumen RKA yang telah disahkan Direktur (`APPROVED`), dengan format visual, struktur hierarki anggaran, formula perhitungan, dan keterlacakan penyesuaian yang mengacu pada standar dokumen RKA Politeknik Kampar.

---

## 2. Persyaratan Export & Hak Akses (RBAC)

- **Status Dokumen:** Hanya `RkaHeader` dengan status `APPROVED` yang dapat diekspor (`DRAFT`, `SUBMITTED`, `IN_REVIEW`, `IN_APPROVED`, dan `REVISION_REQUIRED` ditolak di server).
- **Hak Akses:** Diverifikasi di server melalui `RkaHeaderPolicy::view()` (`View:RkaHeader` + organizational scope `canAccessRecord()`). Pengguna dari unit lain tanpa role global (`super_admin`, `direktur`, `keuangan`, `wadir_keuangan`) tidak dapat mengekspor RKA unit lain.
- **Read-Only:** Proses export tidak mengubah `rka_headers`, `rka_items`, `rka_adjustments`, maupun `department_budgets`.
- **Activity Log:** Setiap export yang berhasil dicatat satu kali melalui `RkaActivityLogService::log()` dengan event `RkaLog::ACTION_EXPORT_RKA` (`'rka.exported'`). Kegagalan validasi/ekspor tidak mencatat log sukses.
- **Nama File:** `RKA-APPROVED-{UNIT}-{TAHUN}.xlsx`.

---

## 3. Struktur Workbook & Fungsi Kedua Worksheet

Workbook terdiri dari **tepat 2 worksheet**:

1. **`DAFTAR PENYESUAIAN`**  
   Menyajikan seluruh catatan penyesuaian hasil review yang terbagi menjadi:
   - **Section A:** Usulan unit yang dipindah, ditolak, dihapus, atau ditunda (`MOVED`, `BUDGETED_ELSEWHERE`, `REJECTED_WITH_ADJUSTMENT`, `REJECTED`, `REMOVED`, `PENDING`).
   - **Section B:** Pos yang dikelola unit ini untuk unit lain atau tambahan saat review (`SHARED_COST`, `ADDED_BY_REVIEWER`).

2. **`BELANJA`**  
   Menyajikan seluruh item anggaran pada dokumen RKA dalam satu worksheet, dikelompokkan ke dalam 6 kelompok belanja resmi:
   - `5.1 Belanja Pegawai`
   - `5.2 Belanja Barang dan Jasa`
   - `5.3 Belanja Modal`
   - `5.4 Belanja Hibah`
   - `5.5 Belanja Pajak`
   - `2.1 Pembayaran Kewajiban Jangka Pendek`

   Kelompok belanja yang tidak memiliki item tidak ditampilkan.

---

## 4. Penjelasan Kolom & Mapping Database

### 4.1. Worksheet `DAFTAR PENYESUAIAN`

| Kolom | Sumber Database | Keterangan |
|---|---|---|
| `ADJ_REF_ID` | `rka_adjustments.id` | Technical ID stabil |
| `ORIGIN_ITEM_ID` | `rka_adjustments.rka_item_id` | Referensi item asal (nullable) |
| `IMPORTED_ITEM_ID` | `rka_adjustments.imported_rka_item_id` | Referensi item hasil import ke `rka_items` |
| `BAGIAN` | `rka_adjustments.section_type` | `A` atau `B` (`PENDING` masuk `A`) |
| `STATUS_PENYESUAIAN` | `rka_adjustments.action_type` | Salah satu dari 8 status resmi |
| `KODE_REKENING` | `accounts.code` | Kode rekening akun |
| `NAMA_REKENING` | `accounts.name` | Nama rekening akun |
| `URAIAN` | `rka_adjustments.description` | Uraian penyesuaian |
| `UNIT_ASAL` | `departments.name` | Unit asal pengusul |
| `UNIT_TUJUAN` | `departments.name` | Unit tujuan / penerima manfaat (`destinationUnit` / `targetUnit`) |
| `VOLUME` | `rka_adjustments.proposed_volume` | Volume usulan/penyesuaian |
| `SATUAN` | `rka_adjustments.unit_symbol` | Satuan volume |
| `HARGA_SATUAN` | `rka_adjustments.unit_price` | Harga satuan |
| `JUMLAH_USULAN` | `rka_adjustments.proposed_amount` | Nominal usulan |
| `JUMLAH_DISETUJUI` | `rka_adjustments.approved_amount` | Nominal disetujui |
| `KETERANGAN_REVIEWER` | `rka_adjustments.notes` | Catatan reviewer |
| `REVIEWER` | `users.name` | Nama reviewer (`adjustedBy`) |

### 4.2. Worksheet `BELANJA`

| Kolom | Sumber Database | Keterangan |
|---|---|---|
| `ITEM_REF_ID` | `rka_items.id` | Technical ID stabil |
| `KELOMPOK_BELANJA` | `rka_items.form_type` / `accounts.code` | Kelompok `5.1`..`5.5`, `2.1` |
| `PROGRAM_KEGIATAN` | `rka_indicators.program_name`, `activity_name` | Program & kegiatan terkait |
| `KODE_REKENING` | `accounts.code` | Kode rekening item |
| `NAMA_REKENING` | `accounts.name` | Nama rekening item |
| `GROUP_HEADER` | `accounts` (Level 2 parent) | Sub-kelompok akun Level 2 |
| `URAIAN_ITEM` | `rka_items.description` | Deskripsi item belanja |
| `VOL_USULAN` | `rka_items.proposed_volume` | Volume usulan awal |
| `SATUAN` | `rka_items.unit_symbol` | Satuan |
| `HARGA_SATUAN_USULAN` | `rka_items.unit_price` | Harga satuan usulan |
| `NILAI_USULAN` | Formula `=VOL_USULAN * HARGA_SATUAN_USULAN` | Nilai usulan |
| `VOL_DISETUJUI` | `rka_items.approved_volume` | Volume disetujui |
| `HARGA_SATUAN_DISETUJUI` | Turunan `approved_total / approved_volume` / `unit_price` | Harga satuan disetujui |
| `NILAI_DISETUJUI` | Formula `=VOL_DISETUJUI * HARGA_SATUAN_DISETUJUI` (atau nilai DB) | Nilai final disetujui |
| `STATUS_ITEM` | `rka_items.status` | `APPROVED`, `TRIMMED`, `REJECTED`, `MOVED`, `PENDING`, dll. |
| `ASAL_ITEM` | `rka_items.is_imported` | `IMPORT_SECTION_B` atau `USULAN_ASLI` |
| `KETERANGAN_REVIEW` | `rka_items.review_notes` | Catatan review |

---

## 5. Perhitungan Subtotal, Total & Format Dokumen

- **Subtotal Kelompok Belanja:** `=SUM(K{start}:K{end})` untuk nilai usulan dan `=SUM(N{start}:N{end})` untuk nilai disetujui.
- **Grand Total Belanja:** Penjumlahan langsung antar baris subtotal kelompok (`=K{sub1}+K{sub2}+...`), terhubung ke ringkasan atas (`C6` dan `C7`), sehingga bebas dari double counting.
- **Format & Pencetakan:** Orientasi Landscape, ukuran kertas A4, `FitToWidth = 1`, `FitToHeight = 0`, `PrintArea` otomatis mengikuti baris akhir, `FreezePane` pada baris header tabel `BELANJA`, serta palet warna resmi (`#BDD7EE`, `#D9D9D9`, `#E2EFDA`, `#FFF2CC`, `#DDEBF7`).

---

## 6. Cara Menggunakan Fitur

1. Buka menu **Manajemen RKA** (`RkaHeaderResource`) di Filament.
2. Pada baris RKA berstatus **APPROVED** yang berada dalam cakupan akses Anda, klik tombol **Export Excel**.
3. File `.xlsx` akan terunduh dengan nama `RKA-APPROVED-{UNIT}-{TAHUN}.xlsx`, dan aktivitas ekspor tercatat pada tab **Riwayat Aktivitas**.

---

## 7. Technical Reference untuk Pengembangan Import Berikutnya

- Gunakan `ITEM_REF_ID` (`BELANJA` kolom `A`) sebagai primary identifier item, serta `ADJ_REF_ID`, `ORIGIN_ITEM_ID`, dan `IMPORTED_ITEM_ID` (`DAFTAR PENYESUAIAN` kolom `A:C`) untuk rekonsiliasi penyesuaian saat fitur import template RKA dikembangkan pada fase berikutnya.
