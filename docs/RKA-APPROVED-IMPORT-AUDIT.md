# Audit Fitur Import Approved RKA — SIMRKA Politeknik Kampar

**Repository:** `mahdiawan-nk/simrka-polkam`  
**Branch Aktif:** `feature/rka-approved-excel-import`  
**Tanggal:** 15 November 2026  
**Mode Pekerjaan:** Audit Read-Only (tanpa perubahan kode, permission, route, maupun database)

---

## 1. Executive Summary

Fitur **Import Approved RKA** (baik sebagai fitur import file Excel template RKA `APPROVED` maupun import dokumen RKA `APPROVED` ke dalam RKA `DRAFT`) **belum diimplementasikan sama sekali** di seluruh codebase pada branch `feature/rka-approved-excel-import`, `fix/bug-findings-remediation`, maupun `main`.

Yang sudah tersedia saat ini hanyalah:
1. **Import Item dari Excel** (`RkaActions::importExcel` → `RkaItemsImport`) pada halaman Penyusunan Formulir RKA (`RkaFormIndicatorAndItem`) untuk mengimpor item belanja usulan unit ke RKA `DRAFT`.
2. **Import Adjustment Section B ke Item RKA** (`RkaReviewActions::importAdjustmentToItem` → `RkaReviewService::importAdjustmentToItem`) pada halaman Review RKA (`ReviewRkaPage`).
3. **Export Approved RKA to Excel** (`Action::make('export_approved_excel')` → `RkaApprovedTemplateService::export`) pada tabel daftar RKA (`RkaHeadersTable`) yang baru selesai dikembangkan pada PHASE 2 (dengan batasan eksplisit: *"Jangan membuat fitur import Excel pada PHASE 2 ini"*).

Oleh karena itu, user unit (`pemohon`) tidak melihat tombol **Import Approved RKA** di halaman Penyusunan Formulir RKA bukan karena bug permission atau kondisi `visible()`, melainkan karena **fitur tersebut belum ada di aplikasi**.

---

## 2. Implementasi Aktual

Seluruh file terkait import/export RKA yang ditemukan di codebase:

| Kategori | File / Class | Fungsi Aktual |
|---|---|---|
| Importer Excel RKA | `app/Imports/RkaItemsImport.php` (`RkaItemsImport`) | Impor item usulan unit dari halaman `RkaFormIndicatorAndItem` (`RkaActions::importExcel`) |
| Importer Excel RKA | `app/Imports/RkaImportFormatExcel.php` (`RkaImportFormatExcel`) | Impor item usulan unit via `RkaItemService::importExcel()` |
| Form Upload Import | `app/Filament/Resources/RkaHeaders/Schemas/ImportExcelForm.php` | Form upload file `.xlsx` untuk `RkaActions::importExcel` |
| Action Penyusunan RKA | `app/Filament/Actions/Rka/RkaActions.php` | Berisi `submitRka`, `addProgram`, `addItem`, `editItem`, `importExcel` (`"Import Item dari Excel"`), `deleteItem`, `clearAllItems` |
| Action Review RKA | `app/Filament/Actions/Rka/RkaReviewActions.php` | Berisi `importAdjustmentToItem` (memindahkan baris `RkaAdjustment` Section B ke `RkaItem`, bukan upload Excel) |
| Export Approved RKA | `app/Services/RkaApprovedTemplateService.php`, `app/Exports/ApprovedRkaTemplateExport.php` | **Export** (bukan Import) RKA berstatus `APPROVED` ke `.xlsx` (`DAFTAR PENYESUAIAN` & `BELANJA`) |
| Tabel Daftar RKA | `app/Filament/Resources/RkaHeaders/Tables/RkaHeadersTable.php` | Berisi action `export_approved_excel` (`"Export Excel"`) untuk RKA `APPROVED` |

Tidak ditemukan class bernama `ApprovedRkaImport`, `ImportApprovedRka`, `RkaApprovedImport`, controller import, route import, maupun Livewire/Filament page khusus Import Approved RKA.

---

## 3. Lokasi Fitur

Karena fitur **Import Approved RKA** belum ada, berikut pemetaan lokasi fitur-fitur RKA import/export yang benar-benar ada saat ini:

| Komponen | Hasil Audit (`Import Item dari Excel`) | Hasil Audit (`Export Approved RKA`) | Hasil Audit (`Import Approved RKA`) |
|---|---|---|---|
| Nama fitur | Import Item dari Excel | Export Approved RKA to Excel | **Belum tersedia di codebase** |
| Lokasi menu | Manajemen RKA → RKA Header → Isi RKA | Manajemen RKA → RKA Header (Row Action) | Tidak ada |
| Navigation group | Manajemen RKA | Manajemen RKA | Tidak ada |
| Halaman | `RkaFormIndicatorAndItem` | `ListRkaHeaders` (`RkaHeadersTable`) | Tidak ada |
| Route | `rka-headers/{record}/form-indicator-and-item` | Livewire table action pada `rka-headers` | Tidak ada |
| Nama action | `importExcel` (`"Import Item dari Excel"`) | `export_approved_excel` (`"Export Excel"`) | Tidak ada |
| Kondisi tampil | Selalu tampil di header action `RkaFormIndicatorAndItem` selama halaman bisa dibuka | `status === 'APPROVED'` dan `$user->can('view', $record)` | - |
| Kondisi aktif | Di halaman `RkaFormIndicatorAndItem` (`DRAFT`) | RKA berstatus `APPROVED` | - |
| Permission | `View:RkaHeader` + `canAccessRecord()` di `mount()` | `View:RkaHeader` + `canAccessRecord()` (`RkaHeaderPolicy::view`) | - |
| Role yang diizinkan | `pemohon`, `super_admin`, `direktur`, `keuangan`, `wadir_keuangan` (sesuai policy `view`) | `pemohon` (unit sendiri), `super_admin`, `direktur`, `keuangan`, `wadir_keuangan` | - |

---

## 4. RBAC dan Permission

Hasil inspeksi tabel `roles`, `permissions`, `role_has_permissions` pada database `erka` serta `app/Policies/RkaHeaderPolicy.php`:

| Role | Permission RKA di DB | Dapat Melihat `Import Item dari Excel` | Dapat Melihat `Export Excel` (Approved) | Dapat Melihat `Import Approved RKA` | Dasar Authorization |
|---|---|---|---|---|---|
| `super_admin` | Seluruh permission `RkaHeader`, `RkaSchedule`, `RkaSetting`, `RkaGenerateConfig` | Ya (di halaman `form-indicator-and-item`) | Ya (jika status `APPROVED`) | **Tidak (fitur belum ada)** | `RkaHeaderPolicy::view` + `canAccessRecord()` |
| `pemohon` (User Unit) | `Create:RkaHeader`, `View:RkaHeader`, `ViewAny:RkaHeader` | Ya (hanya unit sendiri saat `DRAFT`) | Ya (hanya unit sendiri saat `APPROVED`) | **Tidak (fitur belum ada)** | `RkaHeaderPolicy::view` + `user_departments` |
| `wadir_keuangan` | `View:RkaHeader`, `ViewAny:RkaHeader` | Ya (jika membuka URL langsung) | Ya (semua unit saat `APPROVED`) | **Tidak (fitur belum ada)** | `RkaHeaderPolicy::view` |
| `direktur` | `View:RkaHeader`, `ViewAny:RkaHeader` | Ya (jika membuka URL langsung) | Ya (semua unit saat `APPROVED`) | **Tidak (fitur belum ada)** | `RkaHeaderPolicy::view` |
| `keuangan` | `NULL` (tidak ada permission `RkaHeader` di `role_has_permissions`) | Tidak (`403` pada `mount()` jika tidak diberi permission) | Tidak | **Tidak (fitur belum ada)** | `RkaHeaderPolicy::view` |

---

## 5. Business Logic

- **Import yang ada (`RkaItemsImport` / `RkaImportFormatExcel`):**
  - Ditujukan untuk mengimpor rincian item belanja ke `RkaHeader` (umumnya berstatus `DRAFT`) dari template import item biasa (`RkaItemsImport` mencocokkan berdasarkan `description`, bukan `ITEM_REF_ID` dari template `APPROVED`).
- **Export Approved RKA (`RkaApprovedTemplateService`):**
  - Telah menyiapkan technical identifier (`ITEM_REF_ID`, `ADJ_REF_ID`, `ORIGIN_ITEM_ID`, `IMPORTED_ITEM_ID`) pada worksheet `DAFTAR PENYESUAIAN` dan `BELANJA` sebagai fondasi untuk fitur Import Approved RKA di fase pengembangan berikutnya.
  - Seperti terdokumentasi di `docs/RKA-APPROVED-TEMPLATE-FLOW-AUDIT.md` dan `docs/RKA-APPROVED-EXCEL-TEMPLATE.md`, fitur **Import Approved RKA** belum dibangun karena RKA `APPROVED` sudah tersinkronisasi dengan `department_budgets` dan `fund_requests`, sehingga desain import memerlukan aturan bisnis khusus (apakah import masuk ke `RkaHeader` `DRAFT` baru/revisi, staging, atau update terbatas).

---

## 6. Analisis Penyebab

| Kemungkinan Penyebab | Klasifikasi | Penjelasan |
|---|---|---|
| Fitur Import Approved RKA belum diimplementasikan / belum terhubung ke UI maupun backend | **Confirmed Finding** | Tidak ada class importer, service, action, route, maupun test untuk Import Approved RKA di codebase. PHASE 2 baru menyelesaikan **Export** Approved RKA. |
| Tombol di halaman Penyusunan RKA bukan Import Approved RKA | **Confirmed Finding** | Tombol `"Import Item dari Excel"` di `RkaFormIndicatorAndItem` memanggil `RkaActions::importExcel()` (`RkaItemsImport`), yaitu import item usulan biasa. |
| Role `pemohon` tidak memiliki permission `View:RkaHeader` | **Not a Finding** | Query DB menunjukkan `pemohon` memiliki `Create:RkaHeader`, `View:RkaHeader`, `ViewAny:RkaHeader` dan lolos `RkaHeaderPolicy::view()` untuk unitnya sendiri. |
| Fitur hanya ada sebagai table action atau di halaman lain | **Confirmed Finding (untuk Export, bukan Import)** | Yang ada di `RkaHeadersTable` adalah `export_approved_excel` (`"Export Excel"`), bukan Import. |
| Fitur berada di branch lain | **Not a Finding** | Pemeriksaan `git branch -a` dan pencarian kode membuktikan tidak ada implementasi Import Approved RKA di `main`, `fix/bug-findings-remediation`, maupun `feature/rka-approved-excel-import`. |

---

## 7. Bukti Kode

1. `app/Filament/Resources/RkaHeaders/RkaHeaderResource.php` (baris 50–56):
   Hanya mendaftarkan page `index`, `create`, `edit`, `form-indicator-and-item`, `view`, dan `review`. Tidak ada page import approved RKA.
2. `app/Filament/Actions/Rka/RkaActions.php` (baris 243–290):
   `importExcel()` menggunakan `ImportExcelForm` dan `new RkaItemsImport($page->record->id, $indicatorId)` untuk impor item belanja unit biasa.
3. `app/Filament/Resources/RkaHeaders/Tables/RkaHeadersTable.php` (baris 220–248):
   Action untuk RKA `APPROVED` adalah `Action::make('export_approved_excel')` (`->label('Export Excel')`) yang memanggil `RkaApprovedTemplateService::export()`.
4. `routes/web.php` (baris 1–28):
   Tidak ada route khusus import RKA.
5. `docs/RKA-APPROVED-EXCEL-TEMPLATE.md` (Bagian 7):
   Menegaskan bahwa `ITEM_REF_ID`, `ADJ_REF_ID`, `ORIGIN_ITEM_ID`, dan `IMPORTED_ITEM_ID` disiapkan sebagai *technical reference untuk pengembangan import berikutnya*.

---

## 8. Test Coverage

- **Yang Sudah Ada:**
  - `tests/Feature/RkaApprovedExcelExportTest.php` menguji **Export** Approved RKA (RBAC, scope unit, struktur 2 sheet, formula, integrity, dan `RkaLog`).
- **Gap Pengujian (untuk Import Approved RKA):**
  - Belum ada test sama sekali untuk Import Approved RKA (baik otorisasi `super_admin`/user unit, validasi sumber `APPROVED` vs tujuan `DRAFT`, pencegahan duplikasi berbasis `ITEM_REF_ID`, maupun pencatatan `RkaLog`), karena fiturnya sendiri belum dibuat.

---

## 9. Rekomendasi

Karena fitur **Import Approved RKA** belum ada, implementasinya perlu dirancang sebagai **PHASE 3** setelah keputusan bisnis ditetapkan:

1. **Perbaikan Visibilitas UI (Setelah Fitur Dibuat):**
   - Tentukan lokasi UI yang tepat: di `RkaFormIndicatorAndItem` (untuk mengimpor item dari file template Approved RKA ke RKA `DRAFT` unit) atau di `ListRkaHeaders` / `ReviewRkaPage`.
2. **Perbaikan RBAC:**
   - Gunakan permission eksplisit atau policy method khusus (misalnya `importApproved` pada `RkaHeaderPolicy`) agar tidak tercampur dengan `View:RkaHeader`, dan terapkan `canAccessRecord()` untuk membatasi user unit hanya ke unit miliknya.
3. **Perbaikan Route/Backend Authorization:**
   - Validasi status RKA target (`DRAFT`), hak akses unit (`user_departments`), dan validasi struktur file template (`ITEM_REF_ID`, kode rekening, nominal) di service layer sebelum `DB::transaction()`.
4. **Perbaikan Business Logic:**
   - Jangan menimpa `RkaHeader` yang sudah berstatus `APPROVED` secara langsung (karena sudah tersinkron ke `department_budgets` dan `fund_requests`). Arahkan import ke `RkaHeader` berstatus `DRAFT` (misalnya pada jadwal RKA Perubahan/tahun berikutnya) dan catat aktivitasnya via `RkaActivityLogService`.
5. **Penambahan Test:**
   - Tambahkan feature test lengkap untuk RBAC (`super_admin`, `pemohon` unit sendiri, `pemohon` unit lain, user tanpa izin), validasi status, anti-duplikasi `ITEM_REF_ID`, rollback transaksi, dan `RkaLog`.

---

## 10. Kesimpulan dan Keputusan yang Dibutuhkan

- **Kesimpulan Eksplisit:** Fitur **Import Approved RKA belum tersedia** di codebase SIMRKA saat ini (yang telah selesai di PHASE 2 adalah **Export Approved RKA to Excel**). User unit tidak melihat fitur tersebut karena implementasinya memang belum dibuat, bukan karena kesalahan permission pada halaman Penyusunan Formulir RKA.
- **Keputusan Bisnis yang Dibutuhkan Sebelum Implementasi (PHASE 3):**
  1. **Target Pengguna:** Apakah Import Approved RKA ditujukan untuk **user unit (`pemohon`)** (misal sebagai baseline usulan tahun baru / revisi `DRAFT` milik unitnya sendiri), atau **khusus tim keuangan / `super_admin`**?
  2. **Skenario Import:** Apakah file Excel hasil export `APPROVED` diimpor ke **RKA `DRAFT` baru** (sebagai usulan awal/revisi), atau digunakan untuk **memperbarui item pada RKA tertentu**?
  3. **Penanganan Penyesuaian (`DAFTAR PENYESUAIAN`):** Apakah saat import hanya sheet `BELANJA` yang diimpor menjadi `RkaItem` (`DRAFT`), atau kolom review/penyesuaian juga ikut diproses?
