# Panduan Monitoring & Koreksi Rekonsiliasi Antarunit (Finance)

Dokumen ini menjadi panduan operasional bagi tim **Keuangan (`keuangan` / `finance` / `wadir_keuangan`)** dalam mengawasi hasil rekonsiliasi otomatis lintas unit pada SIMRKA Politeknik Kampar.

---

## 1. Akses Menu & Hak Akses

- **Menu:** `Manajemen RKA → Rekonsiliasi Antarunit` (`ManageReconciliationsPage`)
- **Otorisasi (`RkaAdjustmentMatchPolicy`):**
  - Role `keuangan`, `finance`, `wadir_keuangan`, dan `super_admin` dapat memantau seluruh rekonsiliasi lintas unit, menjalankan koreksi, membatalkan relasi, dan memicu rekonsiliasi ulang.
  - Role `pemohon` **tidak** dapat mengakses halaman monitoring Finance, namun tetap dapat melihat penyesuaian dan hasil rekonsiliasi milik unitnya (serta alokasi lintas unit yang ditujukan ke unitnya setelah `rka_headers.status = 'APPROVED'`).

---

## 2. Prinsip Pengawasan

1. **Otomatis Sah (`MATCHED`)**: Pasangan unik dan konsisten langsung dianggap sah tanpa perlu persetujuan manual Finance.
2. **Fokus Pengawasan**: Finance memprioritaskan filter status:
   - `REVIEW_REQUIRED` (beberapa kandidat cocok)
   - `CONFLICT` (atribut wajib berbeda, mis. `1 Paket x 600.000` vs `12 Bulan x 50.000` atau selisih nominal)
   - `UNMATCHED` (unit lawan sudah import tetapi pasangan tidak ditemukan)
   - `WAITING_COUNTERPART` (unit lawan belum melakukan import)

---

## 3. Aksi yang Tersedia untuk Finance

### 3.1 Detail & Histori
Membuka perbandingan berdampingan antara **Item Sumber** dan **Item Lawan** (kode rekening, uraian, volume/satuan, nilai usulan, nilai disetujui, referensi sumber), penjelasan alasan pencocokan/perbedaan atribut, serta linimasa `RkaReconciliationHistory`.

### 3.2 Koreksi Finance (`adjust`)
Digunakan apabila Finance memverifikasi bahwa dua item memang berpasangan atau perlu perubahan status/unit lawan/nominal rekonsiliasi:
- Wajib mengisi **Alasan Koreksi Finance** (minimal 5 karakter).
- Mencatat `before_state`, `after_state`, aktor, sumber (`FINANCE_MONITORING`), dan waktu pada `rka_reconciliation_histories` serta `rka_logs`.
- **Catatan Penting:** Koreksi ini hanya memperbarui relasi rekonsiliasi dan **tidak** membuka kunci atau mengubah isi `RkaHeader` / `RkaItem` yang sudah `APPROVED`. Perubahan isi dokumen tetap harus melalui alur **Permintaan Buka Kunci → Persetujuan Finance → Import Ulang**.

### 3.3 Batalkan Relasi (`cancel`)
Membatalkan pasangan rekonsiliasi yang terbukti keliru dengan alasan wajib dan menyimpan histori pembatalan.

### 3.4 Rekonsiliasi Ulang (`rerun`)
Menjalankan evaluasi ulang untuk seluruh `RkaHeader` pada satu `RkaSchedule` dengan alasan wajib, tanpa menimpa koreksi atau pembatalan yang sudah direview oleh Finance.
