<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class RkaItemsImport implements ToCollection, WithCalculatedFormulas
{
    protected int $rkaHeaderId;
    protected ?int $rkaIndicatorId;

    /**
     * @param int $rkaHeaderId
     * @param int|null $rkaIndicatorId
     */
    public function __construct(int $rkaHeaderId, ?int $rkaIndicatorId = null)
    {
        $this->rkaHeaderId = $rkaHeaderId;
        $this->rkaIndicatorId = $rkaIndicatorId;
    }

    public function collection(Collection $rows): void
    {
        $currentAccountId = null;
        $currentGroupHeader = null; // Menampung nama kelompok akun (contoh: "Belanja Alat Tulis Kantor")
        $currentIndicatorId = $this->rkaIndicatorId;
        $currentFormType = '5.1';
        $affectedIndicatorIds = [];

        if ($this->rkaIndicatorId) {
            $affectedIndicatorIds[$this->rkaIndicatorId] = true;
        }

        foreach ($rows as $row) {
            $code        = trim((string) ($row[0] ?? ''));
            $description = trim((string) ($row[1] ?? ''));
            $volumeRaw   = $row[2] ?? null;
            $unitSymbol  = trim((string) ($row[3] ?? ''));
            $priceRaw    = $row[4] ?? null;

            // 1. Skip baris header label / penomoran kolom
            if (
                in_array(strtolower($code), ['kode rekening', '1']) ||
                in_array(strtolower($description), ['uraian', '2'])
            ) {
                continue;
            }

            // 2. Jika kolom Kode Rekening diisi (misal: "5.2", "5.2.1", "5.2.1.01")
            if (! empty($code)) {
                $account = Account::where('code', $code)->first();

                // Simpan description dari Excel sebagai group_header saat kode tingkat rincian ditemukan (misal: "Belanja Alat Tulis Kantor")
                if (! empty($description)) {
                    $currentGroupHeader = $description;
                }

                if ($account) {
                    // JIKA AKUN LEVEL 1 (Formulir RKA / Indikator Utama, contoh: 5.2 / 5.3)
                    if ($account->level == 1) {
                        if (is_null($this->rkaIndicatorId)) {
                            $indicator = RkaIndicator::where('rka_header_id', $this->rkaHeaderId)
                                ->where('account_id', $account->id)
                                ->first();

                            if ($indicator) {
                                $currentIndicatorId = $indicator->id;
                                $currentFormType = $account->code;
                                $affectedIndicatorIds[$indicator->id] = true;
                            }
                        } else {
                            $currentFormType = $account->code;
                        }
                    }
                    // JIKA AKUN LEVEL REKENING / ITEM (Level 3 / Level 4)
                    else {
                        $currentAccountId = $account->id;

                        // Auto-detect form_type dari 2 digit pertama kode akun
                        $codeParts = explode('.', $account->code);
                        if (count($codeParts) >= 2) {
                            $currentFormType = $codeParts[0] . '.' . $codeParts[1];
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
                                }
                            }
                        }
                    }
                }

                // Baris header rekening tidak disimpan sebagai RkaItem
                continue;
            }

            // 3. Jika Kode Rekening KOSONG, Uraian & Volume Ada -> SIMPAN ITEM
            if (empty($code) && ! empty($description) && is_numeric($volumeRaw)) {

                if (! $currentIndicatorId || ! $currentAccountId) {
                    continue;
                }

                $volume = (float) $volumeRaw;
                $unitPrice = (float) filter_var($priceRaw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

                if ($volume > 0) {
                    RkaItem::updateOrCreate(
                        [
                            'rka_header_id'    => $this->rkaHeaderId,
                            'rka_indicator_id' => $currentIndicatorId,
                            'account_id'       => $currentAccountId,
                            'description'      => $description,
                        ],
                        [
                            'form_type'        => $currentFormType,
                            'group_header'     => $currentGroupHeader, // Menyoal kolom baru group_header
                            'proposed_volume'  => $volume,
                            'unit_symbol'      => $unitSymbol ?: 'Unit',
                            'unit_price'       => $unitPrice,
                            'status'           => 'PENDING',
                        ]
                    );
                }
            }
        }

        // 4. Recalculate JSON matrix
        foreach (array_keys($affectedIndicatorIds) as $indicatorId) {
            $indicator = RkaIndicator::find($indicatorId);
            if ($indicator && method_exists($indicator, 'recalculateMasukanTargetValue')) {
                $indicator->recalculateMasukanTargetValue();
            }
        }
    }
}