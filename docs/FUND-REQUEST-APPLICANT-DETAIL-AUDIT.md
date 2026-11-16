# Audit Codebase — Halaman Detail Pengajuan Pemohon & Redesign PDF Fund Request

**Tanggal Audit:** 2026-11-16  
**Branch:** `feature/rka-approved-excel-import`

---

## 1. Kondisi Saat Ini

1. **Belum Ada Halaman Detail Pemohon (`/admin/fund-requests/{record}`):**
   - `FundRequestResource::getPages()` hanya memiliki `index`, `create`, `edit`, dan `verify`.
   - `ViewAction` di `FundRequestsTable` mengarahkan ke `VerifyFundRequest`, yang memblokir role `pemohon` dengan `403`.
2. **PDF Existing (`FundRequestPdfController` + `resources/views/pdf/fund-request.blade.php`):**
   - Menggunakan `barryvdh/laravel-dompdf` (`Barryvdh\DomPDF\Facade\Pdf`), yang sudah memadai tanpa perlu mengganti library.
   - `FundRequestPdfController::preview()` menolak semua status selain `approved` (`403`).
   - Template PDF memiliki `header { position: fixed; top: -32mm }` di luar margin `@page { margin: 12mm 15mm }` sehingga kop surat terpotong, belum memiliki page numbering footer, dan menghitung data secara terpisah di Blade tanpa service presenter bersama.
   - Aset logo resmi tersedia di `public/images/logo-polkam.png` dan `public/images/kop-surat.png`.
3. **Lampiran & Riwayat:**
   - `fund_requests.attachments` menyimpan array path file; belum ada route download/preview yang terproteksi policy `view`.
   - `fund_request_approvals` menyimpan riwayat persetujuan berjenjang (`kabakku`, `wadir_bakku`, `director`).

---

## 2. Rencana Implementasi

1. **Service Presenter Bersama (`App\Services\FundRequest\FundRequestDetailService`):**
   - Satu sumber kebenaran untuk halaman detail (`ViewFundRequest`) dan cetak PDF (`FundRequestPdfController`):
     - Eager loading `department`, `creator`, `rkaHeader.schedule`, `items.account`, `items.rkaItem`, `verifications.account`, `verifications.finalAccount`, `approvals.user`.
     - Ringkasan keuangan: `total_requested`, `total_approved`, `total_rejected` (`total_requested - total_approved` ketika sudah diverifikasi/diproses), serta status workflow aktual.
     - Rekap per kode rekening dihitung dari detail `items` (tanpa double counting).
     - Timeline progress workflow & riwayat persetujuan dari `fund_request_approvals`.
     - Ringkasan hasil verifikasi Bagian Keuangan (tanpa membocorkan catatan internal yang tidak semestinya).
     - Daftar lampiran dengan URL unduh terproteksi policy (`fund-requests.attachment`).
2. **Halaman Detail Pemohon (`App\Filament\Resources\FundRequests\Pages\ViewFundRequest`):**
   - Route `/admin/fund-requests/{record}` (`'view' => ViewFundRequest::route('/{record}')`), otorisasi via `$user->can('view', $this->record)` (`FundRequestPolicy` membatasi unit pemohon).
   - Menampilkan Header + Cetak PDF + Kembali + Edit (jika `draft`/`rejected`), Progress Workflow, Ringkasan Keuangan, Informasi Kegiatan, Rincian Item (semua status termasuk Pending & Rejected), Rekap Per Kode Rekening, Hasil Verifikasi Keuangan, Riwayat Persetujuan, Riwayat Aktivitas, dan Lampiran.
3. **Redesign PDF (`FundRequestPdfController` + `resources/views/pdf/fund-request.blade.php`):**
   - Menggunakan `FundRequestDetailService::build($record)` agar 100% identik dengan halaman detail.
   - Menggunakan `logo-polkam.png` / `kop-surat.png`, A4 dengan margin rapi, header berulang (`thead { display: table-header-group; }`), footer dengan nomor halaman (`Halaman X dari Y`) & tanggal cetak, penanda status `DRAFT` / `BELUM DISETUJUI` bila belum `approved`, serta area pengesahan yang hanya menampilkan tanda persetujuan sesuai `fund_request_approvals` aktual.
   - Nama file: `Fund-Request-{nomor-pengajuan}-{tahun-anggaran}.pdf`.
