# Dokumentasi Referensi & Implementasi PDF Fund Request Resmi

## 1. Referensi Dokumen Resmi
- **File acuan:** `data-rka/013-PK.3-NOT-ICT-10.2026_FORM RKA PENGAJUAN PENCAIRAN.pdf` (2 halaman, A4 Portrait).
- **Struktur Halaman 1:**
  1. Kop surat (mengikuti RKA Settings: `full_image`, `manual_two_logos`, atau `manual_single_logo`).
  2. Judul `FORMULIR PERMOHONAN PENCAIRAN DANA` dan `Tahun Anggaran`.
  3. Kotak `Nomor` dan `Tanggal`.
  4. Paragraf pengantar kepada Direktur Politeknik Kampar melalui Wakil Direktur II Bidang Keuangan, Kepegawaian dan Umum.
  5. **Section A. KEGIATAN** (`Unit Kerja`, `Nama Kegiatan / Keperluan`, `Waktu Pelaksanaan`, `Penanggung Jawab`, `Jenis Pencairan`).
  6. **Section B. RINCIAN KEBUTUHAN** (`No`, `Kode Akun`, `Nama Akun (otomatis)`, `Uraian`, `Volume`, `Satuan`, `Harga Satuan`, `Jumlah (Rp)`, total `JUMLAH YANG DIMOHONKAN`, serta `Terbilang`).
  7. **Section C. REKAP PER KODE AKUN (otomatis)** (`No`, `Kode`, `Nama Akun`, `Catatan`, `Jumlah Dimohonkan`, `Pagu Unit`, `Status`, dan catatan kaki pos terpusat & pagu setahun).
- **Struktur Halaman 2:**
  8. **Section D. PEMBAYARAN KEPADA** (`Nama Penerima`, `Bank / No. Rekening (kosongkan jika tunai)`).
  9. **Section E. LAMPIRAN (beri tanda ✓)** (daftar periksa dokumen lampiran + daftar berkas terunggah jika ada).
  10. **Section F. VERIFIKASI DAN PERSETUJUAN PER KODE (diisi Bagian Keuangan)** (`No`, `Kode`, `Kode Final (jika dikoreksi)`, `Sisa Pagu Sebelum (Rp)`, `Jumlah Disetujui (Rp)`, `Persetujuan`, `No. Bukti Register`, total `JUMLAH DISETUJUI`, serta keterangan simbol persetujuan).
  11. **Blok Tanda Tangan** (`Pemohon`, `Diverifikasi / Kabag KKU`, `Disetujui / Wakil Direktur II`, dan `Menyetujui / Direktur Politeknik Kampar`).

## 2. Sumber Konfigurasi RKA Settings
- **Model & Tabel:** `App\Models\RkaSetting` (`rka_settings`).
- **Resolusi Konfigurasi Aktif:** `FundRequestDetailService::resolvePdfConfiguration()` memilih record `RkaSetting` dengan `setting_name` yang merujuk pada *Form Permohonan Pencairan Dana* (mengandung `permohonan pencairan` atau `pencairan dana`, dan mengecualikan `format rka`).
- Jika konfigurasi belum tersedia atau ambigu, `FundRequestDetailService::buildForPdf()` melempar `RuntimeException` dan `FundRequestPdfController::preview()` mengembalikan HTTP `422` dengan pesan error informatif.
- **Kop Surat (`pdf_header_config`):**
  - Mode `full_image`: mengambil `full_kop_image` dari disk `local` (`storage/app/private`) atau `public` (`storage/app/public`) lalu dikonversi ke base64 data URI untuk DomPDF.
  - Mode `manual_two_logos` & `manual_single_logo`: merender logo kiri/kanan, `foundation_name`, `institution_name`, `address_line`, `contact_line`, dan `show_divider_line`.
- **Pejabat & Penandatangan (`signing_config`, `director_name`, `director_nrp`, `signing_place`):**
  - Nama dan jabatan penandatangan dibaca dari `signing_config` (role `pemohon`, `keuangan`, `wadir_keuangan`, `direktur`) dan kolom pejabat pada `RkaSetting`.
  - Status persetujuan pada masing-masing slot diambil dari `fund_request_approvals` secara aktual (tidak membuat tanda tangan/persetujuan fiktif apabila tahap tersebut belum disetujui).

## 3. Aturan Bisnis & Konsistensi Data
- **Section B:** Seluruh item pada `fund_request_items` (termasuk yang berstatus `approved`, `partial`, `rejected`, maupun pending `null`) tetap ditampilkan lengkap.
- **Section C:** `Pagu Unit` diambil dari `DepartmentBudget.allocated_amount` tahun anggaran berjalan (`pagu setahun`, bukan sisa pagu). Akun dengan `is_centralized = true` menampilkan catatan merah.
- **Section F:** Data diambil dari `fund_request_verifications` (atau fallback menunggu verifikasi jika belum diverifikasi). `No. Bukti Register` hanya ditampilkan jika terisi dan status verifikasi `Disetujui` atau `Disetujui Sebagian`.

## 4. File yang Diperbarui / Ditambahkan
- `app/Services/FundRequest/FundRequestDetailService.php` (`buildForPdf`, `resolvePdfConfiguration`, `buildPdfContext`, `terbilang`, `formatIndonesianDate`)
- `app/Http/Controllers/FundRequestPdfController.php` (`preview`)
- `resources/views/pdf/fund-request.blade.php`
- `tests/Feature/FundRequest/FundRequestOfficialPdfTest.php`
