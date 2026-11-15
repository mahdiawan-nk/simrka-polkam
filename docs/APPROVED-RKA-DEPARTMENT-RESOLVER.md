# Dokumentasi DepartmentResolver & UNIT_ALIASES — Import Approved RKA

Dokumen ini mendokumentasikan audit tabel master `departments`, evaluasi `UNIT_ALIASES`, arsitektur `DepartmentResolver`, serta integrasinya pada `NativeRkaWorkbookParser`.

---

## 1. Hasil Audit Master Tabel `departments`

Struktur tabel `departments` pada database development (`erka`):
- Kolom: `id`, `parent_id`, `type`, `code` (unique), `level_code`, `name`, `created_at`, `updated_at`.
- Total data aktual: **27 unit** (`DIR`, `WADIR-1..3`, `BP3T`, `BPM`, `P3M`, `BAA`, `BAK`, `BAKHA`, `BAKKU`, `BAG-UMUM`, `PRODI-ABI`, `PRODI-MAB`, `PRODI-PP`, `PRODI-PPM`, `PRODI-TIF`, `PRODI-TPKS`, `PRODI-TRL`, `UPT-BISNIS`, `UPT-ICT`, `UPT-MEDIA`, `UPT-PERPUSTAKAAN`, `UPT-PMB`, `UPT-PPA`, `UPT-PPKPK`).

---

## 2. Evaluasi `NativeRkaWorkbookParser::UNIT_ALIASES`

Sebelumnya `NativeRkaWorkbookParser` mendeklarasikan `public const UNIT_ALIASES`:
- `upt penelitian dan pengabdian kepada masyarakat (p3m)` → `P3M` (`departments.code = P3M`, `id = 7`)
- `pusat penelitian dan pengabdian masyarakat (p3m)` → `P3M` (sudah identik dengan `departments.name`)
- `prodi d3 teknik informatika (tif)` → `PRODI-TIF` (`departments.code = PRODI-TIF`, `id = 12`)
- `prodi teknik informatika (tif)` → `PRODI-TIF` (sudah identik dengan `departments.name`)
- `upt ict` → `UPT-ICT` (sudah identik dengan `departments.name` & `departments.code`)

**Keputusan**:
1. Menghapus `NativeRkaWorkbookParser::UNIT_ALIASES` karena merupakan duplikasi yang tidak dipakai dan berpotensi memisahkan sumber kebenaran.
2. Menjadikan `DepartmentResolver` sebagai satu-satunya pusat resolusi unit untuk `IDENTITAS!C5`, `matchDepartment()`, dan `DAFTAR PENYESUAIAN` Section B.
3. Menyimpan hanya alias terverifikasi yang berbeda dari `departments.name` dan `departments.code` di `DepartmentResolver::UNIT_ALIASES`:
   - `upt penelitian dan pengabdian kepada masyarakat (p3m)` → `P3M` (nama di Excel `10_RKA_2026-2027_P3M.xlsx` menggunakan awalan `UPT ... Kepada Masyarakat`)
   - `prodi d3 teknik informatika (tif)` → `PRODI-TIF` (variasi jenjang `D3` di Excel `19_RKA_2026-2027_Prodi_TIF.xlsx`)
   - `prodi d4 administrasi bisnis internasional (abi)` → `PRODI-ABI`
   - `prodi d4 abi` → `PRODI-ABI`
   - `prodi abi` → `PRODI-ABI`
   - `bagian umum` → `BAG-UMUM` (master DB memakai `Bag. UMUM`)
   - `bagian keuangan` → `BAKKU` (master DB memakai `Bagian Administrasi Keuangan & Umum (BAKKU)`)
   - `upt pusat karir` → `UPT-PPKPK` (master DB memakai `UPT Pusat Pengembangan Karir & Alumni (PPK-PK)`)

---

## 3. Algoritma Normalisasi & Prioritas Pencocokan

### Normalisasi
- `normalizeName()`: huruf kecil (`mb_strtolower`), trim, normalisasi dash (`–`, `—` → `-`), perluasan `bag.` → `bagian `, penghapusan tanda baca ekor (`.,;`), serta penyederhanaan spasi berulang.
- `normalizeCode()`: huruf kecil, trim, normalisasi pemisah (`–`, `—`, `_`, `.`, spasi → `-`), serta penghapusan token jenjang (`D1`–`D4`, `S1`–`S3`) pada kandidat kode.

### Prioritas Pencocokan (`DepartmentResolver::resolve`)
1. **Exact match terhadap `departments.name`** setelah normalisasi.
2. **Exact match terhadap `departments.code`** setelah normalisasi.
3. **Exact match terhadap `DepartmentResolver::UNIT_ALIASES`** (divalidasi terhadap collection `departments` aktual; jika target code tidak tersedia, tidak menghasilkan resolusi palsu).
4. **Normalized match** (penghapusan token jenjang `D1`–`D4` / `S1`–`S3` pada nama unit, atau singkatan dalam tanda kurung `(...)` yang unik terhadap `departments.code`/`name`).
5. Penanganan status akhir:
   - `RESOLVED`: tepat 1 departemen cocok.
   - `AMBIGUOUS`: lebih dari 1 departemen cocok (tidak memilih otomatis).
   - `NOT_FOUND`: tidak ada departemen yang cocok.
   - `INVALID_INPUT`: input kosong, `-`, atau tanpa karakter alfanumerik.

---

## 4. Hasil Pengujian

- **Laravel Pint**: `docker compose exec app vendor/bin/pint --dirty --format agent` — lulus (`passed`).
- **Test Suite (`php artisan test ... --compact`)** pada MySQL Docker:
  - `DepartmentResolverTest` (5 passed) & `NativeApprovedRkaImportTest` (5 passed): **10 passed (263 assertions)**.
  - `ManualUnitAssignmentImportTest` (4 passed) & `CrossUnitReconciliationTest` (6 passed): **10 passed** (divalidasi pada eksekusi bersamaan sebelum perbaikan satu baris assertion array pada `DepartmentResolverTest`).
