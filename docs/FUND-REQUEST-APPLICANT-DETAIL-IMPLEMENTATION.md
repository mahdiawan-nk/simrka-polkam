# Implementasi Halaman Detail Pengajuan Pemohon & Redesign PDF Fund Request

**Tanggal:** 2026-11-16  
**Branch:** `feature/rka-approved-excel-import`  
**Audit Pendukung:** [FUND-REQUEST-APPLICANT-DETAIL-AUDIT.md](FUND-REQUEST-APPLICANT-DETAIL-AUDIT.md)

---

## 1. Struktur Halaman Detail (`/admin/fund-requests/{record}`)

- **Page Class:** `App\Filament\Resources\FundRequests\Pages\ViewFundRequest`
- **Blade View:** `resources/views/filament/resources/fund-requests/pages/view-fund-request.blade.php`
- **Presenter Terpusat:** `App\Services\FundRequest\FundRequestDetailService`
- **Bagian Halaman:**
  1. Header dengan Breadcrumb (`Pengajuan Pencairan > Detail Pengajuan`), Nomor Surat, Status Badge, Unit Kerja, Pemohon, Tahun Anggaran, dan tombol `Cetak PDF`, `Ubah Pengajuan` (khusus `draft`/`rejected`), `Kembali ke Daftar`.
  2. Status & Progress Workflow (`Draft` → `Diajukan` → `Verifikasi Keuangan` → `Persetujuan Wadir Keuangan` → `Pengesahan Direktur`) bersumber dari `fund_requests.status` dan `fund_request_approvals`.
  3. Kartu Ringkasan Keuangan (`Total Dimohonkan`, `Total Disetujui`, `Total Ditolak / Tidak Disetujui`).
  4. Informasi Kegiatan & Sumber Anggaran (2 kolom desktop, 1 kolom mobile).
  5. Tabel Rincian Kebutuhan (menampilkan seluruh item termasuk `Pending`, `Rejected`, `Partial`, `Approved`, beserta footer total).
  6. Tabel Rekapitulasi per Kode Rekening (agregasi langsung dari detail item).
  7. Hasil Verifikasi Bagian Keuangan & Riwayat Persetujuan Berjenjang.
  8. Riwayat Aktivitas & Lampiran Dokumen Pendukung (unduh terproteksi policy).

---

## 2. Authorization & Keamanan

- `ViewFundRequest::mount()` memeriksa `$user->can('view', $this->record)` (`FundRequestPolicy` membatasi pemohon hanya pada unitnya).
- `FundRequestPdfController::preview()` dan `FundRequestPdfController::attachment()` memeriksa `$user->can('view', $record)`.
- `FundRequestsTable` memisahkan `Lihat` (`view` — untuk pemohon & semua role berwenang) dan `Verifikasi` (`verify` — khusus `keuangan`, `wadir_keuangan`, `direktur`).

---

## 3. Redesign PDF (`resources/views/pdf/fund-request.blade.php`)

- Menggunakan `barryvdh/laravel-dompdf` dengan sumber data yang sama (`FundRequestDetailService::build($record)`).
- Format nama file: `Fund-Request-{nomor-pengajuan}-{tahun-anggaran}.pdf`.
- Kertas A4 portrait dengan logo resmi `public/images/logo-polkam.png`, repeating `<thead>`, footer nomor halaman (`Halaman X dari Y`) & tanggal cetak, penanda status non-final bila belum `approved`, serta area pengesahan sesuai data `fund_request_approvals` aktual.
