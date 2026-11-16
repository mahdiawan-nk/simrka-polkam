# Dokumentasi Modul — Fund Request Berbasis Approved RKA

**Tanggal:** 2026-11-16  
**Branch:** `feature/rka-approved-excel-import`  
**Audit Pendukung:** [FUND-REQUEST-APPROVED-RKA-AUDIT.md](FUND-REQUEST-APPROVED-RKA-AUDIT.md)

---

## 1. Arsitektur Sumber Item & Periode Aktif

Seluruh pengajuan Fund Request baru (`CreateFundRequestCustom`) dan perubahan (`EditFundRequestCustom`) bersumber langsung dari `RkaItem` pada `RkaHeader` yang telah disahkan (`status = APPROVED`) pada periode anggaran aktif (`RkaSchedule`).

- **Penentuan Periode Aktif (`FundRequestBudgetService::resolveActiveSchedule()`):**
  - Mengambil `RkaSchedule` dengan `is_active = true`.
  - Jika `0` jadwal aktif → diblokir (tidak fallback ke periode sebelumnya).
  - Jika `> 1` jadwal aktif → diblokir karena ambigu (tidak memilih acak).
- **Penentuan Unit & RKA Approved (`resolveApprovedRkaContext()`):**
  - Menggunakan departemen aktif dari `ContextManager` (atau departemen yang dimiliki user sesuai `FundRequestPolicy`).
  - Memastikan terdapat `RkaHeader` dengan `schedule_id = $activeSchedule->id`, `department_id = $department->id`, dan `status = APPROVED`.
- **Item RKA yang Dapat Dipilih (`getSelectableRkaItems()`):**
  - `rka_items.status IN ('APPROVED', 'ACCEPTED', 'REDUCED', 'TRIMMED')`, `approved_total > 0`, `approved_volume > 0`, `account_id IS NOT NULL`, dan memiliki `remaining_amount > 0`.

---

## 2. Struktur Database Setelah Perubahan

Migration: `database/migrations/2026_11_16_214000_add_rka_links_to_fund_requests_tables.php` (non-destruktif, nullable untuk kompatibilitas histori lama):

- **`fund_requests`:**
  - `rka_header_id` (`bigint unsigned`, nullable, FK → `rka_headers.id` ON DELETE SET NULL)
- **`fund_request_items`:**
  - `rka_item_id` (`bigint unsigned`, nullable, FK → `rka_items.id` ON DELETE SET NULL)
  - `rka_account_code_snapshot` (`varchar(50)`, nullable)
  - `rka_account_name_snapshot` (`varchar(255)`, nullable)
  - `rka_approved_volume_snapshot` (`decimal(12,2)`, nullable)
  - `rka_approved_total_snapshot` (`decimal(15,2)`, nullable)

---

## 3. Kontrol Saldo Dua Tingkat & Status Workflow

Dikelola terpusat oleh `App\Services\FundRequest\FundRequestBudgetService`:

1. **Status yang Memengaruhi Saldo (`ACTIVE_BALANCE_STATUSES`):**
   - `draft`, `submitted`, `verified_kabakku`, `verified_wadir_bakku` → **menahan saldo sementara** (`total_price`, atau `approved_price` bila sudah diverifikasi parsial/approved).
   - `approved` → **mengurangi saldo permanen** (`approved_price` untuk item `approved`/`partial`, `0` untuk item `rejected`).
   - `rejected` → **melepaskan saldo** (tidak diperhitungkan).
2. **Kontrol Pertama (Per Item RKA):**
   - `Sisa Anggaran Item = RkaItem.approved_total - SUM(penggunaan aktif rka_item_id)`.
3. **Kontrol Kedua (Per Kode Rekening / `DepartmentBudget`):**
   - `Sisa Pagu Rekening = DepartmentBudget.allocated_amount - SUM(penggunaan aktif department_id + account_id + fiscal_year)`.
4. **Pencegahan *Double Spending* & Manipulasi Browser:**
   - Validasi berjalan di dalam `DB::transaction()` dengan `lockForUpdate()` pada `RkaItem` dan `DepartmentBudget` (diurutkan berdasarkan ID).
   - `unit_price`, `unit`, `description`, `account_id`, dan `fiscal_year` selalu diturunkan ulang di server dari `RkaItem` dan `RkaSchedule` aktif.
   - Saat Edit (`updateFundRequest`), transaksi yang sedang diedit dikecualikan (`$excludeFundRequestId`) dari perhitungan penggunaan saldo.

---

## 4. Menjalankan Pengujian

```bash
docker compose exec -e DB_CONNECTION=mysql -e DB_HOST=db -e DB_PORT=3306 -e DB_DATABASE=erka -e DB_USERNAME=erka -e DB_PASSWORD=erka app php artisan test tests/Feature/FundRequest/FundRequestApprovedRkaTest.php --compact
```
