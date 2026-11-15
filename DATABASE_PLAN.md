**Skema Database Lengkap** untuk Sistem Informasi **E-RKA (Rencana Kerja dan Anggaran)**. Skema ini dirancang menggunakan standar *Relational Database Management System* (PostgreSQL / MySQL) dengan fitur pendukung audit, kontrol akses (RBAC), siklus portal *open/closed*, penyesuaian belanja antar-unit, serta versi histori revisi.

---

## 1. Diagram Hubungan Antar Tabel (Entity Relationship Overview)

```text
[users] ───< [user_roles] >─── [roles]
   │
   ├───< [rka_schedules] (Created by Admin)
   │
   └───< [rka_headers] ───┬───< [rka_indicators]
           │              ├───< [rka_items]
           │              ├───< [rka_adjustments]
           │              ├───< [rka_logs]
           │              └───< [rka_approvals]
           │
[units] ───┴─── (unit_id / origin_unit_id)
   │
[accounts] (Master Kode Rekening / COA)

```

---

## 2. Definisi Struktur Tabel Database (SQL / DDL Schema)

### A. Master Data & Autentikasi

#### 1. `units` (Master Unit Kerja & Program Studi)

Menyimpan daftar seluruh unit kerja, prodi, bagian, dan lembaga di lingkungan kampus.

```sql
CREATE TABLE units (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,           -- Misal: 'UPT_ICT', 'PRODI_TIF', 'BAA', 'KEUANGAN'
    name VARCHAR(150) NOT NULL,                  -- Misal: 'UPT ICT', 'Prodi D3 Teknik Informatika'
    unit_type VARCHAR(50) NOT NULL,             -- 'UPT', 'PRODI', 'BAGIAN', 'PUSAT', 'PIMPINAN'
    parent_id BIGINT NULL REFERENCES units(id), -- Hierarki jika ada sub-unit
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

```

#### 2. `accounts` (Master Kode Rekening / Chart of Accounts)

Menyimpan struktur kode rekening standar anggaran (Kelompok Belanja 5.1 s.d. 2.1).

```sql
CREATE TABLE accounts (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(30) UNIQUE NOT NULL,               -- Kode rekening (misal: '5.1', '5.2.1', '5.2.1.01')
    code_clean VARCHAR(30) NULL,                    -- Kode tanpa titik untuk kemudahan parser/search (misal: '51', '521', '52101')
    name VARCHAR(255) NOT NULL,                     -- Uraian (misal: 'Belanja Bahan Habis Dipakai')
    
    -- Relasi Hirarki (Self-Referencing Parent)
    parent_id BIGINT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
    parent_code VARCHAR(30) NULL,                   -- Menyimpan parent_code sebagai fallback/referensi cepat
    
    -- Tingkat Hirarki Sesuai Format Excel
    level INT NOT NULL,                             -- 1: Kelompok, 2: Jenis, 3: Rincian
    level_label VARCHAR(30) NOT NULL,               -- 'Kelompok', 'Jenis', 'Rincian' (Sesuai kolom Tingkat di Excel)
    
    -- Keterangan / Contoh Isi (Petunjuk Pengisian)
    description TEXT NULL,                          -- Berisi penjelasan/contoh dari kolom 'Keterangan / contoh isi'
    
    -- Metadata & Status
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexing untuk mempercepat query hirarki dan pencarian kode
CREATE INDEX idx_accounts_parent_id ON accounts(parent_id);
CREATE INDEX idx_accounts_code_clean ON accounts(code_clean);
CREATE INDEX idx_accounts_level ON accounts(level);

```

#### 3. `users` & `roles` (Manajemen Pengguna & Akses)

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    nip_nrp VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    unit_id BIGINT REFERENCES units(id) ON DELETE SET NULL,
    position VARCHAR(100) NULL,                 -- Misal: 'Kepala UPT ICT', 'Wakil Direktur II'
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,           -- 'admin_keuangan', 'ka_unit', 'reviewer', 'direktur'
    label VARCHAR(100) NOT NULL
);

CREATE TABLE user_roles (
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    role_id BIGINT REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

```

---

### B. Transaksi Utama RKA & Portal Kontrol

#### 4. `rka_schedules` (Jadwal Portal Open / Closed)

Mengatur gelombang pengisian RKA per tahun anggaran dan penguncian sistem.

```sql
CREATE TABLE rka_schedules (
    id BIGSERIAL PRIMARY KEY,
    fiscal_year VARCHAR(10) NOT NULL,           -- Misal: '2026/2027'
    period_range VARCHAR(100) NOT NULL,          -- Misal: 'September 2026 – Agustus 2027'
    phase_name VARCHAR(100) NOT NULL,            -- Misal: 'Pengusulan Awal', 'Revisi Pasca Review'
    start_at TIMESTAMP NOT NULL,
    end_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    allow_late_submission BOOLEAN DEFAULT FALSE,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

```

#### 5. Tabel Konfigurasi Institusi & Penandatangan (`institutional_settings`)

Memisahkan data Direktur, Yayasan, dan tempat TTE agar tidak berulang di setiap RKA unit.

```sql
CREATE TABLE institutional_settings (
    id BIGSERIAL PRIMARY KEY,
    fiscal_year VARCHAR(10) UNIQUE NOT NULL,      -- Misal: '2026/2027'
    director_user_id BIGINT REFERENCES users(id),  -- Relasi ke user Direktur
    director_name VARCHAR(150) NOT NULL,
    director_nrp VARCHAR(50) NOT NULL,
    foundation_head_name VARCHAR(150) NOT NULL,
    signing_place VARCHAR(100) DEFAULT 'Bangkinang',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 6. `rka_headers` (Header Dokumen RKA Unit)

Menyimpan identitas, pagu anggaran, status persetujuan, serta penandatangan.

```sql
CREATE TABLE rka_headers (
    id BIGSERIAL PRIMARY KEY,
    schedule_id BIGINT NOT NULL REFERENCES rka_schedules(id) ON DELETE CASCADE,
    unit_id BIGINT NOT NULL REFERENCES units(id) ON DELETE CASCADE,
    setting_id BIGINT NOT NULL REFERENCES institutional_settings(id), -- Mengambil setting direktur/yayasan
    
    -- Bagian A: Pengusul (Cukup simpan FK ke User)
    unit_head_user_id BIGINT REFERENCES users(id),
    submitted_at TIMESTAMP NULL,
    
    -- Bagian B: Pengaturan Keuangan & Pagu Unit
    pagu_budget DECIMAL(15,2) DEFAULT 0.00,
    
    -- Bagian C: Reviewer (Cukup simpan FK ke User)
    reviewer_user_id BIGINT REFERENCES users(id),
    review_date DATE NULL,
    general_notes TEXT NULL,
    
    -- Status & Kontrol Portal Per Unit
    status VARCHAR(30) DEFAULT 'DRAFT',            -- 'DRAFT', 'SUBMITTED', 'IN_REVIEW', 'REVISION_REQUIRED', 'APPROVED'
    is_locked BOOLEAN DEFAULT FALSE,
    unlocked_by_admin BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(schedule_id, unit_id)
);
```

#### 7. `rka_indicators` (Indikator Kinerja Belanja per Formulir)

Menyimpan data indikator (Formulir RKA-5.1, 5.2, 5.3, dst.).

```sql
CREATE TABLE rka_indicators (
    id BIGSERIAL PRIMARY KEY,
    rka_header_id BIGINT NOT NULL REFERENCES rka_headers(id) ON DELETE CASCADE,
    form_type VARCHAR(10) NOT NULL,              -- '5.1', '5.2', '5.3', '5.4', '5.5', '2.1'
    program_name TEXT NOT NULL,                  -- Misal: 'Program Pelayanan Administrasi Perkantoran'
    activity_name TEXT NOT NULL,                 -- Misal: 'Belanja Barang dan Jasa'
    location VARCHAR(150) DEFAULT 'Politeknik Kampar',
    
    -- Matriks Indikator
    indicator_category VARCHAR(50) NOT NULL,    -- 'Capaian Program', 'Masukan', 'Keluaran', 'Hasil', 'Kelompok Sasaran'
    performance_benchmark TEXT NOT NULL,        -- Tolak Ukur Kinerja
    target_value VARCHAR(50) NOT NULL,          -- Misal: '1', '60,019,000', '87'
    unit_target VARCHAR(50) NOT NULL,           -- Misal: 'Tahun', 'Rupiah', 'Pegawai', 'Bulan'
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

```

#### 8. `rka_items` (Rincian Anggaran Belanja Item-by-Item)

Tabel utama tempat perbandingan **Usulan Unit** dan **Hasil Review**.

```sql
CREATE TABLE rka_items (
    id BIGSERIAL PRIMARY KEY,
    rka_header_id BIGINT NOT NULL REFERENCES rka_headers(id) ON DELETE CASCADE,
    account_id BIGINT NOT NULL REFERENCES accounts(id),
    form_type VARCHAR(10) NOT NULL,              -- '5.1', '5.2', '5.3', dll.
    description TEXT NOT NULL,                  -- Uraian rincian item belanja
    
    -- Usulan Unit
    proposed_volume DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_symbol VARCHAR(50) NOT NULL,           -- 'Box', 'Pcs', 'Bulan', 'Unit', 'Paket', 'Orang'
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    proposed_total DECIMAL(15,2) GENERATED ALWAYS AS (proposed_volume * unit_price) STORED,
    
    -- Hasil Review (Keuangan / Wadir II)
    approved_volume DECIMAL(12,2) NULL,          -- Jika dipangkas, nilai volume diubah di sini
    approved_total DECIMAL(15,2) NULL,           -- Volume disetujui * unit_price
    
    -- Status & Penandaan Visual
    status VARCHAR(30) DEFAULT 'PENDING',        -- 'ACCEPTED', 'REDUCED', 'REJECTED'
    is_trimmed BOOLEAN DEFAULT FALSE,           -- True jika dipangkas (untuk highlight warna oranye di UI/Excel)
    review_notes TEXT NULL,                     -- Catatan khusus per item dari reviewer
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

```

#### 9. `rka_adjustments` (Daftar Penyesuaian Usulan & Shared Cost)

Digunakan untuk menampung data dari **Daftar Penyesuaian Usulan** (Sheet Penyesuaian Bagian A & B).

```sql
CREATE TABLE rka_adjustments (
    id BIGSERIAL PRIMARY KEY,
    rka_header_id BIGINT NOT NULL REFERENCES rka_headers(id) ON DELETE CASCADE,
    section_type VARCHAR(1) NOT NULL,           -- 'A': Usulan Dipindah/Dihapus, 'B': Pos Dikelola untuk Unit Lain
    
    account_id BIGINT REFERENCES accounts(id),
    description TEXT NOT NULL,                  -- Uraian (misal: 'Aplikasi premium/lisensi – Pembelian Akun Turnitin')
    origin_unit_id BIGINT REFERENCES units(id),  -- Unit Asal (misal: 'P3M', 'Prodi D3 TIF', 'UPT Media Center')
    
    proposed_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    approved_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    
    status_label VARCHAR(100) NULL,             -- Misal: 'Dipindahkan', 'Disetujui'
    notes TEXT NULL,                            -- Keterangan / Dianggarkan di
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

```

---

### C. Persetujuan, Log Audit & Riwayat

#### 10. `rka_approvals` (Siklus Persetujuan & Digital Sign-off)

Mencatat jejak persetujuan berjenjang.

```sql
CREATE TABLE rka_approvals (
    id BIGSERIAL PRIMARY KEY,
    rka_header_id BIGINT NOT NULL REFERENCES rka_headers(id) ON DELETE CASCADE,
    approver_user_id BIGINT NOT NULL REFERENCES users(id),
    stage VARCHAR(50) NOT NULL,                 -- 'KA_UNIT_SUBMIT', 'WADIR2_REVIEW', 'DIREKTUR_APPROVAL'
    action VARCHAR(30) NOT NULL,                -- 'SUBMITTED', 'REVISED', 'APPROVED', 'REJECTED'
    comments TEXT NULL,
    digital_signature_hash VARCHAR(255) NULL,   -- Untuk verifikasi dokumen TTE / QR Code
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

```

#### 11. `rka_logs` (Audit Trail & Riwayat Perubahan)

Mencatat perubahan item, pemotongan anggaran, dan aksi buka/tutup portal.

```sql
CREATE TABLE rka_logs (
    id BIGSERIAL PRIMARY KEY,
    rka_header_id BIGINT REFERENCES rka_headers(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id),
    action VARCHAR(100) NOT NULL,               -- Misal: 'OPEN_LOCK_UNIT', 'TRIM_ITEM_VOLUME', 'SUBMIT_RKA'
    old_values JSONB NULL,                      -- Snapshot data sebelum diubah
    new_values JSONB NULL,                      -- Snapshot data setelah diubah
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

```

---

## 3. Ringkasan Fitur Unggulan pada Skema Database

1. **Virtual Generated Column (`proposed_total`)**: Menghitung otomatis total usulan `(Volume × Harga Satuan)` secara konsisten di level database.
2. **Multi-Unit Budget Allocation (`rka_adjustments`)**: Mendukung penganggaran terpusat di mana 1 unit (seperti UPT ICT) mengelola lisensi/aplikasi untuk unit lain (P3M, Media Center, Prodi).
3. **Auditability (`rka_logs` & `rka_approvals`)**: Setiap pemangkasan anggaran oleh Reviewer tersimpan riwayatnya (`old_values` & `new_values`) sehingga proses review akuntabel.
4. **Flexible Portal Override (`rka_headers.unlocked_by_admin`)**: Memungkinkan admin memberikan izin pengeditan khusus kepada unit tertentu meskipun jadwal portal utama sudah tertutup.

Berikut adalah analisis dan hasil normalisasi untuk skema tabel `rka_headers`.

