<?php

namespace App\Exports;

use App\Exports\Sheets\BelanjaSheet;
use App\Exports\Sheets\DaftarPenyesuaianSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export workbook RKA APPROVED dengan tepat dua worksheet: DAFTAR PENYESUAIAN dan BELANJA.
 */
class ApprovedRkaTemplateExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new DaftarPenyesuaianSheet($this->data),
            new BelanjaSheet($this->data),
        ];
    }
}
