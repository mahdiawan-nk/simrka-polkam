# Dokumentasi Redesign PDF Fund Request

## 1. Tujuan & Referensi
Pembaruan ini menyempurnakan tata letak dan presentasi visual PDF **Formulir Permohonan Pencairan Dana** pada modul Fund Request SIMRKA Politeknik Kampar agar bersih, formal, berlatar putih, dan layak digunakan sebagai dokumen administrasi resmi institusi tanpa mengubah struktur section maupun logika bisnis yang ada.

## 2. Library & Mekanisme PDF
- **Library PDF:** `barryvdh/laravel-dompdf` (`Barryvdh\DomPDF\Facade\Pdf`), ukuran kertas `A4`, orientasi `portrait`.
- **Controller:** `App\Http\Controllers\FundRequestPdfController::preview()` (rute `fund-requests.pdf-preview`, digunakan pada `ViewFundRequest` dan `FundRequestsTable`).
- **Service:** `App\Services\FundRequest\FundRequestDetailService::buildForPdf()` yang mengambil data pengajuan, item, rekap akun (`DepartmentBudget`), verifikasi (`fund_request_verifications`), persetujuan (`fund_request_approvals`), serta konfigurasi aktif `Konfigurasi Form Permohonan Pencairan Dana` dari `rka_settings`.
- **Template Blade:** `resources/views/pdf/fund-request.blade.php`.

## 3. Perubahan Desain per Section
- **Desain Umum:** Seluruh sel dan latar dokumen menggunakan background putih (`#ffffff`), teks hitam/abu-abu gelap (`#111827` / `#1f2937`), border tabel tipis abu-abu netral (`0.7px solid #9ca3af`), serta seluruh judul section rata kiri dengan garis bawah tegas. Menghapus seluruh background kuning/krem/biru dekoratif dan pill/badge UI.
- **Nomor & Tanggal:** Disajikan dalam tata letak label-nilai bersih tanpa kotak berwarna.
- **Section A (KEGIATAN) & Section D (PEMBAYARAN KEPADA):** Disajikan menggunakan format label-nilai tanpa tabel berkotak kompleks, dengan pembungkusan teks otomatis untuk nilai panjang dan ruang kosong terjaga jika `Bank / No. Rekening` belum diisi.
- **Section B (RINCIAN KEBUTUHAN):** Tabel 8 kolom berlatar putih, **baris kosong tambahan dihilangkan** sehingga jumlah baris mengikuti jumlah item aktual, angka Rupiah rata kanan, volume dan satuan rata tengah, nama akun dan uraian rata kiri, serta `Terbilang` tetap ditampilkan di bawah total permohonan.
- **Section C (REKAP PER KODE AKUN):** Tabel 7 kolom berlatar putih tanpa baris kosong, catatan akun terpusat berwarna merah, pagu tahunan (`DepartmentBudget`), serta catatan kaki dalam paragraf biasa.
- **Section E (LAMPIRAN):** Checklist dua kolom tanpa border kotak penuh menggunakan simbol checkbox yang jelas (`☐`), diikuti daftar berkas terunggah apabila tersedia.
- **Section F (VERIFIKASI DAN PERSETUJUAN PER KODE):** Tabel 7 kolom berlatar putih tanpa warna dekoratif maupun baris kosong tambahan, angka rata kanan, status persetujuan rata tengah, dan keterangan petunjuk persetujuan sebagai paragraf biasa di bawah tabel.
- **Area Tanda Tangan:** Menghapus seluruh badge hijau/status pill (`Diajukan`, `Disetujui • ...`). Menampilkan tempat & tanggal, jabatan di atas ruang tanda tangan kosong yang proporsional, nama pejabat di bawah ruang tanda tangan, serta posisi **Direktur Politeknik Kampar** di bawah susunan tiga pejabat (`Pemohon`, `Diverifikasi`, `Disetujui`).

## 4. Pengaturan Halaman & Pagination
- Header tabel (`thead`) diulang otomatis saat tabel berlanjut ke halaman berikutnya (`display: table-header-group`).
- Baris tabel dan blok ringkas (`A`, `D`, `E`, dan blok tanda tangan) dilindungi dengan `page-break-inside: avoid` agar tidak terpotong di tengah halaman.
- Tidak ada pemaksaan `page-break-before` yang menyebabkan ruang kosong besar.

## 5. Hasil Pengujian
- `vendor/bin/pint --dirty --format agent`: Lulus tanpa error.
- `php artisan test tests/Feature/FundRequest/ --compact`: Seluruh test (`FundRequestApplicantDetailAndPdfTest`, `FundRequestApprovedRkaTest`, `FundRequestOfficialPdfTest`, `FundRequestVerificationWorkflowTest`) lulus.
- Pemeriksaan visual dilakukan terhadap hasil render PDF aktual untuk `FundRequest` nomor `134`.
