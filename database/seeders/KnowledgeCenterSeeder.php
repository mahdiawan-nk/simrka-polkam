<?php

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class KnowledgeCenterSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Pastikan permission Knowledge Center tersedia di Shield
        $kcPermissions = [
            'View:KnowledgeCenterPage',
            'ViewAny:KnowledgeArticle',
            'View:KnowledgeArticle',
            'Create:KnowledgeArticle',
            'Update:KnowledgeArticle',
            'Delete:KnowledgeArticle',
            'DeleteAny:KnowledgeArticle',
            'Publish:KnowledgeArticle',
            'Reorder:KnowledgeArticle',
            'ViewAny:KnowledgeCategory',
            'View:KnowledgeCategory',
            'Create:KnowledgeCategory',
            'Update:KnowledgeCategory',
            'Delete:KnowledgeCategory',
            'DeleteAny:KnowledgeCategory',
            'Reorder:KnowledgeCategory',
        ];

        foreach ($kcPermissions as $permName) {
            Permission::query()->firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // Berikan akses baca Pusat Panduan ke seluruh role aktif
        foreach (['super_admin', 'direktur', 'wadir_keuangan', 'keuangan', 'staf_keuangan', 'pemohon'] as $roleName) {
            if ($role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()) {
                $role->givePermissionTo('View:KnowledgeCenterPage');
            }
        }

        // Berikan akses kelola artikel & kategori ke super_admin
        if ($superAdmin = Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->first()) {
            $superAdmin->givePermissionTo($kcPermissions);
        }

        // 2. Kategori Panduan
        $categories = [
            'panduan-umum' => [
                'name' => 'Panduan Umum',
                'description' => 'Pengenalan sistem e-RKA, cara login, serta pergantian peran dan unit aktif.',
                'icon' => 'heroicon-o-information-circle',
                'sort_order' => 1,
            ],
            'panduan-pemohon' => [
                'name' => 'Panduan Unit & Pemohon',
                'description' => 'Panduan penyusunan RKA, pengajuan pencairan dana, pelacakan status, dan cetak PDF.',
                'icon' => 'heroicon-o-document-plus',
                'sort_order' => 2,
            ],
            'manajemen-rka-keuangan' => [
                'name' => 'Keuangan & Verifikasi',
                'description' => 'Pengaturan jadwal RKA, pagu unit, verifikasi per item, rekonsiliasi, dan buka kunci RKA.',
                'icon' => 'heroicon-o-calculator',
                'sort_order' => 3,
            ],
            'persetujuan-eksekutif' => [
                'name' => 'Pimpinan & Persetujuan',
                'description' => 'Panduan tinjauan dan persetujuan berjenjang untuk Wadir II dan Direktur.',
                'icon' => 'heroicon-o-shield-check',
                'sort_order' => 4,
            ],
            'pemantauan-keuangan' => [
                'name' => 'Pemantauan (Read-Only)',
                'description' => 'Panduan pemantauan data RKA dan pengajuan dana bagi Staf Keuangan.',
                'icon' => 'heroicon-o-eye',
                'sort_order' => 5,
            ],
            'administrasi-sistem' => [
                'name' => 'Administrasi Sistem',
                'description' => 'Manajemen pengguna, peran & izin (Shield), identitas aplikasi, dan backup database.',
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort_order' => 6,
            ],
        ];

        $catModels = [];
        foreach ($categories as $slug => $data) {
            $catModels[$slug] = KnowledgeCategory::query()->firstOrCreate(
                ['slug' => $slug],
                array_merge($data, ['is_active' => true])
            );
        }

        $roleMap = Role::query()->where('guard_name', 'web')->pluck('id', 'name');

        // 3. Artikel Panduan Berbasis Fitur Aktual
        $articles = [
            [
                'slug' => 'mengenal-simrka-dan-perpindahan-konteks',
                'category' => 'panduan-umum',
                'roles' => [],
                'title' => 'Mengenal e-RKA / SIMRKA & Pengaturan Peran/Unit Aktif',
                'excerpt' => 'Panduan dasar login, memahami tampilan dasbor, dan mengganti peran atau unit kerja aktif bagi pengguna multi-peran.',
                'module_key' => 'umum',
                'resource_fqcn' => null,
                'featured' => true,
                'reading_time' => 3,
                'sort_order' => 1,
                'content' => <<<'HTML'
<h2>Tujuan Penggunaan</h2>
<p>Membantu seluruh pengguna memahami cara masuk ke sistem e-RKA, membaca informasi Tahun Anggaran aktif, serta menggunakan fitur penggantian peran dan unit kerja (<em>Context Switcher</em>).</p>
<h2>Langkah-Langkah Penggunaan</h2>
<ol>
<li>Buka halaman masuk aplikasi dan masukkan <strong>Alamat Email</strong> serta <strong>Kata Sandi</strong> institusi Anda, lalu klik <strong>Masuk</strong>.</li>
<li>Pada bagian kanan atas (Topbar), periksa tombol <strong>Konteks Aktif</strong> yang menampilkan peran dan unit kerja yang sedang digunakan.</li>
<li>Jika Anda memiliki lebih dari satu peran atau ditugaskan sebagai PLT di unit lain, klik tombol konteks tersebut untuk berpindah peran/unit tanpa perlu keluar dari sistem.</li>
</ol>
<h2>Catatan dan Batasan</h2>
<ul>
<li>Menu dan data yang tampil pada Dasbor maupun tabel akan otomatis menyesuaikan peran dan unit kerja yang sedang aktif.</li>
<li>Selalu pastikan unit kerja aktif sudah sesuai sebelum membuat RKA atau Pengajuan Pencairan Dana.</li>
</ul>
HTML,
            ],
            [
                'slug' => 'panduan-pemohon-penyusunan-dan-pengajuan-rka',
                'category' => 'panduan-pemohon',
                'roles' => ['pemohon'],
                'title' => 'Panduan Pemohon: Menyusun & Mengajukan RKA Unit',
                'excerpt' => 'Langkah lengkap mengisi program, indikator kinerja, rincian item belanja, hingga mengajukan RKA untuk ditinjau.',
                'module_key' => 'rka',
                'resource_fqcn' => 'App\\Filament\\Resources\\RkaHeaders\\RkaHeaderResource',
                'featured' => true,
                'reading_time' => 5,
                'sort_order' => 2,
                'content' => <<<'HTML'
<h2>Siapa yang Dapat Menggunakan</h2>
<p>Pengguna dengan peran <strong>Pemohon / Unit Kerja</strong> pada jadwal pengusulan RKA yang sedang aktif.</p>
<h2>Lokasi Menu</h2>
<p>Buka menu <strong>Manajemen RKA &rarr; Pengusulan &amp; Review RKA</strong>.</p>
<h2>Langkah-Langkah Penggunaan</h2>
<ol>
<li>Pastikan Tahun Anggaran dan Jadwal Pengusulan RKA sedang aktif pada banner konteks di Dasbor.</li>
<li>Pilih dokumen RKA unit Anda yang berstatus <strong>Draft</strong> atau <strong>Perlu Perbaikan</strong>, lalu buka menu aksi <strong>Data Item</strong>.</li>
<li>Lengkapi indikator kinerja dan tambahkan rincian item belanja dengan memilih <strong>Kode Akun (COA)</strong>, volume, satuan, serta harga satuan yang sesuai.</li>
<li>Periksa total usulan agar tidak melebihi <strong>Pagu Anggaran Unit</strong> yang telah ditetapkan.</li>
<li>Setelah seluruh item lengkap, klik tombol <strong>Ajukan RKA</strong> untuk mengirim dokumen ke tahap tinjauan Keuangan dan Pimpinan.</li>
</ol>
<h2>Penjelasan Status RKA</h2>
<ul>
<li><strong>Draft</strong>: Dokumen masih dapat diubah dan dilengkapi oleh unit.</li>
<li><strong>Diajukan</strong>: Menunggu pemeriksaan Keuangan / Pimpinan.</li>
<li><strong>Perlu Perbaikan</strong>: Dikembalikan dengan catatan revisi agar diperbaiki oleh unit.</li>
<li><strong>Disetujui</strong>: RKA telah disahkan dan itemnya dapat digunakan untuk Pengajuan Pencairan Dana.</li>
</ul>
HTML,
            ],
            [
                'slug' => 'panduan-pemohon-pengajuan-pencairan-dana-dan-cetak-pdf',
                'category' => 'panduan-pemohon',
                'roles' => ['pemohon'],
                'title' => 'Panduan Pemohon: Mengajukan Pencairan Dana & Cetak PDF',
                'excerpt' => 'Cara memilih item dari RKA Disetujui, memeriksa sisa pagu, mengirim pengajuan pencairan, dan mengunduh formulir PDF.',
                'module_key' => 'fund_request',
                'resource_fqcn' => 'App\\Filament\\Resources\\FundRequests\\FundRequestResource',
                'featured' => true,
                'reading_time' => 5,
                'sort_order' => 3,
                'content' => <<<'HTML'
<h2>Persyaratan Sebelum Menggunakan</h2>
<ul>
<li>Unit kerja telah memiliki dokumen <strong>RKA berstatus Disetujui (Approved)</strong> pada Tahun Anggaran berjalan.</li>
<li>Tersedia sisa pagu yang mencukupi pada item RKA yang akan dicairkan.</li>
</ul>
<h2>Lokasi Menu</h2>
<p>Buka menu <strong>Transaksi &rarr; Pengajuan Dana</strong>.</p>
<h2>Langkah-Langkah Penggunaan</h2>
<ol>
<li>Klik tombol <strong>Buat Pengajuan</strong>.</li>
<li>Isi informasi kegiatan, tanggal pelaksanaan, penanggung jawab, jenis pencairan (<em>Panjar</em>, <em>Reimburse</em>, atau <em>Langsung</em>), penerima, dan informasi rekening.</li>
<li>Pada bagian rincian item, klik pemilih item RKA untuk memilih pos belanja dari <strong>RKA Disetujui</strong> unit Anda. Sistem menampilkan sisa anggaran tersedia per item secara otomatis.</li>
<li>Masukkan nominal yang dimohonkan (tidak boleh melebihi sisa anggaran) serta unggah lampiran pendukung jika diperlukan.</li>
<li>Simpan sebagai <strong>Draft</strong> atau langsung klik <strong>Ajukan</strong> untuk mengirim ke antrean verifikasi Keuangan.</li>
<li>Untuk mencetak formulir permohonan, klik aksi <strong>Cetak PDF</strong> pada baris pengajuan di tabel.</li>
</ol>
HTML,
            ],
            [
                'slug' => 'panduan-keuangan-verifikasi-fund-request-dan-manajemen-rka',
                'category' => 'manajemen-rka-keuangan',
                'roles' => ['keuangan'],
                'title' => 'Panduan Keuangan: Jadwal, Pagu Unit, & Verifikasi Pengajuan Dana',
                'excerpt' => 'Prosedur pengelolaan jadwal RKA, penetapan pagu unit, verifikasi item pengajuan dana, rekonsiliasi antarunit, dan permintaan buka kunci.',
                'module_key' => 'fund_request',
                'resource_fqcn' => 'App\\Filament\\Resources\\FundRequests\\FundRequestResource',
                'featured' => true,
                'reading_time' => 6,
                'sort_order' => 4,
                'content' => <<<'HTML'
<h2>Tugas Utama Peran Keuangan</h2>
<p>Mengelola jadwal dan pagu RKA, meninjau usulan RKA unit, memverifikasi kelayakan item pada Pengajuan Pencairan Dana, serta memproses rekonsiliasi dan pembukaan kunci RKA.</p>
<h2>Alur Verifikasi Pengajuan Pencairan Dana</h2>
<ol>
<li>Buka <strong>Dasbor</strong> (widget <em>Antrean Verifikasi Keuangan</em>) atau menu <strong>Transaksi &rarr; Pengajuan Dana</strong>.</li>
<li>Klik aksi <strong>Verifikasi</strong> pada pengajuan berstatus <strong>Diajukan</strong>.</li>
<li>Periksa kesesuaian kode akun, sisa pagu unit, dan dokumen lampiran pada setiap baris item.</li>
<li>Tentukan nilai disetujui per item atau berikan catatan penyesuaian apabila terdapat koreksi nominal.</li>
<li>Simpan hasil verifikasi untuk meneruskan dokumen ke tahap persetujuan <strong>Wadir II (BAKKU)</strong>.</li>
</ol>
<h2>Pengelolaan RKA &amp; Rekonsiliasi</h2>
<ul>
<li><strong>Jadwal Pengusulan RKA</strong>: Atur periode aktif dan fase (Pengusulan, Review, Pelaksanaan).</li>
<li><strong>Alokasi Pagu Unit</strong>: Tetapkan batas pagu tahunan per unit kerja sebelum RKA disusun.</li>
<li><strong>Permintaan Buka Kunci RKA</strong>: Setujui atau tolak permohonan import ulang RKA yang sudah disetujui.</li>
<li><strong>Rekonsiliasi Antarunit</strong>: Pantau pencocokan penyesuaian anggaran antarunit dan lakukan koreksi apabila diperlukan.</li>
</ul>
HTML,
            ],
            [
                'slug' => 'panduan-wadir-tinjauan-dan-persetujuan-berjenjang',
                'category' => 'persetujuan-eksekutif',
                'roles' => ['wadir_keuangan'],
                'title' => 'Panduan Wadir II (BAKKU): Persetujuan Pengajuan & Penyerapan Unit',
                'excerpt' => 'Cara memeriksa antrean persetujuan Wadir II, memantau laporan penyerapan unit, dan menindaklanjuti permintaan buka kunci RKA.',
                'module_key' => 'fund_request',
                'resource_fqcn' => 'App\\Filament\\Resources\\FundRequests\\FundRequestResource',
                'featured' => true,
                'reading_time' => 4,
                'sort_order' => 5,
                'content' => <<<'HTML'
<h2>Kewenangan Wadir II (BAKKU)</h2>
<p>Melakukan tinjauan dan persetujuan tingkat kedua setelah verifikasi Keuangan pada RKA dan Pengajuan Pencairan Dana, serta memantau tren pencairan bulanan.</p>
<h2>Langkah-Langkah Persetujuan</h2>
<ol>
<li>Pada <strong>Dasbor</strong>, periksa widget <em>Antrean Persetujuan Wadir BAKKU</em> untuk melihat dokumen yang telah terverifikasi oleh Keuangan.</li>
<li>Klik aksi tinjau/verifikasi untuk memeriksa ringkasan nilai usulan, nilai rekomendasi Keuangan, serta sisa pagu unit terkait.</li>
<li>Pilih <strong>Setujui</strong> untuk meneruskan ke persetujuan final Direktur, atau kembalikan untuk revisi apabila diperlukan perbaikan.</li>
</ol>
HTML,
            ],
            [
                'slug' => 'panduan-direktur-persetujuan-akhir-dan-kinerja-institusi',
                'category' => 'persetujuan-eksekutif',
                'roles' => ['direktur'],
                'title' => 'Panduan Direktur: Persetujuan Final & Pemantauan Kinerja Anggaran',
                'excerpt' => 'Panduan membaca ringkasan anggaran kampus, matriks jenis akun, performa 5 unit tertinggi/terendah, dan memberikan persetujuan akhir.',
                'module_key' => 'dashboard',
                'resource_fqcn' => 'App\\Filament\\Resources\\RkaHeaders\\RkaHeaderResource',
                'featured' => true,
                'reading_time' => 4,
                'sort_order' => 6,
                'content' => <<<'HTML'
<h2>Ringkasan Eksekutif pada Dasbor</h2>
<p>Dasbor Direktur menampilkan Total Anggaran Institusi, Total Realisasi Eksekutif, Persentase Penyerapan Institusi, grafik perbandingan antar tahun anggaran, serta daftar 5 unit dengan penyerapan tertinggi dan terendah.</p>
<h2>Langkah Persetujuan Akhir</h2>
<ol>
<li>Buka widget <strong>Antrean Persetujuan Direktur</strong> di Dasbor atau melalui menu <strong>Pengajuan Dana</strong> / <strong>Pengusulan &amp; Review RKA</strong>.</li>
<li>Periksa riwayat verifikasi Keuangan dan Wadir II beserta catatan pada dokumen terkait.</li>
<li>Berikan persetujuan final (<strong>Setujui</strong>) atau kembalikan untuk perbaikan sesuai pertimbangan pimpinan.</li>
</ol>
HTML,
            ],
            [
                'slug' => 'panduan-staf-keuangan-pemantauan-read-only',
                'category' => 'pemantauan-keuangan',
                'roles' => ['staf_keuangan'],
                'title' => 'Panduan Staf Keuangan: Pemantauan RKA & Pengajuan Dana (Read-Only)',
                'excerpt' => 'Cara memantau status RKA, pengajuan pencairan dana, pagu unit, dan rincian transaksi secara read-only.',
                'module_key' => 'dashboard',
                'resource_fqcn' => null,
                'featured' => true,
                'reading_time' => 3,
                'sort_order' => 7,
                'content' => <<<'HTML'
<h2>Ruang Lingkup Akses</h2>
<p>Peran <strong>Staf Keuangan</strong> memiliki hak akses <strong>baca saja (Read-Only)</strong> untuk memantau seluruh unit kerja tanpa kewenangan mengubah, memverifikasi, atau menyetujui dokumen.</p>
<h2>Fitur yang Dapat Digunakan</h2>
<ol>
<li><strong>Dasbor Pemantauan</strong>: Menampilkan ringkasan verifikasi, grafik alokasi pagu vs realisasi per unit, porsi jenis pencairan, serta tabel <em>Pemantauan Pengajuan Pencairan Dana (Read-Only)</em>.</li>
<li><strong>Melihat Detail Pengajuan &amp; RKA</strong>: Buka menu <strong>Transaksi &rarr; Pengajuan Dana</strong> atau <strong>Manajemen RKA &rarr; Pengusulan &amp; Review RKA</strong>, lalu klik <strong>Lihat Detail</strong> untuk meninjau rincian item dan riwayat persetujuan.</li>
<li><strong>Melihat Master Data</strong>: Dapat melihat daftar <strong>Alokasi Pagu Unit</strong>, <strong>Kode Akun (COA)</strong>, dan <strong>Unit / Departemen</strong>.</li>
</ol>
HTML,
            ],
            [
                'slug' => 'panduan-super-admin-pengguna-shield-brand-dan-backup',
                'category' => 'administrasi-sistem',
                'roles' => ['super_admin'],
                'title' => 'Panduan Super Admin: Pengguna, Peran & Izin, Identitas Brand, & Backup',
                'excerpt' => 'Panduan administrasi sistem mencakup manajemen pengguna, pengaturan hak akses Filament Shield, identitas aplikasi dinamis, dan backup database.',
                'module_key' => 'system',
                'resource_fqcn' => 'App\\Filament\\Pages\\Settings\\BrandIdentitySettings',
                'featured' => true,
                'reading_time' => 6,
                'sort_order' => 8,
                'content' => <<<'HTML'
<h2>Tanggung Jawab Super Admin</h2>
<p>Mengelola akun pengguna, mengatur hak akses melalui Filament Shield, menyesuaikan identitas aplikasi secara dinamis, serta menjaga ketersediaan salinan database.</p>
<h2>Pengelolaan Utama</h2>
<ol>
<li><strong>Pengguna &amp; Penugasan Unit</strong> (<em>Pengaturan Akses &rarr; Pengguna</em>): Tambah/ubah akun, impor pengguna, dan tetapkan unit kerja utama maupun PLT.</li>
<li><strong>Peran &amp; Izin</strong> (<em>Pengaturan Akses &rarr; Peran &amp; Izin</em>): Atur izin akses per peran untuk Resource, Halaman (Pages), dan Widget. Seluruh halaman kustom mengikuti izin centang pada tab <em>Halaman</em>.</li>
<li><strong>Identitas Aplikasi</strong> (<em>Pengaturan Sistem &rarr; Identitas Aplikasi</em>): Ubah nama brand, institusi, tagline, logo, judul browser, dan favicon secara langsung tanpa mengubah kode.</li>
<li><strong>Backup Database</strong> (<em>Database &rarr; Manajemen Backup Database</em>): Buat backup manual sesuai kebutuhan dan unduh file cadangan secara aman.</li>
</ol>
<h2>Batasan Keamanan</h2>
<ul>
<li>Pastikan hanya peran yang berwenang yang diberi akses ke halaman sensitif seperti <em>Manajemen Backup Database</em> dan <em>Identitas Aplikasi</em>.</li>
<li>Menu <em>Alat Pengembang (Pembersihan Data RKA)</em> hanya aktif pada lingkungan non-production untuk mencegah kehilangan data operasional.</li>
</ul>
HTML,
            ],
        ];

        foreach ($articles as $data) {
            $category = $catModels[$data['category']];

            $article = KnowledgeArticle::query()->firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => $category->id,
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'],
                    'status' => KnowledgeArticle::STATUS_PUBLISHED,
                    'featured' => $data['featured'],
                    'reading_time' => $data['reading_time'],
                    'sort_order' => $data['sort_order'],
                    'module_key' => $data['module_key'],
                    'resource_fqcn' => $data['resource_fqcn'],
                    'doc_version' => '1.0',
                    'last_verified_at' => now(),
                    'maintainer_name' => 'Tim UPT ICT',
                    'published_at' => now(),
                ]
            );

            if ($article->roles()->count() === 0 && ! empty($data['roles'])) {
                $targetRoleIds = collect($data['roles'])
                    ->map(fn (string $name): ?int => $roleMap[$name] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                if ($targetRoleIds !== []) {
                    $article->roles()->sync($targetRoleIds);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
