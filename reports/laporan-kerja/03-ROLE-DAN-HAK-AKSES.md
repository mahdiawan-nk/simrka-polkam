# Role dan Hak Akses (RBAC)

Aplikasi menggunakan **Filament Shield** (`bezhansalleh/filament-shield` v4.3) dan **Spatie Laravel Permission** untuk mengelola peran dan izin pada guard `web`.

---

## 1. Daftar Peran Aktual dan Kewenangan

| Role | Tanggung Jawab | Modul yang Dapat Diakses | Batasan Akses |
|---|---|---|---|
| `super_admin` | Administrasi sistem, pengguna, RBAC, identitas aplikasi, & pemeliharaan | Manajemen Pengguna, Peran & Izin (Shield), Identitas Aplikasi, Master Data, Manajemen Backup, Knowledge Center (kelola + panduan lengkap) | Page & Widget wajib memiliki izin eksplisit di Shield (tidak otomatis bypass oleh `Gate::before`). Alat Pengembang hanya aktif di non-production |
| `direktur` | Pimpinan tertinggi institusi; pemantauan kinerja makro & persetujuan final | Dasbor Direktur, Pengusulan & Review RKA, Pengajuan Pencairan Dana, Alokasi Pagu Unit, Pusat Panduan | Tidak mengelola pengaturan sistem/identitas aplikasi atau backup database |
| `wadir_keuangan` | Wakil Direktur II (BAKKU); verifikasi & persetujuan tingkat kedua | Dasbor Wadir II, Pengusulan & Review RKA, Pengajuan Pencairan Dana, Permintaan Buka Kunci RKA, Pusat Panduan | Tidak mengakses administrasi pengguna/Shield atau pengaturan identitas sistem |
| `keuangan` | Pengelola anggaran & verifikator teknis keuangan (Ka. BAKKU) | Dasbor Keuangan, Pengelolaan RKA (Jadwal, Pagu, COA, Generate, Impor Approved, Rekonsiliasi, Buka Kunci), Verifikasi Fund Request | Tidak mengakses manajemen peran/izin Shield, Identitas Aplikasi, atau Backup Database |
| `staf_keuangan` | Pemantau operasional keuangan (**Read-Only**) | Dasbor Pemantauan, melihat daftar & detail RKA, Fund Request, Pagu Unit, COA, Unit Kerja, dan Pusat Panduan | **Hanya baca (Read-Only)**: tidak dapat membuat, mengubah, memverifikasi, maupun menyetujui dokumen |
| `pemohon` | Pengusul anggaran & pencairan dana tingkat unit kerja | Dasbor Unit, Pengusulan RKA unit sendiri, Pengajuan Pencairan Dana unit sendiri, cetak PDF, dan Pusat Panduan | Dibatasi ketat pada unit kerja miliknya (`user_departments`); diblokir oleh Policy jika mengakses record/PDF/lampiran unit lain (`403`) |

*(Catatan: `panel_user` juga disediakan oleh konfigurasi Shield sebagai peran dasar autentikasi panel jika diaktifkan).*

---

## 2. Mekanisme Penegakan Otorisasi

1. **Perlindungan Panel Production (`FilamentUser`)**: `App\Models\User` mengimplementasikan `FilamentUser::canAccessPanel()` sehingga autentikasi tetap berjalan aman pada `APP_ENV=production`.
2. **Otorisasi Halaman & Sidebar (`HasPageShield`)**: Seluruh 7 halaman kustom (termasuk `BrandIdentitySettings`, `BackupManagement`, `CleanDataRkaPage`, `ManageReconciliationsPage`, `ManageUnlockRequestsPage`, dan `KnowledgeCenterPage`) menggunakan `HasPageShield` dan dikecualikan dari bypass `Gate::before` pada `AppServiceProvider`. Halaman tanpa izin otomatis disembunyikan dari sidebar dan mengembalikan `403 Forbidden` saat diakses via URL langsung.
3. **Otorisasi Record Lintas Unit (Anti-IDOR)**: `FundRequestPolicy` dan `RkaHeaderPolicy` memverifikasi kepemilikan `department_id` terhadap `user_departments` untuk role unit (`pemohon`), sementara `super_admin`, `direktur`, `keuangan`, `wadir_keuangan`, dan `auditor` memiliki akses lintas unit sesuai tugasnya.
4. **Pembatasan Konteks Multi-Peran (`ContextManager`)**: Pengguna yang memiliki lebih dari satu peran atau unit kerja dapat berpindah konteks melalui *Context Switcher*, dan tampilan tabel/dasbor/Knowledge Center langsung menyesuaikan konteks aktif.
