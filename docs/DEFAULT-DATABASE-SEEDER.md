# Default Database Seeder — Inisialisasi Deployment Pertama
**Aplikasi SIMRKA / e-RKA Politeknik Kampar**

---

## 1. Tujuan

Default database seeder dirancang untuk menginisialisasi database kosong pada deployment production pertama kali secara **idempotent**, **aman**, dan **bebas dari data transaksi dummy**.

Setelah prosedur inisialisasi dijalankan:
- Seluruh 27 unit kerja (`departments`) tersedia dengan relasi hierarki `parent_id`.
- Seluruh 72 kode akun COA (`accounts`: 19 Level 1–2 + 53 Level 3 Rincian) tersedia dengan relasi `parent_id`.
- Seluruh 6 role institusional (`super_admin`, `direktur`, `wadir_keuangan`, `keuangan`, `staf_keuangan`, `pemohon`) serta `panel_user` (jika aktif) tersedia.
- Seluruh permission Resource, Page, Widget, dan `ViewAllUnits:*` ter-sinkron melalui mekanisme Filament Shield.
- Akun `super_admin` awal dibuat berdasarkan kredensial environment (`.env`), tanpa password hardcoded.
- Pengaturan awal `BrandSetting` dan `RkaSetting` tersedia sebagai fallback default.

---

## 2. Daftar & Urutan Eksekusi Seeder

Diorkestrasi oleh `database/seeders/DatabaseSeeder.php` sesuai urutan dependency:

| Urutan | Seeder | Tanggung Jawab | Mekanisme Idempotensi |
|---:|---|---|---|
| 1 | `Departmentseeder` | Mengisi 27 unit kerja Politeknik Kampar & relasi `parent_id` | `firstOrCreate(['code' => ...])`, tidak menimpa nama unit yang sudah diubah admin |
| 2 | `AccountDataSeeder` | Mengisi 72 kode akun COA (Level 1–3) & relasi `parent_id` | `firstOrCreate(['code' => ...])`, menyinkronkan hierarki `level` & `parent_id` |
| 3 | `RoleSeeder` | Membuat role aktual (`super_admin`, `direktur`, `wadir_keuangan`, `keuangan`, `staf_keuangan`, `pemohon`, `panel_user`) | `firstOrCreate(['name' => ..., 'guard_name' => 'web'])` |
| 4 | `PermissionSeeder` | Menemukan seluruh permission Resource, Page, Widget, & `ViewAllUnits:*` via Filament Shield serta memberi izin awal hanya jika role belum memiliki permission non-widget | `firstOrCreate` permission; tidak menimpa konfigurasi role yang sudah diubah di Shield |
| 5 | `DashboardRbacSeeder` | Menyelaraskan role read-only `staf_keuangan` & permission `ViewAllUnits:*` | `firstOrCreate` & `syncPermissions` terukur untuk `staf_keuangan` |
| 6 | `DashboardWidgetPermissionSeeder` | Menyinkronkan matriks widget per role tanpa menghapus permission Resource/Page | Mempertahankan seluruh permission non-widget |
| 7 | `InitialAdminSeeder` | Membuat akun `super_admin` awal dari `.env` (`INITIAL_ADMIN_*`) | Tidak membuat duplikat & **tidak pernah menimpa password user existing** |
| 8 | `ApplicationSettingsSeeder` | Mengisi default `BrandSetting` & `RkaSetting` jika tabel masih kosong | Hanya mengisi jika tabel kosong / belum ada |

---

## 3. Environment Variables yang Dibutuhkan

Tambahkan pada `.env` production sebelum menjalankan seeder pertama kali:

```env
INITIAL_ADMIN_NAME="Administrator"
INITIAL_ADMIN_EMAIL="admin@polkam.ac.id"
INITIAL_ADMIN_PASSWORD="<password-kuat-unik>"
```

**Aturan Keamanan:**
- Jika belum ada user `super_admin` dan `INITIAL_ADMIN_EMAIL` atau `INITIAL_ADMIN_PASSWORD` kosong, `InitialAdminSeeder` akan menghentikan proses dengan `RuntimeException` dan **tidak akan membuat akun dengan password publik**.
- Password di-hash menggunakan `Hash::make()` dan tidak pernah dicatat ke log maupun output terminal.

---

## 4. Prosedur Inisialisasi & Pemeliharaan

### Inisialisasi Pertama di Production
```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
docker compose -f docker-compose.prod.yml exec app php artisan permission:cache-reset
```

### Menjalankan Ulang Seeder (Aman / Idempotent)
```bash
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```
> **Dilarang** menjalankan `php artisan migrate:fresh --seed` pada database production atau database yang sudah memiliki data transaksi.

### Perbedaan Lingkungan
- **Production**: Hanya menjalankan `DatabaseSeeder` (data master, RBAC, akun admin awal dari `.env`, pengaturan default). Tidak ada data transaksi dummy atau akun testing.
- **Development / Testing**: Jika membutuhkan data simulasi, gunakan factory/test terpisah (mis. `tests/Feature/*`) pada database pengujian terisolasi (`sqlite :memory:` atau DB testing khusus).

---

## 5. Verifikasi Otomatis

Pengujian pada `tests/Feature/DefaultDatabaseSeederTest.php` memverifikasi:
- Penolakan aman saat kredensial `INITIAL_ADMIN_*` kosong pada database kosong.
- Kelengkapan 27 department, 72 akun COA beserta `parent_id`, seluruh role & permission Shield, pembuatan akun admin awal, serta pengaturan default.
- Idempotensi penuh saat `DatabaseSeeder` dijalankan ulang (tanpa duplikasi, tanpa menimpa password admin, dan tanpa mengembalikan permission yang telah dicabut oleh administrator).
