<?php

namespace App\Exports;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AccountExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    /**
     * Query data accounts
     */
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return Account::query()->with('parent')->orderBy('code', 'asc');
    }

    /**
     * Baris Header Excel
     */
    public function headings(): array
    {
        return [
            'Kode Rekening',
            'Nama Uraian Akun',
            'Tingkat',
            'Keterangan / Contoh Isi',
            'Catatan Pengelolaan',
            'Pos Terpusat',
            'Status Aktif',
        ];
    }

    /**
     * Mapping data per baris
     */
    public function map($account): array
    {
        return [
            $account->code,
            $account->name,
            $account->level_label,
            $account->description ?? '-',
            $account->managed_by_note ?? '-',
            $account->is_centralized ? 'Ya' : 'Tidak',
            $account->is_active ? 'Aktif' : 'Non-Aktif',
        ];
    }

    /**
     * Style Header Excel
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Header: Bold, Tekst Wit, Achtergrond Donkergrijs/Charcoal
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '1F2937'],
                ],
            ],
        ];
    }

    /**
     * Event Register voor dynamische achtergrondkleuren op rijniveau
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Loop door alle datarijen (vanaf rij 2)
                for ($row = 2; $row <= $highestRow; $row++) {
                    // Kolom D (index 4) bevat de waarde 'level_label' / 'Tingkat'
                    $levelLabel = trim($sheet->getCell("C{$row}")->getValue());

                    if ($levelLabel === 'Kelompok') {
                        // Kleur voor Kelompok: Blauw/Grijs accent met vetgedrukte tekst
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'D1E7DD'], // Soft Pastel Groen/Blauw (of aanpasbaar)
                            ],
                        ]);
                    } elseif ($levelLabel === 'Jenis') {
                        // Kleur voor Jenis: Zachte gele/oranje tint
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFF3CD'], // Soft Pastel Geel
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}