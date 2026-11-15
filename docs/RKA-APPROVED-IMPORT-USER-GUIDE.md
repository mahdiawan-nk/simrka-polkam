# Panduan Pengguna — Import Approved RKA & Permintaan Buka Kunci

## A. Untuk Pemohon (Unit / Departemen)

### 1. Import Approved RKA Baru
1. Buka menu **Manajemen RKA → Pengusulan & Review RKA**.
2. Klik tombol **Import Approved RKA** di bagian kanan atas halaman.
3. Jika belum memiliki file dengan format resmi, klik tombol **Download Template Excel** untuk mengunduh `TEMPLATE-IMPORT-APPROVED-RKA.xlsx` (berisi worksheet `DAFTAR PENYESUAIAN` dan `BELANJA` sesuai format PHASE 2).
4. Pilih **Periode / Jadwal RKA**, pilih **Unit / Departemen** Anda, dan unggah file Excel `.xlsx` yang memiliki worksheet **`BELANJA`** dan **`DAFTAR PENYESUAIAN`**.
5. Klik **Preview & Validasi Data**.
6. Periksa ringkasan nominal, tab **Worksheet BELANJA**, tab **Worksheet DAFTAR PENYESUAIAN**, serta badge validasi (`SESUAI`, `BERBEDA`, `PERLU PERHATIAN`, `TIDAK VALID`).
7. Jika tidak ada kesalahan fatal, klik **Konfirmasi & Simpan Approved RKA**.
8. RKA otomatis tersimpan dengan status **APPROVED** dan langsung **terkunci**.

### 2. Mengajukan Pembukaan Kunci (Jika Ada Kesalahan Data)
1. Pada daftar RKA, cari RKA berstatus **APPROVED** milik unit Anda.
2. Klik aksi **Ajukan Buka Kunci**, lalu isi **Alasan Permintaan Pembukaan Kunci** dan deskripsi kesalahan (opsional).
3. Tunggu persetujuan dari Tim Keuangan (`keuangan` / `wadir_keuangan`).

### 3. Menjalankan Import Ulang
1. Setelah disetujui Tim Keuangan, tombol **Import Ulang Approved** akan muncul pada baris RKA tersebut (atau melalui menu **Permintaan Buka Kunci RKA** → **Jalankan Import Ulang**).
2. Unggah file Excel perbaikan, lakukan **Preview & Validasi Data**, lalu konfirmasi.
3. Data direkonsiliasi, hak import ulang ditandai **USED**, dan RKA otomatis dikunci kembali dengan status tetap **APPROVED**.

## B. Untuk Tim Keuangan (`keuangan` / `wadir_keuangan`)
1. Buka menu **Manajemen RKA → Permintaan Buka Kunci RKA**.
2. Tinjau alasan permintaan pada baris berstatus **PENDING**.
3. Klik **Setujui** (atur masa berlaku jam & catatan opsional) untuk memberi hak import ulang satu kali, atau klik **Tolak** dengan mengisi alasan penolakan wajib.
