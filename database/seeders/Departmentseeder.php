<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class Departmentseeder extends Seeder
{
    /**
     * Daftar unit kerja resmi Politeknik Kampar sesuai struktur organisasi aktual.
     *
     * @return list<array{code: string, type: string, name: string, parent_code: ?string}>
     */
    public static function definitions(): array
    {
        return [
            ['code' => 'DIR', 'type' => 'direktorat', 'name' => 'Direktorat', 'parent_code' => null],

            // Level Bidang (Wadir) & Badan di bawah Direktorat
            ['code' => 'WADIR-1', 'type' => 'bidang', 'name' => 'Bidang Akademik & Kemahasiswaan', 'parent_code' => 'DIR'],
            ['code' => 'WADIR-2', 'type' => 'bidang', 'name' => 'Bidang Keuangan, Umum & Kepegawaian', 'parent_code' => 'DIR'],
            ['code' => 'WADIR-3', 'type' => 'bidang', 'name' => 'Bidang Kerjasama, Humas & Alumni', 'parent_code' => 'DIR'],
            ['code' => 'BP3T', 'type' => 'badan', 'name' => 'BP3T', 'parent_code' => 'DIR'],
            ['code' => 'BPM', 'type' => 'badan', 'name' => 'Badan Penjaminan Mutu (BPM)', 'parent_code' => 'DIR'],
            ['code' => 'P3M', 'type' => 'badan', 'name' => 'Pusat Penelitian dan Pengabdian Masyarakat (P3M)', 'parent_code' => 'DIR'],

            // Sub-Unit di bawah Wadir 1
            ['code' => 'BAA', 'type' => 'bagian', 'name' => 'Bagian Administrasi Akademik (BAA)', 'parent_code' => 'WADIR-1'],
            ['code' => 'BAK', 'type' => 'bagian', 'name' => 'Bagian Administrasi Kemahasiswaan (BAK)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-TPS', 'type' => 'prodi', 'name' => 'Prodi Teknik Pengolahan Sawit (TPS)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-PPM', 'type' => 'prodi', 'name' => 'Prodi Perawatan dan Perbaikan Mesin (PPM)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-TIF', 'type' => 'prodi', 'name' => 'Prodi Teknik Informatika (TIF)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-ABI', 'type' => 'prodi', 'name' => 'Prodi Administrasi Bisnis (ABI)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-TPKS', 'type' => 'prodi', 'name' => 'Prodi Teknologi Produksi Tanaman Perkebunan (TPKS)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-PP', 'type' => 'prodi', 'name' => 'Prodi Pengelolaan Perkebunan (PP)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-MAB', 'type' => 'prodi', 'name' => 'Prodi Manajemen Agribisnis (MAB)', 'parent_code' => 'WADIR-1'],
            ['code' => 'PRODI-TRL', 'type' => 'prodi', 'name' => 'Prodi Teknologi Rekayasa Logistik (TRL)', 'parent_code' => 'WADIR-1'],
            ['code' => 'UPT-ICT', 'type' => 'upt', 'name' => 'UPT ICT', 'parent_code' => 'WADIR-1'],
            ['code' => 'UPT-PERPUSTAKAAN', 'type' => 'upt', 'name' => 'UPT Perpustakaan', 'parent_code' => 'WADIR-1'],

            // Sub-Unit di bawah Wadir 2 & BAKKU
            ['code' => 'BAKKU', 'type' => 'bagian', 'name' => 'Bagian Administrasi Keuangan & Umum (BAKKU)', 'parent_code' => 'WADIR-2'],
            ['code' => 'UPT-BISNIS', 'type' => 'upt', 'name' => 'UPT Bisnis', 'parent_code' => 'WADIR-2'],
            ['code' => 'UPT-PPA', 'type' => 'upt', 'name' => 'UPT PPA', 'parent_code' => 'WADIR-2'],
            ['code' => 'BAG-UMUM', 'type' => 'Sub Divisi', 'name' => 'Bag. UMUM', 'parent_code' => 'BAKKU'],

            // Sub-Unit di bawah Wadir 3
            ['code' => 'BAKHA', 'type' => 'bagian', 'name' => 'Bagian Administrasi KERJASAMA HUMAS ALUMNI', 'parent_code' => 'WADIR-3'],
            ['code' => 'UPT-PMB', 'type' => 'upt', 'name' => 'UPT Penerimaan Mahasiswa Baru', 'parent_code' => 'WADIR-3'],
            ['code' => 'UPT-MEDIA', 'type' => 'upt', 'name' => 'UPT Media Center', 'parent_code' => 'WADIR-3'],
            ['code' => 'UPT-PPKPK', 'type' => 'upt', 'name' => 'UPT Pusat Pengembangan Karir & Alumni (PPK-PK)', 'parent_code' => 'WADIR-3'],
        ];
    }

    public function run(): void
    {
        $codeToId = [];

        // Tahap 1: Pastikan seluruh unit tersedia tanpa menimpa perubahan nama administrator
        foreach (self::definitions() as $item) {
            $department = Department::query()->firstOrCreate(
                ['code' => $item['code']],
                [
                    'type' => $item['type'],
                    'name' => $item['name'],
                    'parent_id' => null,
                ]
            );

            $codeToId[$item['code']] = $department->id;
        }

        // Tahap 2: Hubungkan relasi parent_id hanya jika belum terisi
        foreach (self::definitions() as $item) {
            if (! $item['parent_code'] || ! isset($codeToId[$item['parent_code']])) {
                continue;
            }

            Department::query()
                ->where('code', $item['code'])
                ->whereNull('parent_id')
                ->update(['parent_id' => $codeToId[$item['parent_code']]]);
        }
    }
}
