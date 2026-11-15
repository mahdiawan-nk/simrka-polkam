# Mapping Native Excel Workbook RKA ke Model & Database

Dokumen ini mendokumentasikan struktur aktual workbook RKA resmi Politeknik Kampar berdasarkan inspeksi langsung file pada `data-rka/` (`05_RKA_2026-2027_UPT_ICT.xlsx`, `10_RKA_2026-2027_P3M.xlsx`, dan `19_RKA_2026-2027_Prodi_TIF.xlsx`).

---

## 1. Struktur Worksheet

| Worksheet | Sifat | Diparsing | Target Model / Kegunaan |
|---|---|---|---|
| `IDENTITAS` | Bisnis | Ya | Validasi unit (`C5`), periode (`C11`, `C12`), pagu anggaran (`C13`), penandatangan |
| `RINGKASAN` | Bisnis | Ya | `Usulan awal unit` (`C6`) -> `rka_headers.pagu_budget`; total dibahas & disetujui untuk rekonsiliasi |
| `REKAP` | Bisnis | Ya | Verifikasi silang total usulan & disetujui per kelompok belanja |
| `BERITA ACARA` | Bisnis | Ya | Metadata review & catatan |
| `DAFTAR PENYESUAIAN` | Bisnis | Ya | `RkaAdjustment` (Bagian A & Bagian B) + dasar `RkaAdjustmentMatch` |
| `Belanja Pegawai` (`5.1`) | Bisnis | Ya | `RkaIndicator` + `RkaItem` (`form_type = 5.1`) |
| `Belanja Barang dan Jasa` (`5.2`) | Bisnis | Ya | `RkaIndicator` + `RkaItem` (`form_type = 5.2`) |
| `Belanja Modal` (`5.3`) | Bisnis | Ya | `RkaIndicator` + `RkaItem` (`form_type = 5.3`) |
| `Belanja Hibah` (`5.4`) | Bisnis | Ya | `RkaIndicator` + `RkaItem` (`form_type = 5.4`) |
| `Belanja Pajak` (`5.5`) | Bisnis | Ya | `RkaIndicator` + `RkaItem` (`form_type = 5.5`) |
| `Pembayaran Hutang` (`2.1`) | Bisnis | Ya | `RkaIndicator` + `RkaItem` (`form_type = 2.1`) |
| `TB`, `DAFTAR` | Teknis/Referensi | Diabaikan | Tidak diimpor sebagai transaksi bisnis |

---

## 2. Mapping `RINGKASAN` & `IDENTITAS` ke `RkaHeader`

| Sumber Excel | Koordinat / Pencarian | Nilai Contoh (`UPT ICT` / `P3M` / `TIF`) | Target Database | Aturan |
|---|---|---|---|---|
| `IDENTITAS` — `Unit Kerja` | `B5` -> `C5` | `UPT ICT` / `UPT Penelitian dan Pengabdian kepada Masyarakat (P3M)` / `Prodi D3 Teknik Informatika (TIF)` | `rka_headers.department_id` | Dicocokkan secara deterministik via `DepartmentResolver`; wajib cocok dengan unit pemohon |
| `RINGKASAN` — `Usulan awal unit` | Cari label `Usulan awal unit` di kolom `B`, baca kolom `C` (merged `C6:D6`) | `156583000` / `496090000` / `728338000` | `rka_headers.pagu_budget` | Nilai numerik asli/cached formula. **Dilarang** dihitung dari total `BELANJA` atau `DAFTAR PENYESUAIAN`. Jika kosong/tidak terbaca, import diblokir |
| `IDENTITAS` — `Pagu Anggaran Unit (Rp)` | `B13` -> `C13` | Kosong (`UPT ICT`, `P3M`), `346500000` (`TIF`) | Metadata preview (`identitas.pagu_anggaran`) | Batas pagu persetujuan; bukan pengganti `Usulan awal unit` |
| Status & Kunci Import | — | — | `rka_headers.status = 'APPROVED'`, `is_locked = true` | Berlaku setelah import berhasil |

---

## 3. Mapping 6 Worksheet `Belanja` ke `RkaIndicator` & `RkaItem`

- **Header Kelompok**: `RkaIndicator` dibuat per worksheet kelompok belanja yang memiliki item valid.
- **Baris Item**:
  - Kode rekening (contoh `5.2.2.04`) dicocokkan ke `accounts.code` (`account_id`).
  - Uraian, volume usulan, satuan (`unit_symbol`), harga satuan (`unit_price`), jumlah usulan (`proposed_total`), volume disetujui (`approved_volume`), dan jumlah disetujui (`approved_total`) dibaca dari nilai numerik/cached formula.
  - Referensi sumber unik per item dicatat sebagai `NATIVE:{group_code}:{account_code}:{seq}` untuk pemetaan `imported_rka_item_id`.

---

## 4. Mapping `DAFTAR PENYESUAIAN` ke `RkaAdjustment`

### 4.1 Bagian A (Section A)
- **Makna**: Usulan unit asal yang dipindah (`MOVED`), tidak dianggarkan (`NOT_BUDGETED`), atau ditunda (`PENDING`).
- **Mapping**:
  - `section_type = 'A'`
  - `account_id`: hasil lookup kode rekening (jika valid)
  - `rka_item_id`: diisi hanya jika `MATCH_UNIQUE` ke item pada sheet `Belanja` unit sendiri; selain itu `NULL`
  - `destination_unit_id`: diisi hanya jika `action_type = 'MOVED'` dan unit tujuan eksplisit diketahui; jika hanya menyebut "pos pusat / unit lain" tanpa nama unit pasti, wajib `NULL`
  - `target_unit_id = NULL`, `imported_rka_item_id = NULL`, `is_imported = false`, `adjusted_by = NULL`

### 4.2 Bagian B (Section B)
- **Makna**: Pos yang dikelola oleh unit pengelola (pemilik workbook) untuk unit penerima manfaat (`Unit Asal` pada kolom Excel).
- **Mapping**:
  - `section_type = 'B'`
  - `action_type = 'SHARED_COST'` (atau `ADDITIONAL` sesuai status)
  - `target_unit_id`: hasil `DepartmentResolver` dari kolom `Unit Asal` (`NOT_FOUND`/`AMBIGUOUS` memblokir import dengan status `UNIT BELUM DITEMUKAN`)
  - `destination_unit_id = NULL`, `adjusted_by = NULL`
  - `imported_rka_item_id`: diisi jika formula Excel (mis. `='Belanja Barang dan Jasa'!F36`) atau pencocokan unik menunjuk ke `RkaItem` pada sheet `Belanja` unit pengelola (`MATCH_UNIQUE`), sekaligus menyetel `is_imported = true`; selain itu `NULL` dan `is_imported = false`
