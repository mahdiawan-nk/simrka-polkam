<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class RkaImportFormatExcel implements ToCollection, WithCalculatedFormulas
{
    protected int $rkaHeaderId;
    protected ?int $rkaIndicatorId;

    public function __construct(int $rkaHeaderId, ?int $rkaIndicatorId = null)
    {
        $this->rkaHeaderId = $rkaHeaderId;
        $this->rkaIndicatorId = $rkaIndicatorId;
    }

    public function collection(Collection $rows): void
    {
        $currentAccountId   = null;
        $currentAccountName = null;
        $currentGroupHeader = null; // Menambahkan variabel tracker Group Header
        $currentIndicatorId = $this->rkaIndicatorId;
        $currentFormType    = '5.2';
        $affectedIndicatorIds = [];

        if ($this->rkaIndicatorId) {
            $affectedIndicatorIds[$this->rkaIndicatorId] = true;
        }

        Log::info("=== START RKA IMPORT ===", [
            'rka_header_id'    => $this->rkaHeaderId,
            'rka_indicator_id' => $this->rkaIndicatorId,
            'total_rows'       => $rows->count(),
        ]);

        // Pemetaan Indeks Kolom Default Berdasarkan Spreadsheet Calc:
        // A=0, B=1, C=2, D=3, E=4 | Uraian=5 (F) | Volume=15 (P) | Satuan=16 (Q) | Harga=18 (S)
        $colVolumeIndex = 15;
        $colUnitIndex   = 16;
        $colPriceIndex  = 18;

        foreach ($rows as $rowIndex => $row) {
            // 1. Deteksi otomatis letak kolom Volume, Satuan, & Harga jika ada header tabel
            $rowArray = $row->toArray();
            foreach ($rowArray as $colIdx => $cellVal) {
                $valClean = strtolower(trim((string) $cellVal));
                if ($valClean === 'volume') {
                    $colVolumeIndex = $colIdx;
                } elseif (in_array($valClean, ['satuan', 'sat'])) {
                    $colUnitIndex = $colIdx;
                } elseif (str_contains($valClean, 'harga')) {
                    $colPriceIndex = $colIdx;
                }
            }

            // 2. Gabungkan Kolom A s/d E (Index 0 s/d 4) untuk membentuk Kode Rekening Utuh
            $codeParts = [];
            for ($i = 0; $i <= 4; $i++) {
                $val = trim((string) ($row[$i] ?? ''));
                if ($val !== '' && is_numeric($val)) {
                    $codeParts[] = $val;
                }
            }

            // Gabung tanpa titik (misal: '53101', '53104', '52601')
            $code = implode('', $codeParts);

            // Ambil Uraian (Index 5 / Kolom F)
            $description = trim((string) ($row[5] ?? ''));
            if (empty($description)) {
                // Fallback pencarian uraian jika ada offset/merged cell
                for ($k = 6; $k < $colVolumeIndex; $k++) {
                    $tempDesc = trim((string) ($row[$k] ?? ''));
                    if (!empty($tempDesc)) {
                        $description = $tempDesc;
                        break;
                    }
                }
            }

            $volumeRaw  = $row[$colVolumeIndex] ?? null;
            $unitSymbol = trim((string) ($row[$colUnitIndex] ?? ''));
            $priceRaw   = $row[$colPriceIndex] ?? null;

            // 3. Skip Baris Header Tabel / Baris Kosong / Judul Kolom
            if (empty($code) && (empty($description) || in_array(strtolower($description), ['uraian', 'rincian perhitungan', 'jumlah', 'rp']))) {
                continue;
            }

            // 4. KONDISI A: Terdeteksi Kode Rekening (Baris Level Akun / Account Header)
            if (! empty($code)) {
                $account = Account::where('code_clean', $code)->first();

                if (! $account) {
                    Log::warning("Row {$rowIndex}: Account TIDAK DITEMUKAN di DB untuk kode '{$code}'", [
                        'code'        => $code,
                        'description' => $description
                    ]);
                } else {
                    Log::info("Row {$rowIndex}: Account DITEMUKAN", [
                        'code'       => $account->code,
                        'account_id' => $account->id,
                        'level'      => $account->level
                    ]);

                    // Reset group header setiap berganti akun baru
                    $currentGroupHeader = null;

                    // JIKA AKUN LEVEL 1 (Formulir Utama)
                    if ($account->level == 1) {
                        if (is_null($this->rkaIndicatorId)) {
                            $indicator = RkaIndicator::where('rka_header_id', $this->rkaHeaderId)
                                ->where('account_id', $account->id)
                                ->first();

                            if ($indicator) {
                                $currentIndicatorId = $indicator->id;
                                $currentFormType = $account->code;
                                $affectedIndicatorIds[$indicator->id] = true;
                                Log::info("Row {$rowIndex}: Indicator terdeteksi dari Level 1", ['indicator_id' => $currentIndicatorId]);
                            }
                        } else {
                            $currentFormType = $account->code;
                        }
                    }
                    // JIKA AKUN LEVEL REKENING DETAIL (Level 2 / Level 3)
                    else {
                        $currentAccountId   = $account->id;
                        $currentAccountName = $description ?: $account->name;

                        $parts = explode('.', $account->code);
                        if (count($parts) >= 2) {
                            $currentFormType = $parts[0] . '.' . $parts[1];
                        }

                        if (is_null($this->rkaIndicatorId) && ! $currentIndicatorId) {
                            $level1Account = Account::where('code', $currentFormType)
                                ->where('level', 1)
                                ->first();

                            if ($level1Account) {
                                $indicator = RkaIndicator::where('rka_header_id', $this->rkaHeaderId)
                                    ->where('account_id', $level1Account->id)
                                    ->first();

                                if ($indicator) {
                                    $currentIndicatorId = $indicator->id;
                                    $affectedIndicatorIds[$indicator->id] = true;
                                    Log::info("Row {$rowIndex}: Indicator terdeteksi dari sub-account", ['indicator_id' => $currentIndicatorId]);
                                }
                            }
                        }
                    }
                }

                continue;
            }

            // 5. KONDISI B: Tidak ada Kode Rekening & Ada Uraian
            if (empty($code) && ! empty($description)) {

                // Sanitasi angka Volume
                $volumeClean = is_numeric($volumeRaw)
                    ? $volumeRaw
                    : filter_var((string)$volumeRaw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

                $volume = (float) $volumeClean;

                // JIKA VOLUME KOSONG / <= 0 -> DIANGGAP SEBAGAI GROUP HEADER
                if ($volume <= 0) {
                    $currentGroupHeader = $description;
                    Log::info("Row {$rowIndex}: Group Header Set -> '{$currentGroupHeader}'");
                    continue;
                }

                // Skip jika ID Indikator atau ID Akun belum tereset oleh baris sebelumnya
                if (! $currentIndicatorId || ! $currentAccountId) {
                    Log::warning("Row {$rowIndex}: ITEM DILEWATI (Indicator ID / Account ID KOSONG)", [
                        'description'        => $description,
                        'currentIndicatorId' => $currentIndicatorId,
                        'currentAccountId'   => $currentAccountId,
                    ]);
                    continue;
                }

                // Sanitasi angka Harga Satuan (Menghapus pemisah ribuan titik/koma)
                $priceStr   = str_replace(['.', ','], '', (string) $priceRaw);
                $priceClean = is_numeric($priceRaw)
                    ? $priceRaw
                    : (is_numeric($priceStr) ? $priceStr : filter_var((string)$priceRaw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));

                $unitPrice = (float) $priceClean;

                // Simpan Data Item ke Database dengan menyertakan Group Header
                $item = RkaItem::updateOrCreate(
                    [
                        'rka_header_id'    => $this->rkaHeaderId,
                        'rka_indicator_id' => $currentIndicatorId,
                        'account_id'       => $currentAccountId,
                        'description'      => $description,
                    ],
                    [
                        'form_type'        => $currentFormType,
                        'group_header'     => $currentGroupHeader, // Menyimpan nama Group Header aktif
                        'proposed_volume'  => $volume,
                        'unit_symbol'      => $unitSymbol ?: 'Unit',
                        'unit_price'       => $unitPrice,
                        'status'           => 'PENDING',
                    ]
                );

                Log::info("Row {$rowIndex}: BERHASIL SIMPAN ITEM RKA", [
                    'item_id'      => $item->id,
                    'description'  => $description,
                    'group_header' => $currentGroupHeader,
                    'volume'       => $volume,
                    'price'        => $unitPrice
                ]);
            }
        }

        Log::info("=== END RKA IMPORT ===", ['affected_indicators' => array_keys($affectedIndicatorIds)]);

        // 6. Recalculate JSON matrix indikator
        foreach (array_keys($affectedIndicatorIds) as $indicatorId) {
            $indicator = RkaIndicator::find($indicatorId);
            if ($indicator && method_exists($indicator, 'recalculateMasukanTargetValue')) {
                $indicator->recalculateMasukanTargetValue();
            }
        }
    }
}
