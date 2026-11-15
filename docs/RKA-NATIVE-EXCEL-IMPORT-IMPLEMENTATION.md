# Implementasi Native Excel Import Approved RKA (PHASE 3)

## 1. Arsitektur Parser & Service

- **`App\Services\ApprovedRkaImport\NativeRkaWorkbookParser`:**
  - Mendeteksi workbook RKA asli (`IDENTITAS`, `REKAP`, `RINGKASAN`, `DAFTAR PENYESUAIAN`, `BERITA ACARA`, serta enam worksheet `Belanja`: `Belanja Pegawai` [`5.1`], `Belanja Barang dan Jasa` [`5.2`], `Belanja Modal` [`5.3`], `Belanja Hibah` [`5.4`], `Belanja Pajak` [`5.5`], `Pembayaran Hutang` [`2.1`]).
  - Mengabaikan sheet teknis `TB` dan `DAFTAR` (termasuk kondisi `hidden`).
  - Mem-parsing tabel Belanja secara dinamis (mencari header `Kode Rekening | Uraian`), melacak hierarki rekening (`5` → `5.x` → `5.x.x` → `5.x.x.xx`), berhenti pada blok pengesahan, dan mengabaikan sheet kosong tanpa item fiktif.
  - Membaca **cached formula values** (`getOldCalculatedValue()`), menandai missing cached value sebagai peringatan/fatal sesuai konteks tanpa menganggapnya nol.
  - Mem-parsing `DAFTAR PENYESUAIAN` Bagian A (termasuk tanpa banner `A. `) dan Bagian B dengan mempertahankan status & keterangan asli.
  - Mencocokkan identitas unit dari `IDENTITAS!C5` secara deterministik (`UNIT_ALIASES`, nama/kode persis, serta kode dalam tanda kurung seperti `(P3M)` dan `(TIF)`).
  - Melakukan rekonsiliasi antara hasil parsing item, grand total tiap sheet Belanja, `REKAP`, dan `RINGKASAN`.
- **`App\Services\ApprovedRkaImport\ApprovedRkaImportService`:**
  - Mendeteksi format workbook otomatis (`native` atau template 17 kolom), memvalidasi otorisasi unit (`user_departments`), menyimpan preview di Cache selama 30 menit terikat `user_id`, mengeksekusi import (`status = APPROVED`, `is_locked = true`), serta mendukung `executeReimport()` berbasis `source_ref_id` dengan proteksi terhadap item yang sudah memiliki `FundRequest`.
- **`App\Services\ApprovedRkaImport\RkaUnlockRequestService`:**
  - Mengelola `submitRequest()`, `approveRequest()`, dan `rejectRequest()` dengan `DB::transaction()` + `lockForUpdate()` serta pencatatan tunggal di `RkaLog`.
