<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Departmentseeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $direktoratId = DB::table('departments')->insertGetId([
            'parent_id' => null,
            'code'      => 'DIR',
            'type'      => 'direktorat',
            'name'      => 'Direktorat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Level Bidang (Wadir / Vice Directors)
        $wadir1Id = DB::table('departments')->insertGetId([
            'parent_id' => $direktoratId,
            'code'      => 'WADIR-1',
            'type'      => 'bidang',
            'name'      => 'Bidang Akademik & Kemahasiswaan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wadir2Id = DB::table('departments')->insertGetId([
            'parent_id' => $direktoratId,
            'code'      => 'WADIR-2',
            'type'      => 'bidang',
            'name'      => 'Bidang Keuangan, Umum & Kepegawaian',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wadir3Id = DB::table('departments')->insertGetId([
            'parent_id' => $direktoratId,
            'code'      => 'WADIR-3',
            'type'      => 'bidang',
            'name'      => 'Bidang Kerjasama, Humas & Alumni',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Sub-Unit di bawah Wadir 1 (Akademik & Kemahasiswaan)
        $wadir1Departments = [
            ['code' => 'BP3T', 'type' => 'badan', 'name' => 'BP3T'],
            ['code' => 'BPM',  'type' => 'badan', 'name' => 'Badan Penjaminan Mutu (BPM)'],
            ['code' => 'P3M',  'type' => 'badan', 'name' => 'Pusat Penelitian dan Pengabdian Masyarakat (P3M)'],
            ['code' => 'BAA',  'type' => 'bagian', 'name' => 'Bagian Administrasi Akademik (BAA)'],
            ['code' => 'BAK',  'type' => 'bagian', 'name' => 'Bagian Administrasi Kemahasiswaan (BAK)'],
            ['code' => 'PRODI-TPS', 'type' => 'prodi', 'name' => 'Prodi Teknik Pengolahan Sawit (TPS)'],
            ['code' => 'PRODI-PPM', 'type' => 'prodi', 'name' => 'Prodi Perawatan dan Perbaikan Mesin (PPM)'],
            ['code' => 'PRODI-TIF', 'type' => 'prodi', 'name' => 'Prodi Teknik Informatika (TIF)'],
            ['code' => 'PRODI-ABI', 'type' => 'prodi', 'name' => 'Prodi Administrasi Bisnis (ABI)'],
            ['code' => 'PRODI-TPKS', 'type' => 'prodi', 'name' => 'Prodi Teknologi Produksi Tanaman Perkebunan (TPKS)'],
            ['code' => 'PRODI-PP',  'type' => 'prodi', 'name' => 'Prodi Pengelolaan Perkebunan (PP)'],
            ['code' => 'PRODI-MAB', 'type' => 'prodi', 'name' => 'Prodi Manajemen Agribisnis (MAB)'],
            ['code' => 'PRODI-TRL', 'type' => 'prodi', 'name' => 'Prodi Teknologi Rekayasa Logistik (TRL)'],
            ['code' => 'UPT-ICT', 'type' => 'upt', 'name' => 'UPT ICT'],
            ['code' => 'UPT-PERPUSTAKAAN', 'type' => 'upt', 'name' => 'UPT Perpustakaan'],
        ];

        foreach ($wadir1Departments as $dept) {
            DB::table('departments')->insert([
                'parent_id' => $wadir1Id,
                'code'      => $dept['code'],
                'type'      => $dept['type'],
                'name'      => $dept['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Sub-Unit di bawah Wadir 2 (Keuangan, Umum & Kepegawaian)
        $wadir2Departments = [
            ['code' => 'BAKKU', 'type' => 'bagian', 'name' => 'Bagian Administrasi Keuangan & Umum (BAKKU)'],
            ['code' => 'UPT-BISNIS', 'type' => 'upt', 'name' => 'UPT Bisnis'],
            ['code' => 'UPT-PPA', 'type' => 'upt', 'name' => 'UPT PPA'],
        ];

        foreach ($wadir2Departments as $dept) {
            DB::table('departments')->insert([
                'parent_id' => $wadir2Id,
                'code'      => $dept['code'],
                'type'      => $dept['type'],
                'name'      => $dept['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Sub-Unit di bawah Wadir 3 (Kerjasama, Humas & Alumni)
        $wadir3Departments = [
            ['code' => 'BAKHA', 'type' => 'bagian', 'name' => 'Bagian Administrasi KERJASAMA HUMAS ALUMNI'],
            ['code' => 'UPT-PMB', 'type' => 'upt', 'name' => 'UPT Penerimaan Mahasiswa Baru'],
            ['code' => 'UPT-MEDIA', 'type' => 'upt', 'name' => 'UPT Media Center'],
            ['code' => 'UPT-PPKPK', 'type' => 'upt', 'name' => 'UPT Pusat Pengembangan Karir & Alumni (PPK-PK)'],
        ];

        foreach ($wadir3Departments as $dept) {
            DB::table('departments')->insert([
                'parent_id' => $wadir3Id,
                'code'      => $dept['code'],
                'type'      => $dept['type'],
                'name'      => $dept['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
