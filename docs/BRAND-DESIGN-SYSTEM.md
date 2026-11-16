# SIMRKA / e-RKA — Brand Design System
**Konsep: Modern Academic Finance**

Dokumentasi ini merupakan standar resmi sistem warna, token desain, arsitektur CSS, dan integrasi Filament 5 untuk aplikasi SIMRKA / e-RKA.

---

## 1. Filosofi Desain

Konsep **Modern Academic Finance** menggabungkan ketegasan institusional dunia akademik dengan presisi dan keterpercayaan sistem keuangan modern:

- **Profesional & Institusional**: Mengutamakan *Deep Navy* (`#17324D`) sebagai warna identitas utama yang berwibawa, menggantikan aksen neon/emerald-orange sebelumnya.
- **Bersih & Nyaman Digunakan**: Permukaan putih (`#FFFFFF`) di atas kanvas *Cool White* (`#F5F7FA`) dengan border halus *Soft Slate* (`#DCE3EA`) untuk kenyamanan operasional harian.
- **Presisi Finansial**: Seluruh nominal anggaran dan tabel menggunakan *tabular monospace* (`JetBrains Mono`) dengan hierarki visual yang jelas.
- **Terkendali & Konsisten**: Warna aksen (*Muted Teal* & *Soft Gold*) dan warna semantik hanya digunakan sesuai maknanya, tanpa gradien dekoratif berlebihan.

---

## 2. Brand Palette

| Token | Nama Warna | Hex | Fungsi Utama |
|---|---|---|---|
| `--brand-primary` | Deep Navy | `#17324D` | Identitas utama, tombol primer, navigasi aktif, heading penting |
| `--brand-secondary` | Academic Blue | `#245B85` | Elemen pendukung, state hover, focus ring, interaksi sekunder |
| `--brand-accent` | Muted Teal | `#168C83` | Aksen modern, indikator positif sekunder, seri chart disetujui |
| `--brand-highlight` | Soft Gold | `#C69A45` | Highlight akademik, indikator bar aktif, badge institusi/periode |

### Neutral Palette

| Token | Light Mode | Dark Mode | Fungsi |
|---|---|---|---|
| `--background` | `#F5F7FA` | `#0B1220` | Kanvas latar belakang aplikasi |
| `--surface` | `#FFFFFF` | `#111C2E` | Card, panel, tabel, modal, form |
| `--surface-muted` | `#F8FAFC` | `#162338` | Header tabel, input search, area sekunder |
| `--border` | `#DCE3EA` | `#1E2D45` | Border card, separator, garis tabel |
| `--text-primary` | `#1E293B` | `#F1F5F9` | Teks utama, angka KPI, judul |
| `--text-secondary` | `#64748B` | `#94A3B8` | Label, subheading, teks pendukung |

---

## 3. Semantic Colors

| Status | Warna | Hex | Makna Penggunaan |
|---|---|---|---|
| `success` | Emerald | `#15803D` | Disetujui (*Approved*), lunas (*Paid*), selesai (*Completed*), target tercapai |
| `warning` | Amber | `#B7791F` | Menunggu (*Pending*), dalam review/verifikasi, perlu revisi (*Revision*), *Trimmed* |
| `danger` | Rose | `#BE123C` | Ditolak (*Rejected*), defisit, peringatan kritis (>90% burn-rate) |
| `info` | Blue | `#2563EB` | Diajukan (*Submitted*), informasi netral aktif, log riwayat |
| `neutral` / `gray` | Slate | `#64748B` | *Draft*, arsip, data non-aktif, informasi sekunder |

---

## 4. CSS Variables & Tailwind v4 Bridge

Seluruh token dideklarasikan secara terpusat di `resources/css/filament/admin/theme.css` pada blok `:root`, `.dark`, dan `@theme`:

- **CSS Custom Properties (`:root` & `.dark`)**: `--brand-primary`, `--brand-secondary`, `--brand-accent`, `--brand-highlight`, `--surface`, `--surface-muted`, `--background`, `--border`, `--text-primary`, `--text-secondary`, `--success`, `--warning`, `--danger`, `--info`, `--neutral`.
- **Tailwind v4 `@theme` Tokens**: `--color-brand-primary`, `--color-brand-secondary`, `--color-brand-accent`, `--color-brand-highlight`, `--color-canvas`, `--color-surface`, `--color-border-soft`, `--color-ink-primary`, `--color-ink-secondary`.

Dengan `@theme`, komponen Blade dapat langsung menggunakan utility class Tailwind seperti `bg-brand-primary`, `text-brand-primary`, `text-brand-accent`, `border-brand-highlight/30`, dan sebagainya.

---

## 5. File CSS Utama

| File | Peran |
|---|---|
| `resources/css/filament/admin/theme.css` | **Single source of truth** untuk tema panel admin Filament 5, design tokens, tipografi, dan komponen UI |
| `resources/css/app.css` | Entry CSS standar untuk halaman publik (`welcome.blade.php`) |

---

## 6. Struktur Dependency CSS

```text
vite.config.js
├── resources/css/app.css
│   └── tailwindcss (v4.3.3 via @tailwindcss/vite)
├── resources/js/app.js
└── resources/css/filament/admin/theme.css (terdaftar di AdminPanelProvider via ->viteTheme())
    ├── Google Fonts (Plus Jakarta Sans & JetBrains Mono)
    ├── vendor/filament/filament/resources/css/theme.css
    │   └── vendor/filament/filament/resources/css/index.css
    │       ├── support, actions, forms, infolists, notifications, schemas, tables, widgets
    │       ├── base.css, utilities.css
    │       └── components/* & widgets/*
    ├── @source: app/Filament/**/*
    ├── @source: resources/views/filament/**/*
    ├── @source: resources/views/components/**/*
    └── Centralized :root / .dark / @theme tokens + Filament component styling
```

---

## 7. Integrasi Filament 5

Integrasi dilakukan di `app/Providers/Filament/AdminPanelProvider.php`:

```php
->viteTheme('resources/css/filament/admin/theme.css')
->colors([
    'primary' => Color::hex('#17324D'),
    'secondary' => Color::hex('#245B85'),
    'accent' => Color::hex('#168C83'),
    'highlight' => Color::hex('#C69A45'),
    'success' => Color::hex('#15803D'),
    'warning' => Color::hex('#B7791F'),
    'danger' => Color::hex('#BE123C'),
    'info' => Color::hex('#2563EB'),
    'gray' => Color::Slate,
])
```

Registrasi warna melalui `Panel::colors()` memungkinkan Filament 5 secara otomatis menghasilkan skala shade `50–950` untuk seluruh komponen native (Button, Badge, Input, Select, Checkbox, Radio, Toggle, Tabs, Pagination, Modal, Slide-over, dan Notification) tanpa perlu override `!important` yang agresif.

---

## 8. Aturan Penggunaan Warna

1. **Deep Navy (`#17324D`)** digunakan untuk identitas utama, tombol primer, dan angka pagu/total utama.
2. **White (`#FFFFFF`)** digunakan untuk seluruh permukaan card dan tabel pada light mode; hindari memberi warna latar berbeda pada setiap card tanpa alasan semantik.
3. **Muted Teal (`#168C83`)** digunakan secara terukur untuk aksen positif, seri anggaran disetujui pada chart, dan indikator penyerapan sehat.
4. **Soft Gold (`#C69A45`)** digunakan hanya untuk highlight akademik/institusional (garis aksen topbar, indikator menu aktif, badge tahun anggaran/identitas).
5. **Semantic Colors** hanya digunakan sesuai makna status, bukan sekadar dekorasi atau karena nominal bernilai besar.
6. **Kontras & Dark Mode**: Setiap warna teks dan latar harus menjaga keterbacaan di light mode maupun `.dark` mode.

---

## 9. Status Mapping (RKA & Fund Request)

| Status | Filament Color Key | Makna |
|---|---|---|
| `draft` | `gray` | Konsep awal, belum diajukan |
| `submitted` | `info` | Sudah diajukan, menunggu pemeriksaan awal |
| `pending`, `in_review`, `verified_kabakku`, `verified_wadir_bakku` | `warning` | Dalam proses verifikasi / persetujuan berjenjang |
| `revision`, `revision_required`, `trimmed` | `warning` | Memerlukan perbaikan atau penyesuaian pagu |
| `approved`, `paid`, `completed` | `success` | Disetujui final / dicairkan / selesai |
| `rejected` | `danger` | Ditolak / dibatalkan |

---

## 10. Panduan Menambah Komponen Baru

- **Filament Resource / Table / Action**: Gunakan key warna semantik Filament (`primary`, `secondary`, `accent`, `success`, `warning`, `danger`, `info`, `gray`). Jangan menggunakan nama warna Tailwind mentah seperti `'amber'` atau `'emerald'` pada `->color()`.
- **Chart Widget**: Gunakan palet terstandar:
  - Series 1 (Pagu / Utama): `#17324D`
  - Series 2 (Sekunder / Trend): `#245B85`
  - Series 3 (Disetujui / Positif): `#168C83`
  - Series 4 (Highlight / Lainnya): `#C69A45`
- **Custom Blade View**: Gunakan token Tailwind `@theme` (`bg-brand-primary`, `text-brand-accent`, `border-brand-highlight/30`) atau CSS variables (`var(--surface)`, `var(--border)`), serta selalu sertakan varian `dark:`.

---

## 11. Panduan Menjaga Konsistensi Brand

1. Jangan menambahkan file CSS baru jika aturan masih termasuk cakupan `resources/css/filament/admin/theme.css`.
2. Jangan menduplikasi deklarasi warna HEX di banyak file Blade atau PHP; gunakan token terpusat atau `Panel::colors()`.
3. Setelah mengubah `theme.css` atau view Blade, selalu jalankan `docker compose exec node npm run build` untuk memastikan bundle Vite terkompilasi tanpa error.
4. Pastikan setiap perubahan tetap menjaga kompatibilitas dark mode dan responsivitas Desktop, Tablet, dan Mobile.
