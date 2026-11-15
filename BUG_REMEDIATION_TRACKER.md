# BUG REMEDIATION TRACKER

**Proyek:** SIMRKA Polkam (`Erka`)  
**Branch:** `fix/bug-findings-remediation`  
**Referensi:** `BUG_FINDINGS_REPORT.md`  

---

## Ringkasan Status

| Bug ID | Description | Severity | Priority | Status | Fix Reference | Test Status |
|---|---|---|---|---|---|---|
| **BUG-001** | Bug aritmatika pengurangan & penulisan ke generated column `proposed_total` pada `RkaReviewService` | SEV-1 | P0 | Verified | `app/Services/RkaReviewService.php` | Passed (`tests/Feature/RkaReviewServiceTest.php`: 2 passed, 10 assertions) |
| **BUG-002** | Celah self-approval & bypass otorisasi pada `VerifyFundRequest` | SEV-1 | P0 | Verified | `app/Filament/Resources/FundRequests/Pages/VerifyFundRequest.php` | Passed (`tests/Feature/AuthAndIdorSecurityTest.php`) |
| **BUG-003** | Halaman `BackupManagement` terbuka tanpa proteksi RBAC (`HasPageShield` / `canAccess`) | SEV-1 | P0 | Verified | `app/Filament/Pages/Database/BackupManagement.php` | Passed (`tests/Feature/AuthAndIdorSecurityTest.php`) |
| **BUG-004** | Celah IDOR lintas unit pada `FundRequestPdfController`, Custom Pages, dan Policies | SEV-1 | P0 | Verified | `FundRequestPolicy`, `RkaHeaderPolicy`, `FundRequestPdfController`, `RkaFormIndicatorAndItem`, `ReviewRkaPage`, `EditFundRequestCustom` | Passed (`tests/Feature/AuthAndIdorSecurityTest.php`: 3 passed, 16 assertions) |
| **BUG-005** | Kelas `DatabaseBackupService` tidak ada di codebase (`RunDatabaseBackup` gagal) | SEV-2 | P1 | Verified | `app/Services/Database/DatabaseBackupService.php`, `app/Jobs/RunDatabaseBackup.php` | Passed (`tests/Feature/DatabaseBackupServiceTest.php`: 1 passed, 13 assertions) |
| **BUG-006** | Referensi kolom tidak ada (`rka_status` & `review_notes`) pada `RkaItemService`, `RkaApprovalService`, `RkaActions` | SEV-2 | P1 | Verified | `RkaItemService`, `RkaApprovalService`, `RkaActions` | Passed (`tests/Feature/RkaSchemaAndStatusTest.php`) |
| **BUG-007** | `EditFundRequestCustom` menimpa `department_id` dan memaksa `status = 'submitted'` | SEV-2 | P1 | Verified | `app/Filament/Resources/FundRequests/Pages/EditFundRequestCustom.php` | Passed (`tests/Feature/FundRequestEditAndContextTest.php`) |
| **BUG-008** | Relasi `User::departments()` tidak memuat kolom pivot sehingga konteks PLT selalu `false` | SEV-2 | P1 | Verified | `app/Models/User.php` | Passed (`tests/Feature/FundRequestEditAndContextTest.php`: 2 passed, 4 assertions) |
| **BUG-009** | Auto-mutasi status `SUBMITTED` → `IN_REVIEW` hanya karena membuka `ReviewRkaPage` | SEV-2 | P1 | Verified | `ReviewRkaPage`, `RkaReviewService` | Passed (`tests/Feature/RkaSchemaAndStatusTest.php`) |
| **BUG-010** | `RkaActions::editItem()` memanggil relasi non-existent `rkaIndicator` | SEV-2 | P1 | Verified | `app/Filament/Actions/Rka/RkaActions.php` | Passed (`tests/Feature/RkaSchemaAndStatusTest.php`) |
| **BUG-011** | Inkonsistensi nilai status `RkaItem` (`ACCEPTED` vs `APPROVED`) | SEV-2 | P1 | Verified | `RkaApprovalService`, `RkaItem` | Passed (`tests/Feature/RkaSchemaAndStatusTest.php`: 4 passed, 10 assertions) |
| **BUG-012** | Bulk action `RkaReviewService` tidak memfilter `rka_header_id` | SEV-3 | P2 | Verified | `app/Services/RkaReviewService.php` | Passed (`tests/Feature/Phase3MediumBugsTest.php`) |
| **BUG-013** | Hardcoded tahun anggaran `'2026/2027'` pada 11 Widget dan `CreateFundRequestCustom` | SEV-3 | P2 | Verified | `RkaSchedule::currentFiscalYear()`, 11 Widgets, `CreateFundRequestCustom` | Passed (`tests/Feature/Phase3MediumBugsTest.php`) |
| **BUG-014** | `orWhere` tanpa grouping pada relasi `RkaHeader::incomingAdjustments()` | SEV-3 | P2 | Verified | `app/Models/RkaHeader.php` | Passed (`tests/Feature/Phase3MediumBugsTest.php`) |
| **BUG-015** | Inkonsistensi filter `is_imported` antara `calculatePaguRka()` dan `calculateProposedTotal()` | SEV-3 | P2 | Verified | `app/Models/RkaHeader.php` | Passed (`tests/Feature/Phase3MediumBugsTest.php`) |
| **BUG-016** | Konfigurasi `super_admin` (`define_via_gate => false`) & ketidakseimbangan permission role | SEV-3 | P2 | Verified | `config/filament-shield.php` | Passed (`tests/Feature/Phase3MediumBugsTest.php`: 4 passed, 11 assertions) |
| **BUG-017** | Inefisiensi query pada `FinanceStatsOverview` & `SuperAdminSystemHealth` | SEV-4 | P3 | Verified | `FinanceStatsOverview`, `SuperAdminSystemHealth` | Passed (`tests/Feature/Phase4And5BugsTest.php`) |
| **BUG-018** | Typo grup navigasi (`Manajeman RKA`) & file duplikat (`ReviewRkaPage copy.php`) | SEV-5 | P4 | Verified | 4 RKA Resources, removed `ReviewRkaPage copy.php` | Passed (`tests/Feature/Phase4And5BugsTest.php`: 2 passed, 13 assertions) |

---

## Log Detail Perbaikan

*(Akan diperbarui setiap kali satu kelompok bug selesai diperbaiki dan diverifikasi.)*
