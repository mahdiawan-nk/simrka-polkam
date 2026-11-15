<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Worksheet 2: BELANJA (Seluruh item anggaran dalam 6 kelompok belanja).
 */
class BelanjaSheet implements Export, WithEvents, WithTitle
{
    private const CURRENCY_FORMAT = '#,##0;(#,##0);"-"';

    private const VOLUME_FORMAT = '#,##0.##';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
    ) {}

    public function title(): string
    {
        return 'BELANJA';
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $this->buildSheet($event->sheet->getDelegate());
            },
        ];
    }

    private function buildSheet(Worksheet $sheet): void
    {
        $meta = $this->data['meta'];
        $belanja = $this->data['belanja'];
        $groups = $belanja['groups'];

        $headers = [
            'ITEM_REF_ID',
            'KELOMPOK_BELANJA',
            'PROGRAM_KEGIATAN',
            'KODE_REKENING',
            'NAMA_REKENING',
            'GROUP_HEADER',
            'URAIAN_ITEM',
            'VOL_USULAN',
            'SATUAN',
            'HARGA_SATUAN_USULAN',
            'NILAI_USULAN',
            'VOL_DISETUJUI',
            'HARGA_SATUAN_DISETUJUI',
            'NILAI_DISETUJUI',
            'STATUS_ITEM',
            'ASAL_ITEM',
            'KETERANGAN_REVIEW',
        ];

        $lastCol = Coordinate::stringFromColumnIndex(count($headers)); // Q

        // Kop Dokumen Resmi (mengacu sheet Belanja di data-rka/)
        $sheet->setCellValue('A1', 'RENCANA KERJA DAN ANGGARAN');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'POLITEKNIK KAMPAR');
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'UNIT KERJA: '.strtoupper((string) $meta['unit_name']));
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A4', 'TAHUN ANGGARAN '.$meta['fiscal_year'].' ('.$meta['period_range'].')');
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Ringkasan Total Usulan & Disetujui (Row 6-7)
        $sheet->setCellValue('A6', 'Jumlah Usulan');
        $sheet->setCellValue('C6', $belanja['grand_total_proposed']);
        $sheet->getStyle('A6:C6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('C6')->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $sheet->setCellValue('A7', 'Jumlah Disetujui');
        $sheet->setCellValue('C7', $belanja['grand_total_approved']);
        $sheet->getStyle('A7:C7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('C7')->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle('C7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');

        // Section Title + Group Banners (Row 9-10)
        $row = 9;
        $sheet->setCellValue("A{$row}", 'RINCIAN ANGGARAN BELANJA MENURUT KELOMPOK BELANJA');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);
        $row++;

        $sheet->setCellValue("H{$row}", 'USULAN UNIT');
        $sheet->mergeCells("H{$row}:K{$row}");
        $sheet->getStyle("H{$row}:K{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->setCellValue("L{$row}", 'HASIL REVIEW');
        $sheet->mergeCells("L{$row}:Q{$row}");
        $sheet->getStyle("L{$row}:Q{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $row++;

        // Table Header (Row 11)
        $headerRow = $row;
        foreach ($headers as $idx => $headerLabel) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$col}{$row}", $headerLabel);
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(26);
        $sheet->freezePane('A'.($headerRow + 1));
        $row++;

        $subtotalRows = [];

        if (count($groups) === 0) {
            $sheet->setCellValue("A{$row}", 'Tidak ada item belanja pada dokumen RKA ini.');
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['italic' => true, 'size' => 10],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $row++;
        } else {
            foreach ($groups as $group) {
                // Banner Kelompok Belanja
                $sheet->setCellValue("A{$row}", strtoupper((string) $group['label']));
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BDD7EE']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $row++;

                $groupStartRow = $row;

                foreach ($group['rows'] as $item) {
                    $sheet->setCellValue("A{$row}", $item['ITEM_REF_ID']);
                    $sheet->setCellValue("B{$row}", $item['KELOMPOK_BELANJA']);
                    $sheet->setCellValue("C{$row}", $item['PROGRAM_KEGIATAN']);
                    $sheet->setCellValue("D{$row}", $item['KODE_REKENING']);
                    $sheet->setCellValue("E{$row}", $item['NAMA_REKENING']);
                    $sheet->setCellValue("F{$row}", $item['GROUP_HEADER']);
                    $sheet->setCellValue("G{$row}", $item['URAIAN_ITEM']);
                    $sheet->setCellValue("H{$row}", $item['VOL_USULAN']);
                    $sheet->setCellValue("I{$row}", $item['SATUAN']);
                    $sheet->setCellValue("J{$row}", $item['HARGA_SATUAN_USULAN']);
                    $sheet->setCellValue("K{$row}", "=H{$row}*J{$row}");
                    $sheet->setCellValue("L{$row}", $item['VOL_DISETUJUI']);
                    $sheet->setCellValue("M{$row}", $item['HARGA_SATUAN_DISETUJUI']);

                    $expectedApproved = round((float) $item['VOL_DISETUJUI'] * (float) $item['HARGA_SATUAN_DISETUJUI'], 2);
                    if (abs($expectedApproved - (float) $item['NILAI_DISETUJUI']) < 1.0) {
                        $sheet->setCellValue("N{$row}", "=L{$row}*M{$row}");
                    } else {
                        $sheet->setCellValue("N{$row}", (float) $item['NILAI_DISETUJUI']);
                    }

                    $sheet->setCellValue("O{$row}", $item['STATUS_ITEM']);
                    $sheet->setCellValue("P{$row}", $item['ASAL_ITEM']);
                    $sheet->setCellValue("Q{$row}", $item['KETERANGAN_REVIEW']);

                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'font' => ['size' => 10],
                        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("O{$row}:P{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode(self::VOLUME_FORMAT);
                    $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode(self::VOLUME_FORMAT);
                    $sheet->getStyle("J{$row}:K{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
                    $sheet->getStyle("M{$row}:N{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
                    $sheet->getStyle("H{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
                    $sheet->getStyle("L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');

                    $row++;
                }

                $groupEndRow = $row - 1;

                // Subtotal per Kelompok Belanja
                $sheet->setCellValue("A{$row}", 'JUMLAH '.strtoupper((string) $group['label']));
                $sheet->mergeCells("A{$row}:J{$row}");
                $sheet->setCellValue("K{$row}", "=SUM(K{$groupStartRow}:K{$groupEndRow})");
                $sheet->setCellValue("N{$row}", "=SUM(N{$groupStartRow}:N{$groupEndRow})");
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
                $sheet->getStyle("N{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);

                $subtotalRows[] = $row;
                $row++;
            }

            // Grand Total Belanja
            $kRefs = implode('+', array_map(fn (int $r): string => "K{$r}", $subtotalRows));
            $nRefs = implode('+', array_map(fn (int $r): string => "N{$r}", $subtotalRows));

            $sheet->setCellValue("A{$row}", 'GRAND TOTAL BELANJA');
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("K{$row}", "={$kRefs}");
            $sheet->setCellValue("N{$row}", "={$nRefs}");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
            $sheet->getStyle("N{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);

            // Hubungkan Ringkasan atas dengan Grand Total
            $sheet->setCellValue('C6', "=K{$row}");
            $sheet->setCellValue('C7', "=N{$row}");

            $row += 2;
        }

        // Blok Pengesahan (hanya menampilkan data aktual dari database)
        $row = $this->writeApprovalBlock($sheet, $row, $meta);

        $this->applyColumnWidths($sheet);
        $this->applyPageSetup($sheet, $lastCol, max(1, $row - 1));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeApprovalBlock(Worksheet $sheet, int $startRow, array $meta): int
    {
        $row = $startRow;
        $dateText = $meta['approved_at'] ?: '....................';
        $sheet->setCellValue("L{$row}", $meta['signing_place'].', '.$dateText);
        $row++;

        $sheet->setCellValue("B{$row}", 'Diusulkan oleh,');
        $sheet->setCellValue("G{$row}", 'Direview oleh,');
        $sheet->setCellValue("L{$row}", 'Disetujui oleh,');
        $row++;

        $sheet->setCellValue("B{$row}", 'KEPALA '.strtoupper((string) $meta['unit_name']));
        $sheet->setCellValue("G{$row}", 'WADIR KEUANGAN');
        $sheet->setCellValue("L{$row}", 'DIREKTUR POLITEKNIK KAMPAR');
        $sheet->getStyle("B{$row}:N{$row}")->getFont()->setBold(true)->setSize(10);

        $row += 4;

        $sheet->setCellValue("B{$row}", $meta['unit_head_name'] ?: '(....................................)');
        $sheet->setCellValue("G{$row}", $meta['reviewer_name'] ?: '(....................................)');
        $sheet->setCellValue("L{$row}", $meta['director_name'] ?: '(....................................)');
        $sheet->getStyle("B{$row}:N{$row}")->getFont()->setBold(true)->setSize(10);
        $row++;

        if ($meta['director_nrp'] !== '') {
            $sheet->setCellValue("L{$row}", 'NRP. '.$meta['director_nrp']);
            $sheet->getStyle("L{$row}")->getFont()->setSize(9);
            $row++;
        }

        return $row;
    }

    private function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 12, 'B' => 24, 'C' => 28, 'D' => 14, 'E' => 24,
            'F' => 24, 'G' => 36, 'H' => 11, 'I' => 10, 'J' => 16,
            'K' => 18, 'L' => 12, 'M' => 16, 'N' => 18, 'O' => 18,
            'P' => 18, 'Q' => 30,
        ];

        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    private function applyPageSetup(Worksheet $sheet, string $lastCol, int $lastRow): void
    {
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setPrintArea("A1:{$lastCol}{$lastRow}");

        $sheet->setShowGridlines(true);
    }
}
