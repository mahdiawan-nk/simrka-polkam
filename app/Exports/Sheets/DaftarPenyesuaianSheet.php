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
 * Worksheet 1: DAFTAR PENYESUAIAN (Section A dan Section B).
 */
class DaftarPenyesuaianSheet implements Export, WithEvents, WithTitle
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
        return 'DAFTAR PENYESUAIAN';
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
        $sectionA = $this->data['daftar_penyesuaian']['section_a'];
        $sectionB = $this->data['daftar_penyesuaian']['section_b'];

        $headers = [
            'ADJ_REF_ID',
            'ORIGIN_ITEM_ID',
            'IMPORTED_ITEM_ID',
            'BAGIAN',
            'STATUS_PENYESUAIAN',
            'KODE_REKENING',
            'NAMA_REKENING',
            'URAIAN',
            'UNIT_ASAL',
            'UNIT_TUJUAN',
            'VOLUME',
            'SATUAN',
            'HARGA_SATUAN',
            'JUMLAH_USULAN',
            'JUMLAH_DISETUJUI',
            'KETERANGAN_REVIEWER',
            'REVIEWER',
        ];

        $lastCol = Coordinate::stringFromColumnIndex(count($headers)); // Q

        // Kop Dokumen (Row 1-3)
        $sheet->setCellValue('A1', 'DAFTAR PENYESUAIAN USULAN RKA TA '.$meta['fiscal_year']);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('A2', strtoupper((string) $meta['unit_name']).' — POLITEKNIK KAMPAR');
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10);

        $sheet->setCellValue('A3', 'Periode: '.$meta['period_range'].' | Status Dokumen: '.$meta['status']);
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->getFont()->setSize(9);

        $row = 5;

        // Section A Banner
        $row = $this->writeSectionBlock(
            sheet: $sheet,
            startRow: $row,
            title: 'A. USULAN UNIT YANG DIPINDAH, DITOLAK, DIHAPUS, ATAU DITUNDA (SECTION A)',
            emptyMessage: 'Tidak ada penyesuaian pada Section A.',
            headers: $headers,
            rows: $sectionA,
            lastCol: $lastCol,
        );

        $row += 1;

        // Section B Banner
        $row = $this->writeSectionBlock(
            sheet: $sheet,
            startRow: $row,
            title: 'B. POS YANG DIKELOLA UNIT INI UNTUK UNIT LAIN / TAMBAHAN SAAT REVIEW (SECTION B)',
            emptyMessage: 'Tidak ada penyesuaian pada Section B.',
            headers: $headers,
            rows: $sectionB,
            lastCol: $lastCol,
        );

        $this->applyColumnWidths($sheet);
        $this->applyPageSetup($sheet, $lastCol, max(1, $row - 1));
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeSectionBlock(
        Worksheet $sheet,
        int $startRow,
        string $title,
        string $emptyMessage,
        array $headers,
        array $rows,
        string $lastCol,
    ): int {
        $row = $startRow;

        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BDD7EE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $row++;

        // Table Header
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
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        if (count($rows) === 0) {
            $sheet->setCellValue("A{$row}", $emptyMessage);
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['italic' => true, 'size' => 10],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            return $row + 1;
        }

        $dataStartRow = $row;

        foreach ($rows as $item) {
            foreach ($headers as $idx => $key) {
                $col = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->setCellValue("{$col}{$row}", $item[$key] ?? null);
            }

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode(self::VOLUME_FORMAT);
            $sheet->getStyle("M{$row}:O{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
            $sheet->getStyle("O{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');

            $row++;
        }

        $dataEndRow = $row - 1;

        // Subtotal Row
        $sheet->setCellValue("A{$row}", 'JUMLAH');
        $sheet->mergeCells("A{$row}:M{$row}");
        $sheet->setCellValue("N{$row}", "=SUM(N{$dataStartRow}:N{$dataEndRow})");
        $sheet->setCellValue("O{$row}", "=SUM(O{$dataStartRow}:O{$dataEndRow})");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getStyle("N{$row}:O{$row}")->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);

        return $row + 1;
    }

    private function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 12, 'B' => 15, 'C' => 16, 'D' => 9, 'E' => 24,
            'F' => 14, 'G' => 24, 'H' => 38, 'I' => 18, 'J' => 18,
            'K' => 11, 'L' => 10, 'M' => 16, 'N' => 18, 'O' => 18,
            'P' => 32, 'Q' => 20,
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
