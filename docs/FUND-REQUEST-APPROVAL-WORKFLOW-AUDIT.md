# Audit Workflow Persetujuan & Verifikasi Fund Request

**Tanggal Audit:** 2026-11-16  
**Branch:** `feature/rka-approved-excel-import`  
**Halaman Target:** `/admin/fund-requests/{id}/verify` (`VerifyFundRequest`)

---

## 1. Workflow Fund Request Saat Ini

1. **Tahap 1 — Pembuatan (`CreateFundRequestCustom` / `EditFundRequestCustom`):**
   - Pemohon memilih item dari `RkaItem` pada `RkaHeader` berstatus `APPROVED` di periode aktif (`RkaSchedule`), mengisi volume dan keterangan, lalu menyimpan (`status = 'submitted'`).
   - `FundRequestBudgetService` memvalidasi saldo dua tingkat (per item RKA dan per rekening `DepartmentBudget`) dengan `lockForUpdate()`.
2. **Tahap 2 — Verifikasi Bagian Keuangan (`VerifyFundRequest`, role `keuangan`):**
   - Keuangan membuka `/admin/fund-requests/{id}/verify`.
   - Memverifikasi tiap `FundRequestItem` via `openApprovalModal()` → `saveItemApproval()` (mengisi `approved_volume`, `approved_price`, `verification_status` (`approved`|`partial`|`rejected`), dan `finance_note`).
   - Merekap per `account_id` pada Bagian F (`fund_request_verifications`) lalu menekan `saveVerification()` (`stage = 'kabakku'`, `status` menjadi `'verified_kabakku'` atau `'rejected'`).
3. **Tahap 3 — Persetujuan Wadir Keuangan (`wadir_keuangan`):**
   - Membuka `/admin/fund-requests/{id}/verify` (mode read-only pada item & Bagian F), mencatat keputusan pada `fund_request_approvals` (`stage = 'wadir_bakku'`), `status` menjadi `'verified_wadir_bakku'` atau `'rejected'`.
4. **Tahap 4 — Persetujuan Direktur (`direktur`):**
   - Membuka `/admin/fund-requests/{id}/verify` (mode read-only), mencatat keputusan pada `fund_request_approvals` (`stage = 'director'`), `status` menjadi `'approved'` atau `'rejected'`.

---

## 2. Daftar Aktor & Kewenangan

| Aktor / Role | Kewenangan |
|---|---|
| `pemohon` | Membuat & mengubah (`draft`/`submitted`) pengajuan unitnya berdasarkan RKA `APPROVED` aktif; melihat daftar pengajuan unitnya. |
| `keuangan` | Memverifikasi item (`saveItemApproval`), mengisi bukti register Bagian F, meneruskan ke Wadir (`verified_kabakku`) atau menolak (`rejected`). |
| `wadir_keuangan` | Meninjau hasil verifikasi Keuangan (read-only item), menyetujui (`verified_wadir_bakku`) atau menolak (`rejected`). |
| `direktur` | Meninjau hasil verifikasi, menyetujui final (`approved`) atau menolak (`rejected`). |

---

## 3. Daftar Status Transaksi

- **`fund_requests.status` (enum):** `draft`, `submitted`, `verified_kabakku`, `verified_wadir_bakku`, `approved`, `rejected`.
- **`fund_request_items.verification_status` (enum nullable):** `null` (belum diverifikasi / pending), `approved`, `partial`, `rejected`.

---

## 4. Relasi Data dan Tabel

- `fund_requests` (`rka_header_id` → `rka_headers.id`, `department_id` → `departments.id`)
- `fund_request_items` (`fund_request_id` → `fund_requests.id`, `rka_item_id` → `rka_items.id`, `account_id` → `accounts.id`)
- `fund_request_verifications` (`fund_request_id` → `fund_requests.id`, `account_id`, `final_account_id`, `remaining_budget`, `amount_approved`, `status`, `register_proof_number`)
- `fund_request_approvals` (`fund_request_id` → `fund_requests.id`, `stage`, `action`, `approved_amount`, `note`, `user_id`)

---

## 5. Temuan Ketidaksesuaian & Risiko

1. **Modal Verifikasi Item Sempit & Minim Konteks Anggaran:**
   - Modal tengah `max-w-lg` belum berupa right-side slide-over, belum menampilkan sumber RKA (`rka_item_id`, program/kegiatan, unit pemohon) maupun ringkasan anggaran RKA (pagu item, penggunaan sebelumnya, sisa sebelum & proyeksi setelah verifikasi).
2. **Validasi Backend Verifikasi Item Belum Lengkap:**
   - `saveItemApproval()` belum memvalidasi bahwa `modal_approved_volume <= selectedItemRequestedVolume`, `modal_approved_price <= selectedItemRequestedPrice`, serta tidak melebihi sisa anggaran item RKA (`FundRequestBudgetService`).
   - `saveItemApproval()` juga belum memblokir perubahan jika `fund_requests.status` sudah final (`approved` / `rejected`).
3. **Bagian F (`recalculateGroupedVerifications()`) Bug `remaining_budget` & Kurang Detail:**
   - `Account::remaining_budget` tidak ada di tabel `accounts` sehingga `remaining_budget` selalu bernilai `0`. Seharusnya diambil dari sisa pagu `DepartmentBudget` unit pada tahun anggaran pengajuan.
   - Tabel Bagian F belum menampilkan `Nama Rekening`, `Jumlah Item`, `Total Dimohonkan`, `Selisih`, **Status Agregat** yang dihitung dari detail item (`Disetujui Penuh`, `Disetujui Sebagian`, `Ditolak`, `Belum Selesai Diverifikasi`), serta tombol **Lihat Rincian** item per rekening.
4. **Penolakan Tanpa Alasan & Tahap Persetujuan:**
   - `saveVerification()` dengan `action_type = 'reject'` belum mewajibkan `verification_note`.

---

## 6. Rekomendasi Perbaikan (Tanpa Migration Baru)

1. Ubah modal verifikasi item pada `verify-fund-request.blade.php` menjadi **right-side slide-over** (`w-full sm:max-w-[680px]`) dengan header sticky, ringkasan item & ringkasan anggaran RKA dari `FundRequestBudgetService`, form verifikasi dengan live calculation & dirty-check konfirmasi saat tutup, dan footer sticky anti-double-submit.
2. Perketat validasi backend di `VerifyFundRequest::saveItemApproval()` (`approved_volume <= requested_volume`, `approved_price <= requested_price`, cek sisa anggaran RKA, dan status `fund_request` masih dapat diverifikasi).
3. Perbaiki `VerifyFundRequest::recalculateGroupedVerifications()` agar menghitung `remaining_budget` dari `DepartmentBudget` via `FundRequestBudgetService`, `total_requested`, `amount_approved`, `selisih`, `item_count`, dan `status` agregat dari detail item, serta tambahkan slide-over/expandable **Lihat Rincian** pada Bagian F.
4. Wajibkan alasan (`verification_note`) saat `action_type === 'reject'` pada `saveVerification()`.
