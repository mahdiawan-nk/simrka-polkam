# Audit Codebase — Fund Request Berbasis Approved RKA

**Tanggal Audit:** 2026-11-16  
**Branch:** `feature/rka-approved-excel-import`  
**Tujuan:** Mengubah sumber item Fund Request dari input manual menjadi pemilihan langsung item `RkaItem` dari `RkaHeader` berstatus `APPROVED` pada periode aktif (`RkaSchedule`), dengan kontrol saldo dua tingkat (per item dan per rekening `DepartmentBudget`).

---

## 1. Arsitektur Fund Request Aktual

- **Resource & Pages:** [FundRequestResource.php](file:///home/mahdi-nk/Project/Polkam/sistem-keuangan/app/Filament/Resources/FundRequests/FundRequestResource.php) mendaftarkan:
  - `index` → `ListFundRequests`
  - `create` → `CreateFundRequestCustom`
  - `edit` → `EditFundRequestCustom`
  - `verify` → `VerifyFundRequest`
- **Model:**
  - `FundRequest` (`fund_requests`)
  - `FundRequestItem` (`fund_request_items`)
  - `FundRequestVerification` (`fund_request_verifications`)
  - `FundRequestApproval` (`fund_request_approvals`)
- **Kondisi Saat Ini:**
  - `CreateFundRequestCustom` dan `EditFundRequestCustom` membiarkan pemohon memilih `account_id` dan mengisi `description`, `volume`, `unit`, serta `unit_price` secara manual.
  - Belum ada keterkaitan FK ke `rka_headers` maupun `rka_items`.
  - Tidak ada validasi server-side atas sisa anggaran per item RKA maupun sisa pagu rekening `DepartmentBudget`.

---

## 2. Struktur Database & Relasi Aktual

- **`fund_requests`:** `id`, `document_number` (unique), `request_date`, `fiscal_year` (`varchar(9)`), `department_id` (FK → `departments.id`), `activity_name`, `execution_date`, `responsible_person`, `disbursement_type`, `recipient_name`, `bank_account_info`, `attachments` (`json`), `total_requested` (`decimal(15,2)`), `total_approved` (`decimal(15,2)`), `status` (`enum('draft','submitted','verified_kabakku','verified_wadir_bakku','approved','rejected')`), `created_by` (FK → `users.id`).
- **`fund_request_items`:** `id`, `fund_request_id` (FK → `fund_requests.id` ON DELETE CASCADE), `account_id` (FK → `accounts.id`), `description`, `volume` (`decimal(10,2)`), `unit` (`varchar(50)`), `unit_price` (`decimal(15,2)`), `approved_volume` (`decimal(10,2)` nullable), `total_price` (`decimal(15,2)`), `approved_price` (`decimal(15,2)` nullable), `verification_status` (`enum('approved','rejected','partial')` nullable), `finance_note`.
- **`department_budgets`:** `id`, `department_id`, `account_id`, `fiscal_year`, `schedule_id`, `allocated_amount` (`decimal(15,2)`), unique key `dept_account_fiscal_unique (department_id, account_id, fiscal_year)`.

---

## 3. Workflow Status & Mekanisme Saldo

| `fund_requests.status` | Pengaruh terhadap Saldo Anggaran | Nominal yang Diperhitungkan |
|---|---|---|
| `draft` | Menahan sementara (jika ada item) | `total_price` item |
| `submitted` | Menahan sementara | `total_price` item |
| `verified_kabakku` | Menahan sementara | `approved_price` jika `verification_status` `approved`/`partial`, `0` jika item `rejected`, else `total_price` |
| `verified_wadir_bakku` | Menahan sementara | Sama seperti di atas |
| `approved` | Mengurangi saldo permanen | `approved_price` jika `verification_status` `approved`/`partial`, `0` jika item `rejected`, else `total_price` |
| `rejected` | Melepaskan saldo (tidak diperhitungkan) | `0` |

Penghapusan pengajuan (`delete`) menghapus baris `fund_request_items` (cascade), sehingga saldo otomatis dilepaskan.

---

## 4. Sumber Data RKA Approved & Penentuan Periode Aktif

- **Periode Aktif (`RkaSchedule`):**
  - Query `RkaSchedule::query()->where('is_active', true)->get()`.
  - Jika `0` jadwal aktif → blokir pengajuan dan tampilkan pesan jelas.
  - Jika `> 1` jadwal aktif → blokir pengajuan karena periode aktif ambigu (jangan memilih acak).
  - Jika tepat `1` jadwal aktif → gunakan `schedule_id` dan `fiscal_year` dari jadwal tersebut.
- **RKA Approved Unit:**
  - `RkaHeader` dengan `schedule_id = $activeSchedule->id`, `department_id = $activeDeptId`, dan `status = RkaHeader::STATUS_APPROVED`.
- **Item RKA yang Dapat Dipilih:**
  - `RkaItem` pada `rka_header_id` tersebut dengan:
    - `status IN ('APPROVED', 'ACCEPTED', 'REDUCED', 'TRIMMED')`
    - `approved_total > 0` dan `approved_volume > 0`
    - `account_id IS NOT NULL`
    - Sisa anggaran item `> 0`.

---

## 5. RBAC & Organizational Scope

- `ContextManager::getActiveDepartment()` menentukan unit aktif pemohon; `department_id` tidak boleh dimanipulasi dari browser untuk mengakses unit lain.
- `FundRequestPolicy` tetap menjadi otorisasi utama (`create`, `update`, `view`, `delete`).

---

## 6. Risiko Integrasi & Keputusan Migration

- **Migration Baru Diperlukan (Non-Destruktif):**
  - Menambahkan kolom nullable `rka_header_id` pada `fund_requests` (FK → `rka_headers.id` ON DELETE SET NULL).
  - Menambahkan kolom nullable `rka_item_id` (FK → `rka_items.id` ON DELETE SET NULL) beserta kolom snapshot (`rka_account_code_snapshot`, `rka_account_name_snapshot`, `rka_approved_volume_snapshot`, `rka_approved_total_snapshot`) pada `fund_request_items`.
  - Kolom dibuat nullable agar data transaksi historis lama tetap utuh tanpa migration destruktif, sementara seluruh pengajuan baru melalui `CreateFundRequestCustom` dan `EditFundRequestCustom` (untuk pengajuan berbasis RKA) wajib memvalidasi `rka_header_id` dan `rka_item_id`.

---

## 7. Daftar File yang Akan Dibuat / Diubah

1. Migration baru: `database/migrations/2026_11_16_214000_add_rka_links_to_fund_requests_tables.php`
2. Model: `app/Models/FundRequest.php`, `app/Models/FundRequestItem.php`
3. Service terpusat: `app/Services/FundRequest/FundRequestBudgetService.php`
4. Livewire Pages: `app/Filament/Resources/FundRequests/Pages/CreateFundRequestCustom.php`, `EditFundRequestCustom.php`
5. Blade Views: `resources/views/filament/resources/fund-requests/pages/create-fund-request-custom.blade.php`, `edit-fund-request-custom.blade.php`
6. Feature Tests: `tests/Feature/FundRequest/FundRequestApprovedRkaTest.php`
7. Dokumentasi Hasil: `docs/FUND-REQUEST-APPROVED-RKA.md`
