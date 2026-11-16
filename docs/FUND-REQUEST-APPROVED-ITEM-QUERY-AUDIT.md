# Audit Query Item RKA Approved pada Fund Request

**Tanggal Audit:** 2026-11-16  
**Database Development:** MySQL `erka` (read-only audit pada `RKA #1979` P3M, `#1996` Prodi TIF, `#1998` UPT ICT)

---

## 1. File dan Method yang Bertanggung Jawab atas Query

- `app/Services/FundRequest/FundRequestBudgetService.php`:
  - `resolveApprovedRkaContext()` — menentukan `RkaSchedule` aktif tunggal (`is_active = true`), departemen aktif, dan `RkaHeader` berstatus `APPROVED`.
  - `getSelectableRkaItems()` — query `RkaItem` yang memenuhi syarat untuk ditampilkan pada modal slide-over `+ Pilih Item RKA`.
  - `validateAndLockBudgetLines()` — validasi ulang di server saat `createFundRequest()` / `updateFundRequest()`.
- `app/Filament/Resources/FundRequests/Concerns/InteractsWithApprovedRkaFundRequest.php`:
  - `getSelectableRkaItemsProperty()`, `getTotalAvailableRkaItemsCountProperty()`, `selectRkaItem()`.

---

## 2. Alur Pengambilan Data dari RKA Header sampai ke Modal

1. `CreateFundRequestCustom::mount()` / `EditFundRequestCustom::mount()` memanggil `resolveRkaSourceContext()` → `FundRequestBudgetService::resolveApprovedRkaContext()`.
2. `rka-items-and-summary.blade.php` membaca `$this->selectableRkaItems` dan `$this->totalAvailableRkaItemsCount`.
3. `getSelectableRkaItems()` melakukan query `RkaItem` milik `$rkaHeader->id` dengan filter status, nilai disetujui, volume disetujui, `account_id`, filter pencarian/kelompok/rekening, serta sisa anggaran (`approved_total - used_amount > 0`).

---

## 3. Kondisi Filter yang Diterapkan & Perhitungan Sisa Anggaran

Query sebelumnya pada `FundRequestBudgetService::getSelectableRkaItems()`:
```php
->where('rka_header_id', $rkaHeader->id)
->whereIn('status', ['APPROVED', 'ACCEPTED', 'REDUCED', 'TRIMMED'])
->where('approved_total', '>', 0)
->where('approved_volume', '>', 0)
->whereNotNull('account_id')
```
Sisa anggaran dihitung: `approved_total - SUM(penggunaan aktif pada fund_request_items dengan status fund_requests IN ('draft','submitted','verified_kabakku','verified_wadir_bakku','approved'))`.

---

## 4. Penyebab Item Tidak Tampil (Root Cause Utama)

Pada dokumen Excel Approved RKA asli (`10_RKA_2026-2027_P3M.xlsx`, `19_RKA_2026-2027_Prodi_TIF.xlsx`, `05_RKA_2026-2027_UPT_ICT.xlsx`), kolom **Vol Disetujui** (`approved_volume`) **bernilai `NULL`** untuk seluruh item yang disetujui penuh (`status = APPROVED`), dan hanya diisi angka ketika volume dipangkas (`TRIMMED`) atau ditolak (`0` / `REJECTED`).

Karena query `getSelectableRkaItems()` dan `validateAndLockBudgetLines()` mensyaratkan `->where('approved_volume', '>', 0)` tanpa memperhitungkan `approved_volume IS NULL` (dengan `proposed_volume > 0`), **seluruh item `APPROVED` terbuang dari hasil query**, sehingga yang tampil hanya item `TRIMMED`.

### Data Aktual `RKA #1979` (P3M — Jadwal Aktif #1 `2026/2027`)
| Keterangan | Jumlah | Detail |
|---|---:|---|
| Total seluruh item RKA | 53 | Semua `RkaItem` pada `rka_header_id = 1979` |
| Item `APPROVED` | 21 | `approved_total > 0`, `proposed_volume > 0`, **`approved_volume = NULL`** |
| Item `TRIMMED` | 4 | `approved_total > 0`, `approved_volume > 0` |
| Item `REJECTED` | 5 | `approved_total = 0`, `approved_volume = 0` |
| Item `MOVED` (Section A) | 23 | `approved_total = 0`, `approved_volume = 0` |
| Item dengan `FundRequest` aktif | 0 | Belum ada pengajuan pada `RKA #1979` |
| Item lolos query **sebelum** perbaikan | **4** | Hanya 4 item `TRIMMED` (21 `APPROVED` hilang karena `approved_volume IS NULL`) |
| Item yang **seharusnya tersedia** | **25** | 21 `APPROVED` + 4 `TRIMMED` |

Contoh item `APPROVED` yang hilang pada `RKA #1979`:
- `#15622`: `Pembayaran DOI Jurnal` — `proposed_volume = 300.00`, `unit_price = 20000.00`, `approved_volume = NULL`, `approved_total = 6000000.00` (tidak lolos `approved_volume > 0`).
- `#15623`: `Honor Pemateri Workshop Penulisan Proposal Hibah` — `proposed_volume = 1.00`, `approved_volume = NULL`, `approved_total = 5000000.00` (tidak lolos `approved_volume > 0`).

Kondisi serupa terjadi pada `RKA #1998` (UPT ICT: 32 `APPROVED` hilang, hanya 1 `TRIMMED` tampil) dan `RKA #1996` (Prodi TIF: 62 `APPROVED` hilang, hanya 5 `TRIMMED` tampil).

---

## 5. Rencana Perbaikan

1. Tambahkan helper `resolveEffectiveApprovedVolume(RkaItem $item): float` pada `FundRequestBudgetService`:
   - Jika `approved_volume !== null` → gunakan `(float) $item->approved_volume` (sehingga `approved_volume = 0` tetap ditolak).
   - Jika `approved_volume === null` → gunakan `(float) $item->proposed_volume`.
2. Perbarui `getSelectableRkaItems()` agar menerima:
   `(approved_volume > 0) OR (approved_volume IS NULL AND proposed_volume > 0)`.
3. Perbarui `validateAndLockBudgetLines()` untuk menggunakan `resolveEffectiveApprovedVolume($rkaItem)` agar validasi server konsisten dengan query modal.
4. Tambahkan pengujian di `FundRequestApprovedRkaTest` untuk item `APPROVED` dengan `approved_volume = NULL` serta item dengan `approved_volume = 0` (tetap diblokir).
