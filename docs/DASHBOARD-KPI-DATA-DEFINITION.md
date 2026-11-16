# Definisi Data & Formula KPI Dashboard SIMRKA

Seluruh perhitungan KPI terpusat di `App\Services\Dashboard\DashboardMetricsService`.

## 1. Total Pagu Anggaran (`pagu`)
- **Sumber Tabel:** `department_budgets` (`allocated_amount`).
- **Filter:** `fiscal_year` = tahun anggaran dari `RkaSchedule` aktif; `department_id` jika user unit-scoped.
- **Formula:** `SUM(allocated_amount)`.
- **Catatan:** Tidak mencampur pagu dengan total usulan RKA atau realisasi.

## 2. Total Disetujui Fund Request (`approved_amount`)
- **Sumber Tabel:** `fund_requests` (`total_approved`).
- **Filter:** `fiscal_year` aktif, `status = 'approved'`, serta `department_id` jika unit-scoped.
- **Formula:** `SUM(total_approved)`.

## 3. Dana Dalam Proses (`pending_amount`)
- **Sumber Tabel:** `fund_requests` (`total_requested`).
- **Filter:** `fiscal_year` aktif, `status IN ('submitted', 'verified_kabakku', 'verified_wadir_bakku')`.
- **Formula:** `SUM(total_requested)`.

## 4. Sisa Pagu Tersedia (`remaining_budget`)
- **Formula:** `pagu - (approved_amount + pending_amount)`.
- **Catatan:** Menghindari double counting antara `approved_amount` dan `pending_amount` karena himpunan status saling lepas.

## 5. Persentase Penyerapan (`absorption_rate`)
- **Formula:** `pagu > 0 ? round((approved_amount / pagu) * 100, 1) : 0.0`.
- **Keamanan:** Terlindung dari pembagian dengan nol.

## 6. Ringkasan RKA (`getRkaSummary`)
- **Sumber Tabel:** `rka_headers` (`status`, `schedule_id`, `department_id`) dan `rka_items` (`proposed_total`, `approved_total`).
- **Total Usulan RKA:** `SUM(rka_items.proposed_total)` pada `rka_headers` periode aktif.
- **Total Disetujui RKA:** `SUM(rka_items.approved_total)` **hanya** pada `rka_headers` berstatus `APPROVED` (RKA berstatus `DRAFT`, `SUBMITTED`, `IN_REVIEW`, atau `REVISION_REQUIRED` tidak dihitung sebagai anggaran disetujui).

## 7. Aktivitas Sistem Terbaru (`getRecentSystemActivities`)
- **Sumber Tabel:** `rka_logs` dan `fund_request_approvals`.
- **Deduplikasi:** Mengambil masing-masing sumber log secara terpisah lalu menggabungkan dan mengurutkan berdasarkan `created_at DESC`, sehingga satu transaksi tidak dihitung ganda.
