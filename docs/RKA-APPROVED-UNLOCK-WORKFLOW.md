# Alur Controlled Unlock & Reimport RKA Approved

## 1. Prinsip Utama
1. **RKA Approved hasil import langsung terkunci (`is_locked = true`)** dan tidak masuk alur pengajuan/review reguler.
2. **Status `APPROVED` tidak pernah turun menjadi `DRAFT`** selama proses permintaan buka kunci maupun persetujuan keuangan.
3. **Hak Import Ulang bersifat satu kali (`one-time`) dan berbatas waktu (`reimport_expires_at`)**.

## 2. Siklus Status `RkaUnlockRequest`

```
[RKA APPROVED & Locked]
         │
         ▼  Pemohon: submitRequest()
    [PENDING] ──────────────────────────┐
         │                              │
         │ Finance: approveRequest()    │ Finance: rejectRequest()
         ▼                              ▼
    [APPROVED] (Hak Reimport Aktif)  [REJECTED] (RKA tetap terkunci)
         │
         ├───► Pemohon: executeReimport() berhasil ──► [USED] + RKA re-locked
         │
         └───► Melewati reimport_expires_at ─────────► [EXPIRED]
```

## 3. Keamanan Transaksi
- Apabila `executeReimport()` gagal di tengah proses, seluruh perubahan database di-rollback oleh `DB::transaction()` sehingga hak import ulang pada `RkaUnlockRequest` tidak hangus (`status` tetap `APPROVED`, `reimport_used_at` tetap `null`).
- Seluruh transisi dicatat ke tabel `rka_logs` melalui `RkaActivityLogService` menggunakan konstanta `ACTION_UNLOCK_REQUESTED`, `ACTION_UNLOCK_APPROVED`, `ACTION_UNLOCK_REJECTED`, `ACTION_APPROVED_REIMPORT_EXECUTED`, dan `ACTION_RELOCKED`.
