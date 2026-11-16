<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountDataSeeder extends Seeder
{
    /**
     * Master Data Kelompok (Level 1) dan Jenis (Level 2) sesuai format RKA.
     *
     * @var array<string, array{name: string, level: int, level_label: string, parent: ?string}>
     */
    protected array $parentCategories = [
        // Level 1: Kelompok
        '5.1' => ['name' => 'Belanja Pegawai', 'level' => 1, 'level_label' => 'Kelompok', 'parent' => null],
        '5.2' => ['name' => 'Belanja Barang dan Jasa', 'level' => 1, 'level_label' => 'Kelompok', 'parent' => null],
        '5.3' => ['name' => 'Belanja Modal', 'level' => 1, 'level_label' => 'Kelompok', 'parent' => null],
        '5.4' => ['name' => 'Belanja Hibah', 'level' => 1, 'level_label' => 'Kelompok', 'parent' => null],
        '5.5' => ['name' => 'Belanja Pajak', 'level' => 1, 'level_label' => 'Kelompok', 'parent' => null],
        '2.1' => ['name' => 'Pembayaran Kewajiban Jangka Pendek', 'level' => 1, 'level_label' => 'Kelompok', 'parent' => null],

        // Level 2: Jenis (5.1.x - Belanja Pegawai)
        '5.1.1' => ['name' => 'Belanja Gaji dan Tunjangan', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.1'],
        '5.1.2' => ['name' => 'Belanja Honor Kegiatan', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.1'],
        '5.1.3' => ['name' => 'Belanja Sosial', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.1'],

        // Level 2: Jenis (5.2.x - Belanja Barang dan Jasa)
        '5.2.1' => ['name' => 'Belanja Bahan Habis Dipakai', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.2'],
        '5.2.2' => ['name' => 'Belanja Jasa', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.2'],
        '5.2.3' => ['name' => 'Belanja Pemeliharaan', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.2'],
        '5.2.4' => ['name' => 'Belanja Perjalanan Dinas', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.2'],
        '5.2.5' => ['name' => 'Belanja Kegiatan', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.2'],
        '5.2.6' => ['name' => 'Belanja Pengembangan SDM', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.2'],

        // Level 2: Jenis (5.3.x - Belanja Modal)
        '5.3.1' => ['name' => 'Belanja Modal Aset Tetap', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.3'],

        // Level 2: Jenis (5.4.x - Belanja Hibah)
        '5.4.1' => ['name' => 'Belanja Hibah', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.4'],

        // Level 2: Jenis (5.5.x - Belanja Pajak)
        '5.5.1' => ['name' => 'Belanja Pajak', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '5.5'],

        // Level 2: Jenis (2.1.x - Pembayaran Kewajiban)
        '2.1.1' => ['name' => 'Hutang Jangka Pendek', 'level' => 2, 'level_label' => 'Jenis', 'parent' => '2.1'],
    ];

    /**
     * Master Data Rincian Akun (Level 3) aktual Politeknik Kampar.
     *
     * @var array<string, string>
     */
    protected array $detailAccounts = [
        '2.1.1.03' => 'Pembayaran Hutang Gaji',
        '5.1.1.01' => 'Gaji Pokok',
        '5.1.1.02' => 'Tunjangan Istri/Suami',
        '5.1.1.03' => 'Tunjangan Anak',
        '5.1.1.04' => 'Tunjangan Golongan',
        '5.1.1.05' => 'Tunjangan Fungsional',
        '5.1.1.06' => 'Tunjangan Struktural',
        '5.1.1.07' => 'Tunjangan Tambahan Penghasilan Pegawai',
        '5.1.1.08' => 'Tunjangan Beras',
        '5.1.1.09' => 'Tunjangan Kinerja',
        '5.1.1.10' => 'Tunjangan Pengelola Keuangan',
        '5.1.2.01' => 'Honor Pembimbing, Penguji, Pengawas dan Wali',
        '5.1.2.02' => 'Honor Kegiatan Kepanitiaan',
        '5.1.2.03' => 'Honor Kegiatan Bisnis',
        '5.1.3.01' => 'BPJS Kesehatan',
        '5.1.3.02' => 'BPJS Ketenagakerjaan',
        '5.1.3.03' => 'Pakaian Dinas',
        '5.2.1.01' => 'Belanja Alat Tulis Kantor',
        '5.2.1.02' => 'Belanja Konsumsi (Makanan dan Minuman)',
        '5.2.1.03' => 'Belanja Rumah Tangga',
        '5.2.1.04' => 'Belanja Bahan Praktikum',
        '5.2.1.05' => 'Belanja Bahan Baku Produksi Bisnis',
        '5.2.1.07' => 'Belanja Spare Part Kendaraan dan Mesin',
        '5.2.2.01' => 'Belanja Listrik',
        '5.2.2.02' => 'Belanja Internet',
        '5.2.2.03' => 'Belanja Komunikasi',
        '5.2.2.04' => 'Belanja Sistem Informasi',
        '5.2.2.05' => 'Belanja Jasa Konsultan, Reviewer dan Narasumber',
        '5.2.3.01' => 'Belanja Pemeliharaan Gedung dan Bangunan',
        '5.2.3.02' => 'Belanja Pemeliharaan Peralatan dan Mesin',
        '5.2.3.03' => 'Belanja Pemeliharaan Jaringan',
        '5.2.3.04' => 'Belanja Pemeliharaan Kendaraan',
        '5.2.3.05' => 'Belanja Pemeliharaan Peralatan Kantor',
        '5.2.4.01' => 'Belanja Perjalanan Dinas Dalam Kota Kabupaten',
        '5.2.4.02' => 'Belanja Perjalanan Dinas Luar Kota Kabupaten',
        '5.2.4.03' => 'Belanja Perjalanan Dinas Luar Daerah (Luar Provinsi)',
        '5.2.4.04' => 'Belanja Perjalanan Dinas Luar Negeri',
        '5.2.5.01' => 'Belanja Kegiatan Akademik',
        '5.2.5.02' => 'Belanja Kegiatan Kemahasiswaan',
        '5.2.5.03' => 'Belanja Kegiatan Seminar/Workshop',
        '5.2.5.04' => 'Belanja Kegiatan Mutu',
        '5.2.5.05' => 'Belanja Promosi',
        '5.2.6.01' => 'Belanja Pendidikan Nongelar',
        '5.2.6.02' => 'Belanja Pendidikan Bergelar',
        '5.3.1.02' => 'Belanja Modal Gedung dan Bangunan',
        '5.3.1.03' => 'Belanja Modal Mesin (Peralatan Praktikum)',
        '5.3.1.04' => 'Belanja Modal Peralatan dan Tools',
        '5.3.1.05' => 'Belanja Modal Furniture',
        '5.3.1.06' => 'Belanja Modal Kendaraan',
        '5.4.1.01' => 'Belanja Hibah Penelitian dan Pengabdian',
        '5.4.1.02' => 'Belanja Hibah Penghargaan',
        '5.5.1.01' => 'Pajak Penghasilan Badan',
        '5.5.1.02' => 'Pajak Kendaraan Bermotor dan KIR',
    ];

    public function run(): void
    {
        // 1. Pastikan seluruh akun Kelompok (Level 1) dan Jenis (Level 2) tersedia
        foreach ($this->parentCategories as $code => $data) {
            Account::query()->firstOrCreate(
                ['code' => $code],
                [
                    'code_clean' => str_replace('.', '', $code),
                    'name' => $data['name'],
                    'parent_code' => $data['parent'],
                    'level' => $data['level'],
                    'level_label' => $data['level_label'],
                    'is_active' => true,
                ]
            );
        }

        // 2. Pastikan seluruh akun Rincian (Level 3) aktual tersedia
        foreach ($this->detailAccounts as $code => $name) {
            $segments = explode('.', $code);
            $parentCode = "{$segments[0]}.{$segments[1]}.{$segments[2]}";

            Account::query()->firstOrCreate(
                ['code' => $code],
                [
                    'code_clean' => str_replace('.', '', $code),
                    'name' => $name,
                    'parent_code' => $parentCode,
                    'level' => 3,
                    'level_label' => 'Rincian',
                    'is_active' => true,
                ]
            );
        }

        // 3. Sinkronkan atribut hierarki & hubungkan parent_id (Self-Referencing FK)
        $accounts = Account::query()->get();
        $codeToIdMap = $accounts->pluck('id', 'code');

        foreach ($accounts as $account) {
            $segments = explode('.', $account->code);
            $segmentCount = count($segments);

            $level = 3;
            $levelLabel = 'Rincian';
            $parentCode = null;

            if ($segmentCount === 2) {
                $level = 1;
                $levelLabel = 'Kelompok';
            } elseif ($segmentCount === 3) {
                $level = 2;
                $levelLabel = 'Jenis';
                $parentCode = "{$segments[0]}.{$segments[1]}";
            } elseif ($segmentCount === 4) {
                $level = 3;
                $levelLabel = 'Rincian';
                $parentCode = "{$segments[0]}.{$segments[1]}.{$segments[2]}";
            }

            $parentId = $parentCode && isset($codeToIdMap[$parentCode])
                ? $codeToIdMap[$parentCode]
                : null;

            $account->update([
                'code_clean' => str_replace('.', '', $account->code),
                'level' => $level,
                'level_label' => $levelLabel,
                'parent_code' => $parentCode,
                'parent_id' => $parentId,
            ]);
        }
    }
}
