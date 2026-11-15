# Development Data Cleanup — RKA (`Clean Data RKA`)

## 1. Tujuan Fitur

Menyediakan fitur khusus untuk membersihkan seluruh data operasional RKA beserta aktivitas dan histori turunannya pada environment development (`local`, `development`) oleh `super_admin`, guna mempermudah pengujian ulang alur RKA tanpa menghapus master data atau konfigurasi sistem.

---

## 2. Pembatasan Environment & Role

### 2.1. Environment Restriction
- Diperiksa di backend melalui `app()->environment(['local', 'development'])`.
- **Aktif:** `local`, `development`
- **Ditolak (nonaktif di menu, halaman 403/404, dan service melempar exception):** `testing`, `test`, `production`, `staging`, dan environment lainnya.

### 2.2. Role & RBAC Restriction
- Hanya user dengan role `super_admin` (`$user->hasRole('super_admin')`) yang dapat melihat menu, membuka halaman, melihat preview, maupun mengeksekusi cleanup.
- Validasi role dan environment dijalankan kembali di setiap pemanggilan backend (`RkaDataCleanupService::ensureAllowedEnvironment()` dan `ensureSuperAdmin()`).

### 2.3. Database Safety Guard
- Menolak eksekusi apabila nama database tidak dapat diidentifikasi atau mengandung `production` / `prod`.
- Menolak eksekusi apabila host database mengindikasikan host production (`prod` / `production`).
- Tidak menampilkan password atau credential koneksi pada UI.

---

## 3. Audit Relasi & Cleanup Scope

### 3.1. Tabel yang Dibersihkan (Urutan Child-to-Parent)

| Urutan | Tabel | Kategori | Aksi | Alasan | Risiko Relasi |
|---|---|---|---|---|---|
| 1 | `rka_logs` | Activity History | DELETE | Seluruh log aktivitas RKA Header & turunannya | FK `rka_header_id` (`CASCADE`), termasuk log `DELETE_RKA` dengan `rka_header_id = NULL` |
| 2 | `rka_item_reviews` | Legacy Review | DELETE | Data review item lama yang terhubung ke `rka_items` | FK `rka_item_id` (`CASCADE`) |
| 3 | `rka_adjustments` | Child | DELETE | Penyesuaian Section A & B | FK `rka_header_id` (`CASCADE`), `rka_item_id`/`rka_indicator_id` (`SET NULL`) |
| 4 | `rka_items` | Child | DELETE | Rincian item belanja RKA | FK `rka_header_id` (`CASCADE`), `rka_indicator_id` (`CASCADE`) |
| 5 | `rka_indicators` | Child | DELETE | Program & matriks indikator RKA | FK `rka_header_id` (`CASCADE`) |
| 6 | `rka_approvals` | History | DELETE | Histori persetujuan/revisi berjenjang | FK `rka_header_id` (`CASCADE`) |
| 7 | `department_budgets` (subset RKA) | Synced Budget | CONDITIONAL DELETE | Hanya baris dengan `schedule_id IS NOT NULL` yang cocok `(department_id, schedule_id)` pada `rka_headers` yang dibersihkan | Menjaga `department_budgets` manual/hasil import (`schedule_id IS NULL`) tetap utuh |
| 8 | `rka_headers` | Operational Root | DELETE | Dokumen utama RKA per unit & jadwal | Root data RKA |

### 3.2. Tabel yang Dipertahankan (KEEP)

| Tabel | Kategori | Alasan |
|---|---|---|
| `users`, `roles`, `permissions`, `model_has_*`, `role_has_permissions` | Master / Auth | Akun Super Admin & seluruh hak akses tetap utuh |
| `departments`, `user_departments` | Master | Referensi unit kerja & penugasan user |
| `accounts` | Master | Bagan akun rekening belanja |
| `rka_settings`, `rka_schedules`, `rka_generate_configs` | Konfigurasi RKA | Pengaturan pejabat, jadwal aktif, dan template generate |
| `department_budgets` (non-RKA / `schedule_id IS NULL`) | Budget Manual | Tidak berasal dari sinkronisasi `rka_headers` |
| `fund_requests`, `fund_request_items`, `fund_request_verifications`, `fund_request_approvals` | Modul Lain | Transaksi pencairan dana tidak dihapus otomatis |
| `backup_*`, `restore_runs`, `table_empty_*`, `exports`, `imports`, `migrations` | Sistem | Infrastruktur backup, audit sistem, dan migrasi |

---

## 4. Strategi Transaksi, Foreign Key & Attachment

- **Foreign Key Handling:** Penghapusan dilakukan berurutan dari child ke parent di dalam `DB::transaction()` **tanpa** menonaktifkan `FOREIGN_KEY_CHECKS`, sehingga integritas referensial tetap terjaga dan transaksi otomatis di-rollback bila terjadi kegagalan.
- **Auto Increment:** Tidak di-reset secara otomatis untuk menghindari efek samping pada referensi historis/transaksi.
- **File & Attachment:** Hasil audit codebase menunjukkan `ImportExcelForm` memproses file sementara Livewire tanpa menyimpan path file permanen pada tabel `rka_headers`, `rka_items`, `rka_adjustments`, maupun `rka_approvals`. Oleh karena itu, tidak ada file `storage/` yang dihapus otomatis agar dokumen modul lain tetap aman.

---

## 5. Mekanisme Konfirmasi Berlapis & Cara Penggunaan

1. Login sebagai `super_admin` pada environment `local` atau `development`.
2. Buka menu **Development Tools → Clean Data RKA**.
3. Periksa panel **Environment Information** (env, database, koneksi — tanpa credential), **Data Overview**, dan **Cleanup Scope**.
4. Klik **Preview Cleanup** untuk menghitung jumlah record aktual yang akan dihapus (tanpa mengubah database).
5. Klik **Execute Cleanup**, baca ringkasan modal konfirmasi (environment, database, total record, tabel terdampak, dan data yang dipertahankan), lalu ketik tepat **`CLEAN RKA DATA`**.
6. Backend memvalidasi ulang environment, role `super_admin`, database safety guard, dan teks konfirmasi sebelum menjalankan transaksi penghapusan dan menampilkan laporan hasil (`SUCCESS` / `FAILED`).

---

## 6. Hasil Pengujian

Diverifikasi melalui [RkaDataCleanupTest.php](file:///home/mahdi-nk/Project/Polkam/sistem-keuangan/tests/Feature/Development/RkaDataCleanupTest.php) menggunakan `DatabaseTransactions`:
- **Total Suite (RKA + Development Cleanup):** `30 passed (251 assertions)`, `0 failed`.
- **Cakupan `RkaDataCleanupTest`:** `4 passed (59 assertions)` mencakup penolakan environment `testing`, `test`, `production`, `staging`, penolakan user non-`super_admin`, validasi `preview()` read-only, validasi safety guard database `production`, konfirmasi eksplisit `CLEAN RKA DATA`, serta verifikasi bahwa seluruh tabel operasional RKA terhapus sementara master data, `super_admin`, `department_budgets` manual, dan `fund_requests` tetap utuh.
