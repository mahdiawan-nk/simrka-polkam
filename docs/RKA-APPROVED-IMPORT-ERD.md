# ERD & Desain Schema Database — Import Approved RKA & Controlled Unlock

## 1. Mermaid ERD

```mermaid
erDiagram
    departments ||--o{ rka_headers : "owns"
    rka_schedules ||--o{ rka_headers : "schedules"
    rka_settings ||--o{ rka_headers : "configures"
    users ||--o{ rka_headers : "unit_head / reviewer"

    rka_headers ||--o{ rka_indicators : "has many"
    rka_headers ||--o{ rka_items : "has many"
    rka_headers ||--o{ rka_adjustments : "has many"
    rka_headers ||--o{ rka_logs : "audit logs"
    rka_headers ||--o{ rka_approved_import_batches : "import history"
    rka_headers ||--o{ rka_unlock_requests : "unlock requests"

    rka_indicators ||--o{ rka_items : "groups"
    accounts ||--o{ rka_items : "classified by"
    accounts ||--o{ rka_adjustments : "classified by"

    rka_unlock_requests ||--o| rka_approved_import_batches : "authorizes reimport"
    users ||--o{ rka_approved_import_batches : "imported_by"
    users ||--o{ rka_unlock_requests : "requested_by / reviewed_by"

    rka_approved_import_batches {
        bigint id PK
        bigint rka_header_id FK "nullable, nullOnDelete"
        bigint department_id FK
        bigint schedule_id FK
        bigint imported_by FK
        bigint unlock_request_id FK "nullable, nullOnDelete"
        varchar import_type "INITIAL | REIMPORT"
        varchar original_filename "255"
        varchar file_checksum "64"
        int belanja_count
        int adjustment_count
        decimal total_proposed "15,2"
        decimal total_approved "15,2"
        varchar status "SUCCESS | FAILED"
        json summary "nullable"
        timestamp created_at
        timestamp updated_at
    }

    rka_unlock_requests {
        bigint id PK
        bigint rka_header_id FK "nullable, nullOnDelete"
        bigint department_id FK
        bigint requested_by FK
        text reason
        text error_description "nullable"
        varchar status "PENDING | APPROVED | REJECTED | USED | EXPIRED | CANCELLED"
        bigint reviewed_by FK "nullable"
        text decision_notes "nullable"
        timestamp reviewed_at "nullable"
        timestamp reimport_granted_at "nullable"
        timestamp reimport_expires_at "nullable"
        timestamp reimport_used_at "nullable"
        bigint reimport_batch_id "nullable"
        timestamp created_at
        timestamp updated_at
    }
```

---

## 2. Detail Tabel & Kolom Baru

### 2.1. `rka_approved_import_batches`
Menyimpan riwayat setiap eksekusi Import Approved RKA (baik `INITIAL` maupun `REIMPORT`).
- Foreign key `rka_header_id` menggunakan `nullOnDelete()` agar riwayat import **tidak ikut terhapus** jika `rka_headers` dihapus.
- Foreign key `unlock_request_id` menggunakan `nullOnDelete()`.
- Index: `(rka_header_id, created_at)`, `(department_id, schedule_id)`.

### 2.2. `rka_unlock_requests`
Menyimpan pengajuan pembukaan kunci oleh `pemohon` serta keputusan persetujuan/penolakan oleh tim keuangan (`keuangan` / `wadir_keuangan`).
- Foreign key `rka_header_id` menggunakan `nullOnDelete()` untuk menjaga audit history.
- Kolom `status`: `PENDING`, `APPROVED`, `REJECTED`, `USED`, `EXPIRED`, `CANCELLED`.
- Kolom one-time reimport authorization: `reimport_granted_at`, `reimport_expires_at`, `reimport_used_at`, `reimport_batch_id`.
- Index: `(rka_header_id, status)`, `(department_id, status)`, `(requested_by, status)`.

### 2.3. Penambahan Kolom Additive pada `rka_items` & `rka_adjustments`
- `source_ref_id` (`varchar(64)`, nullable, indexed) pada `rka_items` dan `rka_adjustments` untuk menyimpan `ITEM_REF_ID` / `ADJ_REF_ID` asal dari Excel template agar proses **import ulang (reimport)** dapat melakukan rekonsiliasi secara deterministik tanpa duplikasi.
