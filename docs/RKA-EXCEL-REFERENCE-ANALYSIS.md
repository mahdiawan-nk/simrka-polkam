# Analisis Dokumen Referensi Excel RKA (`data-rka/`)

## 1. Inventarisasi File Referensi

| Nama File | Ukuran | Jenis Dokumen | Unit Kerja | Tahun Anggaran | Relevansi |
|---|---|---|---|---|---|
| `data-rka/05_RKA_2026-2027_UPT_ICT.xlsx` | 68.6 KB | Dokumen RKA Final Unit Kerja | UPT ICT | 2026/2027 | Acuan utama format visual, struktur hierarki rekening, warna, formula, dan print layout untuk Export Approved RKA |

Tidak ada file `.xls` atau `.xlsm` lain di folder `data-rka/`.

---

## 2. Struktur Workbook Referensi

Workbook `05_RKA_2026-2027_UPT_ICT.xlsx` memiliki **13 worksheet** (11 visible, 2 hidden):

| Index | Nama Worksheet | State | Orientasi | Keterangan |
|---|---|---|---|---|
| 0 | `RINGKASAN` | visible | Portrait | Ringkasan eksekutif usulan vs disetujui |
| 1 | `DAFTAR PENYESUAIAN` | visible | Landscape | Section A (dipindah/dihapus/ditunda) & Section B (dikelola untuk unit lain / tambahan review) |
| 2 | `BERITA ACARA` | visible | Portrait | Berita acara review RKA |
| 3 | `IDENTITAS` | visible | Portrait | Parameter unit, tahun anggaran, pejabat penandatangan |
| 4 | `REKAP` | visible | Portrait | Rekapitulasi per kelompok rekening |
| 5 | `Belanja Pegawai` | visible | Landscape | Rincian Form RKA-5.1 |
| 6 | `Belanja Barang dan Jasa` | visible | Landscape | Rincian Form RKA-5.2 |
| 7 | `Belanja Modal` | visible | Landscape | Rincian Form RKA-5.3 |
| 8 | `Belanja Hibah` | visible | Landscape | Rincian Form RKA-5.4 |
| 9 | `Belanja Pajak` | visible | Landscape | Rincian Form RKA-5.5 |
| 10 | `Pembayaran Hutang` | visible | Landscape | Rincian Form RKA-2.1 |
| 11 | `DAFTAR` | hidden | Portrait | Referensi daftar kode/uraian |
| 12 | `TB` | hidden | Portrait | Tabel bantu konversi terbilang Rupiah |

---

## 3. Analisis Worksheet Acuan (`DAFTAR PENYESUAIAN` & `Belanja ...`)

### 3.1. `DAFTAR PENYESUAIAN`
- **Header Dokumen (Row 1–3):**
  - Row 1: Judul `DAFTAR PENYESUAIAN USULAN RKA TA ...` (Bold, 12pt)
  - Row 2: Nama Unit Kerja (Bold, 10pt)
  - Row 3: Subjudul keterangan (9pt)
- **Section A (Row 4–6+):**
  - Banner `A. USULAN UNIT YANG DIPINDAH ATAU TIDAK DIANGGARKAN DI RKA UNIT INI` (Bold, 10pt, fill `#BDD7EE`).
  - Header tabel (fill `#D9D9D9`, Bold, 10pt): `No`, `Kode`, `Uraian Usulan`, `Volume`, `Jumlah Usulan (Rp)`, `Status`, `Keterangan / Dianggarkan di`.
- **Section B (Row 8+):**
  - Banner `B. POS YANG DIKELOLA UNIT INI UNTUK UNIT LAIN / TAMBAHAN SAAT REVIEW` (Bold, 10pt, fill `#BDD7EE`).
  - Header tabel (fill `#D9D9D9`, Bold, 10pt): `No`, `Kode`, `Uraian`, `Unit Asal`, `Jumlah Usulan (Rp)`, `Jumlah Disetujui (Rp)`, `Keterangan`.
  - Baris total `JUMLAH` (Bold, fill `#D9D9D9`) menggunakan `SUM(...)`.

### 3.2. Sheet `Belanja ...` (Representatif: `Belanja Barang dan Jasa`)
- **Kop & Identitas (Row 1–10):**
  - Row 1: `RENCANA KERJA DAN ANGGARAN` (Bold, 12pt, center)
  - Row 2: `POLITEKNIK KAMPAR` (Bold, 12pt, center)
  - Row 3: `UNIT KERJA: <NAMA UNIT>` (Bold, 10pt, center)
  - Row 4: `TAHUN ANGGARAN <TAHUN> (<PERIODE>)` (Bold, 10pt, center)
  - Row 6–10: Ringkasan Program, Kegiatan, Lokasi Kegiatan, Jumlah Usulan, Jumlah Disetujui.
- **Tabel Rincian Anggaran (Row 20–23+):**
  - Group header: `USULAN UNIT` (fill `#FFF2CC`) dan `HASIL REVIEW` (fill `#DDEBF7`).
  - Kolom header (fill `#D9D9D9`, Bold, 10pt): `Kode Rekening`, `Uraian`, `Volume`, `Satuan`, `Harga Satuan (Rp)`, `Jumlah Usulan (Rp)`, `Volume Disetujui`, `Jumlah Disetujui (Rp)`.
  - Hierarki baris:
    - Kelompok belanja (Level 1, e.g. `5.2`): Bold, fill `#BDD7EE`
    - Sub-kelompok (Level 2, e.g. `5.2.1`): Bold, fill `#E2EFDA`
    - Jenis rekening (Level 3, e.g. `5.2.1.03`): Bold, tanpa fill khusus
    - Item detail: 10pt, kolom input/usulan berlatar `#FFF2CC`, kolom volume disetujui berlatar `#DDEBF7`.
  - Baris Total Kelompok / Grand Total: Bold, fill `#D9D9D9`.
- **Blok Pengesahan (jika data tersedia):**
  - `Diusulkan oleh` (Kepala Unit), `Direview oleh` (Wadir Keuangan), `Disetujui oleh` (Direktur Politeknik Kampar).

---

## 4. Analisis Layout & Formatting

- **Font:** Standar 10pt (judul 12pt Bold, catatan/nomor kolom 9pt).
- **Palet Warna Resmi Referensi:**
  - Banner Bagian / Kelompok Level 1: `#BDD7EE`
  - Header Tabel / Baris Jumlah: `#D9D9D9`
  - Sub-kelompok Level 2: `#E2EFDA`
  - Area Usulan Unit: `#FFF2CC`
  - Area Hasil Review / Disetujui: `#DDEBF7`
- **Format Angka:**
  - Mata uang/nilai: `#,##0;(#,##0);"-"`
  - Volume: `#,##0.##`
- **Page Setup:**
  - `PaperSize`: A4 (`PageSetup::PAPERSIZE_A4` = 9)
  - `Orientation`: Landscape (`PageSetup::ORIENTATION_LANDSCAPE`)
  - `FitToPage = true`, `FitToWidth = 1`, `FitToHeight = 0`

---

## 5. Analisis Formula

- **Nilai Usulan per Item:** `=VolumeUsulan * HargaSatuanUsulan`
- **Nilai Disetujui per Item:** Menggunakan nilai `approved_total` aktual dari database atau formula `=VolumeDisetujui * HargaSatuanDisetujui` pada baris item (untuk item `APPROVED` / `TRIMMED`), sementara item berstatus `REJECTED`, `REMOVED`, `MOVED`, `BUDGETED_ELSEWHERE`, `PENDING` bernilai `0` pada unit asal.
- **Subtotal Kelompok & Grand Total:** Menggunakan `=SUM(...)` yang mereferensikan baris-baris item pada masing-masing kelompok belanja sehingga tidak terjadi double counting.

---

## 6. Mapping Kolom Referensi ke Database SIMRKA

### 6.1. Worksheet `DAFTAR PENYESUAIAN`

| Kolom Excel | Sumber Model | Field Database | Aturan Mapping | Keterangan |
|---|---|---|---|---|
| `ADJ_REF_ID` | `RkaAdjustment` | `id` | Langsung | Technical ID untuk future import |
| `ORIGIN_ITEM_ID` | `RkaAdjustment` | `rka_item_id` | Nullable jika manual Section B | Referensi item asal |
| `IMPORTED_ITEM_ID` | `RkaAdjustment` | `imported_rka_item_id` | Nullable | Referensi item hasil import |
| `BAGIAN` | `RkaAdjustment` | `section_type` | `A` atau `B` | `PENDING` masuk `A` sesuai Phase 1 |
| `STATUS_PENYESUAIAN` | `RkaAdjustment` | `action_type` | Langsung (8 status) | `MOVED`, `BUDGETED_ELSEWHERE`, `REJECTED_WITH_ADJUSTMENT`, `REJECTED`, `REMOVED`, `PENDING`, `SHARED_COST`, `ADDED_BY_REVIEWER` |
| `KODE_REKENING` | `Account` | `code` | Via `adjustment->account` | Fallback `originItem->account->code` |
| `NAMA_REKENING` | `Account` | `name` | Via `adjustment->account` | Nama akun rekening |
| `URAIAN` | `RkaAdjustment` | `description` | Langsung | Uraian penyesuaian |
| `UNIT_ASAL` | `Department` | `name` | Via `rkaHeader->department` atau `sourceItem->rkaHeader->department` | Untuk Section B tampilkan unit asal |
| `UNIT_TUJUAN` | `Department` | `name` | Via `destinationDepartment` / `targetDepartment` | Nullable |
| `VOLUME` | `RkaAdjustment` | `proposed_volume` | Numeric | Volume usulan/penyesuaian |
| `SATUAN` | `RkaAdjustment` | `unit_symbol` | String | Satuan |
| `HARGA_SATUAN` | `RkaAdjustment` | `unit_price` | Numeric | Harga satuan |
| `JUMLAH_USULAN` | `RkaAdjustment` | `proposed_amount` | Numeric | Jumlah usulan |
| `JUMLAH_DISETUJUI` | `RkaAdjustment` | `approved_amount` | Numeric | Jumlah disetujui |
| `KETERANGAN_REVIEWER` | `RkaAdjustment` | `notes` | Nullable | Catatan reviewer |
| `REVIEWER` | `User` | `name` | Via `adjustedBy` | Fallback `rkaHeader->reviewer` |

### 6.2. Worksheet `BELANJA`

| Kolom Excel | Sumber Model | Field Database | Aturan Mapping | Keterangan |
|---|---|---|---|---|
| `ITEM_REF_ID` | `RkaItem` | `id` | Langsung | Technical ID stabil |
| `KELOMPOK_BELANJA` | `Account` | `code` + `name` (Level 1) | `5.1`, `5.2`, `5.3`, `5.4`, `5.5`, `2.1` | Mapping via `form_type` / parent akun |
| `PROGRAM_KEGIATAN` | `RkaIndicator` | `program_name`, `activity_name` | `program_name - activity_name` | Via `item->indicator` |
| `KODE_REKENING` | `Account` | `code` | Via `item->account` | Kode akun belanja |
| `NAMA_REKENING` | `Account` | `name` | Via `item->account` | Nama akun belanja |
| `GROUP_HEADER` | `RkaItem` / `Account` | parent Level 2 | Nama sub-kelompok akun Level 2 (atau fallback `form_type`) | Karena kolom `group_header` tidak ada di `rka_items`, dipetakan dari akun Level 2 |
| `URAIAN_ITEM` | `RkaItem` | `description` | Langsung | Deskripsi item |
| `VOL_USULAN` | `RkaItem` | `proposed_volume` | Numeric | Tetap nilai usulan asli |
| `SATUAN` | `RkaItem` | `unit_symbol` | String | Satuan |
| `HARGA_SATUAN_USULAN` | `RkaItem` | `unit_price` | Numeric | Harga satuan usulan |
| `NILAI_USULAN` | `RkaItem` | `proposed_total` | Formula `=VOL * HARGA` | Nilai usulan |
| `VOL_DISETUJUI` | `RkaItem` | `approved_volume` | Numeric | Volume final disetujui |
| `HARGA_SATUAN_DISETUJUI` | `RkaItem` | `unit_price` | Numeric | Harga satuan berlaku |
| `NILAI_DISETUJUI` | `RkaItem` | `approved_total` | Numeric / Formula konsisten DB | Nilai final disetujui |
| `STATUS_ITEM` | `RkaItem` | `status` | Langsung | `APPROVED`, `TRIMMED`, `REJECTED`, `MOVED`, `PENDING`, dll. |
| `ASAL_ITEM` | `RkaItem` | `is_imported` | `'IMPORT_SECTION_B'` / `'USULAN_ASLI'` (+ `origin_item_id` jika ada) | Menjaga keterlacakan |
| `KETERANGAN_REVIEW` | `RkaItem` | `review_notes` | Nullable | Catatan review |

---

## 7. Gap Analysis

1. **Jumlah Worksheet:** Referensi menggunakan 13 worksheet (6 worksheet belanja terpisah), sedangkan spesifikasi PHASE 2 mewajibkan **tepat 2 worksheet** (`DAFTAR PENYESUAIAN` dan `BELANJA`). Solusi: seluruh 6 kelompok belanja disajikan berurutan dalam satu worksheet `BELANJA` dengan banner pemisah kelompok (`#BDD7EE`) dan subtotal per kelompok.
2. **Kolom `GROUP_HEADER`:** Tabel `rka_items` tidak memiliki kolom `group_header` khusus. Solusi: dipetakan secara deterministik dari nama `Account` Level 2 (parent dari akun detail item).
3. **Data Penandatangan Lengkap:** `RkaSetting` menyimpan `director_name` dan `director_nrp`, sedangkan NRP Kepala Unit/Reviewer tidak selalu terisi di `users`. Solusi: tampilkan nama aktual dari `unitHead`, `reviewer`, dan `setting->director_name` / `director_nrp` tanpa mengarang NIP/NRP yang tidak ada di database.
4. **Identitas Teknis Future Import:** Referensi manual belum memiliki kolom ID database (`ITEM_REF_ID`, `ADJ_REF_ID`, `ORIGIN_ITEM_ID`, `IMPORTED_ITEM_ID`). Solusi: sertakan kolom teknis tersebut dengan styling khusus/rapi di awal tabel agar stabil untuk kebutuhan import di masa mendatang.

---

## 8. Rekomendasi Desain Final Dua Worksheet

1. **Worksheet 1 — `DAFTAR PENYESUAIAN`:**
   - Kop dokumen resmi + nama unit + tahun anggaran (`schedule->fiscal_year`).
   - Section A (semua adjustment `section_type = 'A'`, termasuk `PENDING`), dengan kolom teknis + bisnis + subtotal Section A.
   - Section B (semua adjustment `section_type = 'B'`), dengan kolom teknis + bisnis + subtotal Section B.
2. **Worksheet 2 — `BELANJA`:**
   - Kop dokumen resmi (`RENCANA KERJA DAN ANGGARAN`, `POLITEKNIK KAMPAR`, `UNIT KERJA`, `TAHUN ANGGARAN`), ringkasan Total Usulan & Total Disetujui.
   - Tabel rincian seluruh item dari 6 kelompok belanja (`5.1`, `5.2`, `5.3`, `5.4`, `5.5`, `2.1`) yang memiliki data, dikelompokkan per kelompok belanja dengan banner `#BDD7EE`, subtotal per kelompok, dan `GRAND TOTAL BELANJA` di akhir tabel, diikuti blok pengesahan berdasarkan data aktual database.

---

## 9. Aturan Formatting dan Pencetakan

- Orientasi: **Landscape**, Paper Size: **A4**, `FitToPage = true`, `FitToWidth = 1`, `FitToHeight = 0`.
- `FreezePane` pada baris header tabel agar mudah diperiksa di Excel/LibreOffice.
- Border tipis (`BORDER_THIN`) pada seluruh sel tabel, border medium/tebal pada baris total.
- Format angka konsisten: `#,##0;(#,##0);"-"` untuk nominal Rupiah, `#,##0.##` untuk volume.
