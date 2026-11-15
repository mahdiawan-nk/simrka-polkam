# SIMRKA POLKAM — BUG REMEDIATION SUMMARY

**Branch:** `fix/bug-findings-remediation`  
**Referensi Audit:** `BUG_FINDINGS_REPORT.md`  
**Tracker:** `BUG_REMEDIATION_TRACKER.md`  
**Hasil Verifikasi Akhir:** **18 passed (77 assertions)** pada MySQL 8.0 (`DatabaseTransactions`) + **Laravel Pint passed**

---

## Ringkasan Eksekutif

Seluruh **18 confirmed bugs** (`BUG-001` hingga `BUG-018`) dari `BUG_FINDINGS_REPORT.md` telah diperbaiki secara sistematis berdasarkan urutan prioritas (`P0` → `P1` → `P2` → `P3` → `P4`), diformat dengan Laravel Pint di dalam container `erka_app`, dan diverifikasi menggunakan suite pengujian Pest pada database MySQL 8.0 (`erka`).

| Prioritas | Severity | Jumlah Bug | Status |
|---|---|---|---|
| **P0** | SEV-1 Critical | 4 (`BUG-001`..`BUG-004`) | Verified |
| **P1** | SEV-2 High | 7 (`BUG-005`..`BUG-011`) | Verified |
| **P2** | SEV-3 Medium | 5 (`BUG-012`..`BUG-016`) | Verified |
| **P3** | SEV-4 Low | 1 (`BUG-017`) | Verified |
| **P4** | SEV-5 Cosmetic | 1 (`BUG-018`) | Verified |
| **Total** | | **18** | **18 / 18 Verified** |

---

## Detail Perbaikan per Fase

### Phase 1 — P0 (SEV-1 Critical)
1. **BUG-001 — Aritmatika pengurangan & penulisan ke generated column `proposed_total` (`app/Services/RkaReviewService.php`)**
   - Menghentikan penulisan ke `proposed_volume`, `unit_price`, dan `proposed_total` (MySQL `STORED GENERATED` column) pada `reviewItem()`, memfilter item dengan `where('rka_header_id', $record->id)`, serta memperbaiki perhitungan `REJECTED_WITH_ADJUSTMENT` dan `$adjustedProposedAmount`.
2. **BUG-002 — Self-approval & bypass otorisasi pada `VerifyFundRequest` (`app/Filament/Resources/FundRequests/Pages/VerifyFundRequest.php`)**
   - Menambahkan `canAccess()` dan pengecekan role verifier (`keuangan`, `wadir_keuangan`, `direktur`) + policy `view` pada `mount()` dan `saveVerification()`, serta mengganti cabang `else` dengan `abort(403)`.
3. **BUG-003 — `BackupManagement` terbuka tanpa RBAC (`app/Filament/Pages/Database/BackupManagement.php`)**
   - Menambahkan trait `HasPageShield` dan override `canAccess()` dengan prinsip *default deny* (hanya `super_admin` atau pemegang permission halaman Shield).
4. **BUG-004 — Celah IDOR lintas unit (`FundRequestPolicy`, `RkaHeaderPolicy`, `FundRequestPdfController`, `RkaFormIndicatorAndItem`, `ReviewRkaPage`, `EditFundRequestCustom`)**
   - Menambahkan scoping kepemilikan `department_id` pada `FundRequestPolicy` dan `RkaHeaderPolicy` untuk role non-privileged, pengecekan `can('view', $record)` pada `FundRequestPdfController::preview()`, serta otorisasi pada `mount()` ketiga custom page.

### Phase 2 — P1 (SEV-2 High)
5. **BUG-005 — Kelas `DatabaseBackupService` hilang (`app/Services/Database/DatabaseBackupService.php`, `app/Jobs/RunDatabaseBackup.php`)**
   - Mengimplementasikan `DatabaseBackupService::execute(BackupRun $run)` untuk format `sql` dan `csv` lengkap dengan kalkulasi `file_size`, `row_count`, dan `checksum` SHA-256, serta memperbaiki leading newline sebelum `<?php` pada `RunDatabaseBackup.php`.
6. **BUG-006 — Referensi kolom non-existent `rka_status` & `review_notes` (`RkaItemService`, `RkaApprovalService`, `RkaActions`)**
   - Mengganti `rka_status` menjadi `status` dan menghapus penulisan `review_notes` ke `rka_headers`.
7. **BUG-007 — `EditFundRequestCustom` menimpa `department_id` dan memaksa `status = 'submitted'` (`app/Filament/Resources/FundRequests/Pages/EditFundRequestCustom.php`)**
   - Menggunakan `$this->department_id ??= $activeDept->id` pada `setDepartmentsActive()` dan mempertahankan `$this->record->status` saat `save()`.
8. **BUG-008 — `User::departments()` tidak memuat kolom pivot (`app/Models/User.php`)**
   - Menambahkan `->withPivot(['assignment_type', 'start_date', 'end_date'])` agar `ContextManager::getActiveAssignmentType()` membaca status `plt` dengan benar.
9. **BUG-009 — Auto-mutasi `submitted` → `IN_REVIEW` saat membuka `ReviewRkaPage` (`ReviewRkaPage.php`, `RkaReviewService.php`)**
   - Membatasi transisi otomatis `IN_REVIEW` hanya jika user memiliki role reviewer (`keuangan`, `wadir_keuangan`, `direktur`).
10. **BUG-010 — `RkaActions::editItem()` memanggil relasi `rkaIndicator` (`app/Filament/Actions/Rka/RkaActions.php`)**
    - Mengubah `$item->rkaIndicator` menjadi `$item->indicator`.
11. **BUG-011 — Inkonsistensi status `RkaItem` (`RkaApprovalService.php`, `RkaItem.php`)**
    - Menambahkan `STATUS_APPROVED` pada `RkaItem` dan memperbarui query agregasi `approveByDirektur()` agar mencakup `STATUS_APPROVED`, `STATUS_ACCEPTED`, dan `STATUS_REDUCED`.

### Phase 3 — P2 (SEV-3 Medium)
12. **BUG-012 — Bulk action `RkaReviewService` tanpa filter `rka_header_id` (`app/Services/RkaReviewService.php`)**
    - Menambahkan `where('rka_header_id', $record->id)` pada `bulkApproveItems()` dan `bulkMoveItems()`.
13. **BUG-013 — Hardcoded `'2026/2027'` (`RkaSchedule::currentFiscalYear()`, 11 Widgets, `CreateFundRequestCustom.php`)**
    - Menambahkan `RkaSchedule::currentFiscalYear()` dan mengganti seluruh literal `'2026/2027'` pada 11 widget dan `CreateFundRequestCustom`.
14. **BUG-014 — `orWhere` tanpa grouping pada `RkaHeader::incomingAdjustments()` (`app/Models/RkaHeader.php`)**
    - Mengelompokkan kondisi `destination_unit_id` dan `target_unit_id` di dalam closure `where(...)`.
15. **BUG-015 — Inkonsistensi filter `is_imported` pada `calculatePaguRka()` (`app/Models/RkaHeader.php`)**
    - Menambahkan `->where('is_imported', 0)` pada `calculatePaguRka()` agar konsisten dengan `calculateProposedTotal()`.
16. **BUG-016 — Konfigurasi `super_admin.define_via_gate` (`config/filament-shield.php`)**
    - Mengubah `define_via_gate` menjadi `true` agar role `super_admin` terintersepsi melalui `Gate::before`.

### Phase 4 & 5 — P3 (SEV-4 Low) & P4 (SEV-5 Cosmetic)
17. **BUG-017 — Inefisiensi query widget (`FinanceStatsOverview.php`, `SuperAdminSystemHealth.php`)**
    - Mengganti `->get()` full-model loading dengan `selectRaw('COALESCE(SUM(...))')` di `FinanceStatsOverview`, serta menggabungkan 5 query `count()` menjadi 1 query `GROUP BY status` di `SuperAdminSystemHealth`.
18. **BUG-018 — Typo grup navigasi & file duplikat**
    - Memperbaiki `'Manajeman RKA'` → `'Manajemen RKA'` pada `RkaSettingResource`, `RkaScheduleResource`, `RkaGenerateConfigResource`, dan `RkaHeaderResource`, serta menghapus `app/Filament/Resources/RkaHeaders/Pages/ReviewRkaPage copy.php`.

---

## Verifikasi Pengujian

Seluruh suite pengujian dijalankan melalui container Docker `erka_app` terhubung ke `erka_db` (MySQL 8.0):

```bash
docker compose exec -e DB_CONNECTION=mysql -e DB_HOST=db -e DB_PORT=3306 -e DB_DATABASE=erka -e DB_USERNAME=erka -e DB_PASSWORD=erka app php artisan test --compact \
  tests/Feature/RkaReviewServiceTest.php \
  tests/Feature/AuthAndIdorSecurityTest.php \
  tests/Feature/RkaSchemaAndStatusTest.php \
  tests/Feature/FundRequestEditAndContextTest.php \
  tests/Feature/DatabaseBackupServiceTest.php \
  tests/Feature/Phase3MediumBugsTest.php \
  tests/Feature/Phase4And5BugsTest.php
```

- **Hasil:** `18 passed (77 assertions)`
- **Formatting:** `docker compose exec app vendor/bin/pint --dirty --format agent` selesai tanpa error.
