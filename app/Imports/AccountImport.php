<?php

namespace App\Imports;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Illuminate\Database\Eloquent\Model;

class AccountImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, WithEvents
{
    private array $accountMap = [];

    public function __construct()
    {
        // Pre-load data existing ke memory
        $this->accountMap = Account::pluck('id', 'code')->toArray();
    }

    /**
     * Trait SkipsEmptyRows membutuhkan method ini jika baris dianggap kosong bila tidak ada data utama.
     */
    public function prepareForValidation($data, $index)
    {
        // Trim semua nilai string di dalam baris
        $kode = trim((string) ($data['kode'] ?? ''));
        $uraian = trim((string) ($data['uraian'] ?? ''));

        // Jika kode & uraian kosong, kosongkan array agar dilewati oleh SkipsEmptyRows / Validation
        if (empty($kode) && empty($uraian)) {
            return [];
        }

        $data['kode'] = $kode;
        $data['uraian'] = $uraian;

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                DB::beginTransaction();
            },
            AfterImport::class => function (AfterImport $event) {
                DB::commit();
            },
            ImportFailed::class => function (ImportFailed $event) {
                DB::rollBack();
            },
        ];
    }

    public function model(array $row): Model|array|null
    {
        $code = trim((string) ($row['kode'] ?? ''));
        $name = trim((string) ($row['uraian'] ?? ''));

        if (empty($code) || empty($name)) {
            return null;
        }

        $tingkatExcel = trim((string) ($row['tingkat'] ?? ''));
        $description = trim((string) ($row['keterangan_contoh_isi'] ?? ''));
        $managedByNote = trim((string) ($row['catatan_pengelolaan'] ?? $row['managed_by_note'] ?? ''));
        $isCentralizedInput = strtolower(trim((string) ($row['pos_terpusat'] ?? $row['is_centralized'] ?? '')));
        $isActiveInput = strtolower(trim((string) ($row['status_aktif'] ?? $row['is_active'] ?? '')));

        // Parser code_clean
        $codeClean = str_replace('.', '', $code);

        // Kalkulasi Hirarki dan Parent Code
        $segments = explode('.', $code);
        $segmentCount = count($segments);

        $level = 3;
        $levelLabel = !empty($tingkatExcel) ? ucfirst(strtolower($tingkatExcel)) : 'Rincian';
        $parentCode = null;

        if ($segmentCount === 2) { 
            $level = 1;
            $levelLabel = !empty($tingkatExcel) ? $tingkatExcel : 'Kelompok';
            $parentCode = null;
        } elseif ($segmentCount === 3) { 
            $level = 2;
            $levelLabel = !empty($tingkatExcel) ? $tingkatExcel : 'Jenis';
            $parentCode = $segments[0] . '.' . $segments[1];
        } elseif ($segmentCount === 4) { 
            $level = 3;
            $levelLabel = !empty($tingkatExcel) ? $tingkatExcel : 'Rincian';
            $parentCode = $segments[0] . '.' . $segments[1] . '.' . $segments[2];
        }

        // Cari ID Parent dari Map Memory
        $parentId = ($parentCode && isset($this->accountMap[$parentCode])) 
            ? $this->accountMap[$parentCode] 
            : null;

        // Flag Pos Terpusat & Status Aktif
        $isCentralized = in_array($isCentralizedInput, ['ya', '1', 'true', 'yes']);
        $isActive = !in_array($isActiveInput, ['non-aktif', 'tidak', '0', 'false', 'off']);

        // Upsert ke database
        $account = Account::updateOrCreate(
            ['code' => $code],
            [
                'code_clean'      => $codeClean,
                'name'            => $name,
                'description'     => $description ?: null,
                'managed_by_note' => $managedByNote ?: null,
                'parent_id'       => $parentId,
                'parent_code'     => $parentCode,
                'level'           => $level,
                'level_label'     => $levelLabel,
                'is_centralized'  => $isCentralized,
                'is_active'       => $isActive,
            ]
        );

        // Simpan ke memory map
        $this->accountMap[$code] = $account->id;

        return $account;
    }

    public function rules(): array
    {
        return [
            '*.kode'   => ['nullable', 'required_with:*.uraian'],
            '*.uraian' => ['nullable', 'required_with:*.kode'],
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            '*.kode'   => 'Kode Rekening',
            '*.uraian' => 'Nama Uraian Akun',
        ];
    }
}