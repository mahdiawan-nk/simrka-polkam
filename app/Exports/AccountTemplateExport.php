<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithExportTemplate;

class AccountTemplateExport implements Export,WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Template Import COA' => new AccountTemplateDataSheet(),
            'Petunjuk Pengisian'  => new AccountTemplateInstructionSheet(),
        ];
    }

    
}

/**
 * Sheet 1: Template Data Utama
 */
class AccountTemplateDataSheet implements
    FromArray,
    WithHeadings,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    public function title(): string
    {
        return 'Template Import COA';
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Uraian',
            'Tingkat',
            'Keterangan / contoh isi',
            'Catatan Pengelolaan',
            'Pos Terpusat',
            'Status Aktif',
        ];
    }

    /**
     * Data Contoh Pengisian (Sample Rows)
     */
    public function array(): array
    {
        return [
            [
                'Kode'                   => '5.2',
                'Uraian'                 => 'Belanja Barang dan Jasa',
                'Tingkat'                => 'Kelompok',
                'Keterangan / contoh isi' => '',
                'Catatan Pengelolaan'    => '',
                'Pos Terpusat'           => 'Tidak',
                'Status Aktif'           => 'Aktif',
            ],
            [
                'Kode'                   => '5.2.1',
                'Uraian'                 => 'Belanja Bahan Habis Dipakai',
                'Tingkat'                => 'Jenis',
                'Keterangan / contoh isi' => 'Barang yang habis dipakai dalam satu kali penggunaan atau kurang dari satu tahun.',
                'Catatan Pengelolaan'    => '',
                'Pos Terpusat'           => 'Tidak',
                'Status Aktif'           => 'Aktif',
            ],
            [
                'Kode'                   => '5.2.1.01',
                'Uraian'                 => 'Belanja Alat Tulis Kantor',
                'Tingkat'                => 'Rincian',
                'Keterangan / contoh isi' => 'Kertas, tinta/toner, map, pulpen, stopmap, amplop.',
                'Catatan Pengelolaan'    => 'Dikelola oleh Bagian Umum',
                'Pos Terpusat'           => 'Ya',
                'Status Aktif'           => 'Aktif',
            ],
            [
                'Kode'                   => '5.2.2.04',
                'Uraian'                 => 'Belanja Sistem Informasi',
                'Tingkat'                => 'Rincian',
                'Keterangan / contoh isi' => 'Aplikasi premium, lisensi software, domain, hosting, cloud.',
                'Catatan Pengelolaan'    => 'Lisensi terpusat di UPT ICT',
                'Pos Terpusat'           => 'Ya',
                'Status Aktif'           => 'Aktif',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '1F2937'],
                ],
            ],
        ];
    }
}

/**
 * Sheet 2: Petunjuk & Aturan Pengisian
 */
class AccountTemplateInstructionSheet implements
    FromArray,
    WithTitle,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    public function title(): string
    {
        return 'Petunjuk Pengisian';
    }

    public function headings(): array
    {
        return [
            'Nama Kolom',
            'Status',
            'Format / Isian yang Diizinkan',
            'Keterangan & Penjelasan',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Kode',
                'Wajib',
                'Teks (Format titik, misal: 5.2 / 5.2.1 / 5.2.1.01)',
                'Kode rekening akun. Sistem otomatis membuatkan relasi induk (parent) berdasarkan susunan titik.',
            ],
            [
                'Uraian',
                'Wajib',
                'Teks Bebas',
                'Nama akun atau rincian komponen belanja.',
            ],
            [
                'Tingkat',
                'Opsional',
                'Kelompok / Jenis / Rincian',
                'Tingkat hirarki akun. Jika dikosongkan, sistem otomatis menentukan tingkat berdasarkan segmen kode.',
            ],
            [
                'Keterangan / contoh isi',
                'Opsional',
                'Teks Bebas',
                'Contoh item barang/jasa atau petunjuk bagi unit pengusul RKA.',
            ],
            [
                'Catatan Pengelolaan',
                'Opsional',
                'Teks Bebas',
                'Catatan khusus jika akun dikelola terpusat oleh unit tertentu (misal: Keuangan / UPT ICT).',
            ],
            [
                'Pos Terpusat',
                'Opsional',
                'Ya / Tidak',
                'Pilih "Ya" jika pengadaan dipusatkan pada satu unit untuk kebutuhan unit lain.',
            ],
            [
                'Status Aktif',
                'Opsional',
                'Aktif / Non-Aktif',
                'Default: "Aktif". Pilih "Non-Aktif" jika akun tidak boleh muncul saat pengajuan RKA.',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '059669'],
                ],
            ],
        ];
    }
}
