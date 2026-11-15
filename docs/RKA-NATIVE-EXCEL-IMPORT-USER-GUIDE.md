# Panduan Pengguna — Native Excel Import Approved RKA

## A. Untuk Pemohon (Unit / Departemen)

### 1. Import Approved RKA dari Workbook Asli
1. Buka menu **Manajemen RKA → Pengusulan & Review RKA**.
2. Klik tombol **Import Approved RKA**.
3. Pilih **Periode / Jadwal RKA** dan **Unit / Departemen** Anda.
4. Unggah langsung file Excel **workbook RKA asli** (`.xlsx`) yang telah disetujui (tidak perlu memindahkan data ke template teknis 17 kolom).
5. Klik **Preview & Validasi Data**.
6. Periksa hasil pada tab **Belanja**, **Daftar Penyesuaian**, **Rekap & Rekonsiliasi**, serta **Identitas & Berita Acara**.
7. Pastikan tidak ada *Kesalahan Fatal* (mis. unit workbook tidak cocok dengan unit akun Anda, kode rekening tidak terdaftar, atau selisih rekonsiliasi), lalu klik **Konfirmasi & Simpan Approved RKA**.
8. RKA otomatis tersimpan dengan status **APPROVED** dan langsung **terkunci**.

> **Catatan Formula Excel:** Pastikan file Excel pernah dibuka dan disimpan (*Save*) di Microsoft Excel atau LibreOffice Calc agar *cached formula values* tersedia untuk proses rekonsiliasi otomatis.

### 2. Permintaan Buka Kunci & Import Ulang
1. Jika terdapat kesalahan data setelah import, klik **Ajukan Buka Kunci** pada baris RKA terkait dan isi alasan.
2. Setelah disetujui oleh Tim Keuangan (`keuangan` / `wadir_keuangan`), klik **Import Ulang Approved**, unggah workbook perbaikan, lakukan preview, dan konfirmasi.
3. RKA tetap berstatus **APPROVED** dan otomatis dikunci kembali setelah import ulang selesai.
