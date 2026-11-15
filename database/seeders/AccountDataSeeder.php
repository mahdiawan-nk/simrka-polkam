<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class AccountDataSeeder extends Seeder
{
    /**
     * Master Data Kelompok (Level 1) dan Jenis (Level 2) sesuai format RKA
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

    public function run(): void
    {
        // 1. Buat atau perbarui master akun Kelompok (Level 1) dan Jenis (Level 2)
        foreach ($this->parentCategories as $code => $data) {
            Account::updateOrCreate(
                ['code' => $code],
                [
                    'code_clean'  => str_replace('.', '', $code),
                    'name'        => $data['name'],
                    'parent_code' => $data['parent'],
                    'level'       => $data['level'],
                    'level_label' => $data['level_label'],
                    'is_active'   => true,
                ]
            );
        }

        // 2. Format dan perbarui data akun Rincian (Level 3) & akun existing lainnya
        $accounts = DB::table('accounts')->get();

        foreach ($accounts as $account) {
            $clean = str_replace('.', '', $account->code);
            $segments = explode('.', $account->code);
            $segmentCount = count($segments);

            $level = 3;
            $levelLabel = 'Rincian';
            $parentCode = null;

            if ($segmentCount === 2) { // Level 1 (misal: 5.1, 5.2)
                $level = 1;
                $levelLabel = 'Kelompok';
            } elseif ($segmentCount === 3) { // Level 2 (misal: 5.2.1, 5.2.2)
                $level = 2;
                $levelLabel = 'Jenis';
                $parentCode = $segments[0] . '.' . $segments[1];
            } elseif ($segmentCount === 4) { // Level 3 (misal: 5.2.1.03)
                $level = 3;
                $levelLabel = 'Rincian';
                $parentCode = $segments[0] . '.' . $segments[1] . '.' . $segments[2];
            }

            DB::table('accounts')
                ->where('id', $account->id)
                ->update([
                    'code_clean'  => $clean,
                    'level'       => $level,
                    'level_label' => $levelLabel,
                    'parent_code' => $parentCode,
                ]);
        }

        // 3. Hubungkan parent_id (Self-Referencing FK) berdasarkan parent_code
        $updatedAccounts = DB::table('accounts')->get();
        $codeToIdMap = $updatedAccounts->pluck('id', 'code');

        foreach ($updatedAccounts as $account) {
            if ($account->parent_code && isset($codeToIdMap[$account->parent_code])) {
                DB::table('accounts')
                    ->where('id', $account->id)
                    ->update([
                        'parent_id' => $codeToIdMap[$account->parent_code]
                    ]);
            }
        }
    }
}