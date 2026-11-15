# Implementasi Fitur Import Approved RKA (PHASE 3)

## 1. Ringkasan Arsitektur

Fitur **Import Approved RKA** dibangun di atas **Service Layer** dan terintegrasi dengan **Filament 5.x**, **Laravel Policies**, serta **RkaActivityLogService**:

- `App\Services\ApprovedRkaImport\ApprovedRkaImportService`:
  - `authorizeUserForDepartment()` — otorisasi `pemohon` berdasarkan relasi `user_departments` (`super_admin` diizinkan lintas unit).
  - `preview()` — parsing read-only worksheet `BELANJA` dan `DAFTAR PENYESUAIAN`, validasi kode rekening, duplikasi `source_ref_id`, status item/penyesuaian, dan menyimpan hasil preview pada Cache (30 menit, terikat `user_id`).
  - `executeImport()` — membuat `RkaHeader` langsung dengan `status = APPROVED` dan `is_locked = true`, membuat `RkaIndicator`, `RkaItem` (`source_ref_id`), `RkaAdjustment` (status `PENDING` dipaksa masuk `SECTION_A` tanpa membuat `DepartmentBudget`), `RkaApprovedImportBatch` (`INITIAL`), serta mencatat `RkaLog`.
  - `executeReimport()` — memvalidasi `RkaUnlockRequest::isReimportActive()`, melakukan rekonsiliasi berdasarkan `source_ref_id`, menjaga riwayat batch lama, menandai request `USED`, menjaga `status = APPROVED`, dan mengunci kembali (`is_locked = true`).
- `App\Services\ApprovedRkaImport\RkaUnlockRequestService`:
  - `submitRequest()`, `approveRequest()`, `rejectRequest()` dengan `DB::transaction()` + `lockForUpdate()` dan audit trail `RkaActivityLogService`.

## 2. Kontrol Akses & Kebijakan (RBAC)

- `App\Policies\RkaHeaderPolicy`:
  - `importApproved()`: mengizinkan `pemohon` dan `super_admin` (atau permission `ImportApproved:RkaHeader`).
  - `requestUnlock()`: mengizinkan `pemohon` milik unit terkait (atau `super_admin`) untuk RKA berstatus `APPROVED` dan `is_locked = true`.
  - `update()` dan `delete()`: memblokir perubahan/penghapusan jika `$rkaHeader->is_locked && !$user->hasRole('super_admin')`.
- `App\Policies\RkaUnlockRequestPolicy`:
  - `approve()` / `reject()`: hanya tim keuangan (`keuangan`, `wadir_keuangan`, `super_admin`) atau pemegang permission terkait.
- `App\Filament\Actions\Rka\RkaActions`:
  - Seluruh aksi mutasi (`addProgram`, `addItem`, `editItem`, `importExcel`, `deleteItem`, `clearAllItems`) memeriksa `ensureEditable()` di backend sehingga RKA terkunci tidak dapat dimodifikasi.

## 3. Antarmuka Filament

- `App\Filament\Resources\RkaHeaders\Pages\ImportApprovedRkaPage` (`/import-approved` & `?reimport={id}`)
- `App\Filament\Pages\Rka\ManageUnlockRequestsPage` (`Manajemen RKA` → `Permintaan Buka Kunci RKA`)
- Integrasi tombol pada `ListRkaHeaders` (`Import Approved RKA`) dan `RkaHeadersTable` (`Ajukan Buka Kunci`, `Import Ulang Approved`).
