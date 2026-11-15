# Analisis Format Workbook RKA Asli Politeknik Kampar

Dokumen ini disusun berdasarkan pemeriksaan langsung terhadap tiga file referensi di folder `data-rka/`:
1. `05_RKA_2026-2027_UPT_ICT.xlsx` (UPT ICT)
2. `10_RKA_2026-2027_P3M.xlsx` (UPT P3M)
3. `19_RKA_2026-2027_Prodi_TIF.xlsx` (Prodi D3 TIF)

---

## 1. Struktur yang Konsisten di Ketiga Workbook

1. **12 Worksheet Inti Selalu Hadir:**
   - `RINGKASAN` (visible, 27 baris, kolom `A:E`)
   - `DAFTAR PENYESUAIAN` (visible, kolom `A:G`)
   - `BERITA ACARA` (visible, 37 baris, kolom `A:E`)
   - `IDENTITAS` (visible, 25 baris, kolom `A:C`)
   - `REKAP` (visible, kolom `A:F`)
   - 6 Worksheet Belanja (visible, kolom `A:H`):
     - `Belanja Pegawai` (`5.1`)
     - `Belanja Barang dan Jasa` (`5.2`)
     - `Belanja Modal` (`5.3`)
     - `Belanja Hibah` (`5.4`)
     - `Belanja Pajak` (`5.5`)
     - `Pembayaran Hutang` (`2.1`)
   - `TB` (hidden, 1000 baris, referensi terbilang — **tidak diimpor**).
2. **Struktur `IDENTITAS`:** Label di kolom `B` (baris 5–23), nilai di kolom `C`.
3. **Struktur Tabel Belanja (Kolom `A:H`):**
   - `A`: `Kode Rekening`
   - `B`: `Uraian`
   - `C`: `Volume`
   - `D`: `Satuan`
   - `E`: `Harga Satuan (Rp)`
   - `F`: `Jumlah Usulan (Rp)`
   - `G`: `Volume Disetujui`
   - `H`: `Jumlah Disetujui (Rp)`
   - Diikuti baris penomoran kolom (`1 | 2 | 3 | 4 | 5 | 6 = 3 × 5 | 7 | 8 = 7 × 5`), baris root (`5 | BELANJA` atau `2 | PEMBAYARAN KEWAJIBAN`), hierarki rekening (`5.x`, `5.x.x`, `5.x.x.xx`), item belanja tanpa pengulangan kode rekening, baris `JUMLAH BELANJA ...`, dan blok pengesahan (`Diusulkan oleh,`, `Direview oleh,`, `Disetujui oleh,`).
4. **Struktur `DAFTAR PENYESUAIAN`:**
   - **Bagian A** (`No | Kode | Uraian Usulan | Volume | Jumlah Usulan (Rp) | Status | Keterangan / Dianggarkan di`)
   - **Bagian B** (`No | Kode | Uraian | Unit Asal | Jumlah Usulan (Rp) | Jumlah Disetujui (Rp) | Keterangan`).

---

## 2. Struktur yang Berbeda

| Aspek | UPT ICT (`05`) | UPT P3M (`10`) | Prodi TIF (`19`) |
|---|---|---|---|
| Jumlah Worksheet | **13** (ada sheet hidden `DAFTAR`) | **12** | **12** |
| Nama Unit di `IDENTITAS!C5` | `UPT ICT` | `UPT Penelitian dan Pengabdian kepada Masyarakat (P3M)` | `Prodi D3 Teknik Informatika (TIF)` |
| Nama Unit di DB `departments` | `UPT ICT` (`UPT-ICT`) | `Pusat Penelitian dan Pengabdian Masyarakat (P3M)` (`P3M`) | `Prodi Teknik Informatika (TIF)` (`PRODI-TIF`) |
| Pagu `IDENTITAS!C13` | Kosong | Kosong | `346500000` |
| Tanggal Review `IDENTITAS!C23` | `46294` (serial Excel) | Kosong | `46294` (serial Excel) |
| Direktur `IDENTITAS!C14` | `Dr. Ir. NINA VERONIKA, ST., MSc` | `Ir. NINA VERONIKA, ST., MSc` | `Dr. Ir. NINA VERONIKA, ST., MSc` |

---

## 3. Variasi Isi & Jumlah Baris Antarunit

- **`DAFTAR PENYESUAIAN`:**
  - `UPT ICT`: Bagian A kosong (`Tidak ada usulan yang dipindah...`), Bagian B berisi 10 baris (`5.2.2.04` lisensi aplikasi untuk P3M, Media Center, TIF, ABI).
  - `Prodi TIF`: Bagian A berisi 37 baris (`Dipindah ke pos pusat`, `Sudah dianggarkan di tempat lain`), Bagian B kosong.
  - `UPT P3M`: Bagian A & B bervariasi (28 baris total sheet).
- **Worksheet Belanja Terisi vs Kosong:**
  - Worksheet kosong (`Belanja Pegawai`, `Belanja Pajak`, `Pembayaran Hutang`, serta `Belanja Hibah` pada ICT & TIF, `Belanja Modal` pada P3M tertentu) menampilkan pesan `"Tidak ada usulan pada kelompok belanja ini"` dan total `0`.
  - Jumlah baris tertinggi pada worksheet terisi bervariasi dinamis (mis. `Belanja Modal` TIF = 86 baris, ICT = 55 baris, P3M = 39 baris).

---

## 4. Variasi Formula & Cached Values

- Formula lintas-sheet digunakan ekstensif (`=REKAP!C20`, `='Belanja Barang dan Jasa'!F25`, `='Belanja Barang dan Jasa'!F36`, `=INDEX(TB!$B$1:$B$1000,...)`).
- Formula baris item:
  - Kolom `F`: `=C{r}*E{r}`
  - Kolom `H`: `=IF(G{r}="",F{r},G{r}*E{r})` — **catatan penting:** apabila `Volume Disetujui` (kolom `G`) dibiarkan kosong oleh reviewer, formula Excel menganggap seluruh usulan disetujui (`F{r}`), sehingga parser **tidak boleh** menganggap `G` kosong = `0` ataupun menyamakan `G` kosong dengan `C` tanpa membaca cached value `H`!
- PhpSpreadsheet membaca **cached formula result** melalui `Cell::getOldCalculatedValue()`. Apabila cached value tidak tersedia (`null`), parser menandai sebagai peringatan (`cached_value_missing`) dan tidak menganggapnya nol.

---

## 5. Kondisi Data Kosong & Gap Pemetaan Otomatis

1. **Pagu Kosong (`IDENTITAS!C13`):** Ditemukan pada ICT dan P3M; `REKAP` menampilkan `"PAGU BELUM DIISI"`. Sistem menandai sebagai **Warning** (bukan `Rp 0` valid).
2. **Placeholder `BERITA ACARA`:** Masih berisi `[....]` untuk nomor surat, hari, tanggal, dan tanda tangan; ditandai sebagai **Warning** informasional.
3. **Relasi `ORIGIN_ITEM_ID` / `IMPORTED_ITEM_ID` pada `DAFTAR PENYESUAIAN` Bagian A:** Tidak memiliki ID teknis database; tidak dibuat relasi spekulatif ke unit lain, melainkan dicatat sebagai `RkaAdjustment` unit asal dengan status/keterangan asli dan ditandai `PERLU PERHATIAN` di preview.
4. **Alias Nama Unit:** Nama di `IDENTITAS!C5` (`UPT Penelitian dan Pengabdian... (P3M)`, `Prodi D3 Teknik Informatika (TIF)`) sedikit berbeda redaksi dengan `departments.name` di database, sehingga dicocokkan melalui mekanisme normalisasi kode kurung `(P3M)`, `(TIF)` dan tabel alias eksplisit yang dapat diaudit.
