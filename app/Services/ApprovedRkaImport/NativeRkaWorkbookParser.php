<?php

namespace App\Services\ApprovedRkaImport;

use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parser workbook Excel RKA asli Politeknik Kampar (12-13 worksheet).
 */
class NativeRkaWorkbookParser
{
    /**
     * Enam worksheet belanja resmi beserta kode kelompoknya.
     *
     * @var array<string, string>
     */
    public const BELANJA_SHEETS = [
        'Belanja Pegawai' => '5.1',
        'Belanja Barang dan Jasa' => '5.2',
        'Belanja Modal' => '5.3',
        'Belanja Hibah' => '5.4',
        'Belanja Pajak' => '5.5',
        'Pembayaran Hutang' => '2.1',
    ];

    /**
     * Worksheet inti yang wajib ada pada workbook RKA asli.
     *
     * @var array<int, string>
     */
    public const REQUIRED_CORE_SHEETS = [
        'RINGKASAN',
        'DAFTAR PENYESUAIAN',
        'BERITA ACARA',
        'IDENTITAS',
        'REKAP',
    ];

    /**
     * Worksheet referensi/bantu yang diabaikan sebagai data bisnis.
     *
     * @var array<int, string>
     */
    public const IGNORED_SHEETS = [
        'TB',
        'DAFTAR',
    ];

    public function __construct(
        private readonly DepartmentResolver $departmentResolver,
    ) {}

    /**
     * Mendeteksi apakah file Excel menggunakan format workbook RKA asli (native).
     */
    public function isNativeWorkbook(string $filePath): bool
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $sheetNames = $reader->listWorksheetNames($filePath);

        return in_array('IDENTITAS', $sheetNames, true)
            && in_array('REKAP', $sheetNames, true)
            && in_array('RINGKASAN', $sheetNames, true);
    }

    /**
     * Mem-parsing seluruh workbook RKA asli menjadi struktur tervalidasi dan siap rekonsiliasi.
     *
     * @param  Collection<string, Account>  $accountsByCode
     * @param  Collection<int, Department>  $departments
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $filePath, Collection $accountsByCode, Collection $departments): array
    {
        $spreadsheet = IOFactory::load($filePath);

        $sheetsMeta = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetsMeta[] = [
                'name' => $sheet->getTitle(),
                'state' => $sheet->getSheetState(),
                'ignored' => in_array($sheet->getTitle(), self::IGNORED_SHEETS, true),
            ];
        }

        $missingCore = [];
        foreach (self::REQUIRED_CORE_SHEETS as $requiredName) {
            if (! $spreadsheet->sheetNameExists($requiredName)) {
                $missingCore[] = $requiredName;
            }
        }

        $presentBelanjaSheets = [];
        foreach (self::BELANJA_SHEETS as $sheetName => $groupCode) {
            if ($spreadsheet->sheetNameExists($sheetName)) {
                $presentBelanjaSheets[$sheetName] = $groupCode;
            }
        }

        if (count($missingCore) > 0 || count($presentBelanjaSheets) === 0) {
            $missingText = count($missingCore) > 0
                ? 'Worksheet inti tidak ditemukan: '.implode(', ', $missingCore).'.'
                : 'Tidak ditemukan satu pun worksheet Belanja resmi.';

            throw new InvalidArgumentException("Struktur workbook RKA asli tidak kompatibel. {$missingText}");
        }

        $warnings = [];
        $fatalErrors = [];
        $infos = [];

        $identitas = $this->parseIdentitas($spreadsheet->getSheetByName('IDENTITAS'), $warnings);
        $identitasResolution = $this->departmentResolver->resolve($identitas['unit_kerja'] ?? null, $departments);
        $matchedDepartment = $identitasResolution['department'];

        if ($matchedDepartment === null && ($identitas['unit_kerja'] ?? null) !== null && trim((string) $identitas['unit_kerja']) !== '') {
            $warnings[] = 'IDENTITAS!C5: '.($identitasResolution['reason'] ?? 'Unit kerja gagal dipetakan ke master Departemen.');
        }

        if ($identitas['pagu_anggaran'] === null) {
            $warnings[] = 'PAGU BELUM DIISI pada worksheet IDENTITAS (C13 kosong).';
        }

        $belanjaSheetsData = [];
        $allItems = [];
        $totalProposedAll = 0.0;
        $totalApprovedAll = 0.0;

        foreach ($presentBelanjaSheets as $sheetName => $groupCode) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $parsed = $this->parseBelanjaSheet($sheet, $groupCode, $sheetName, $accountsByCode, $warnings, $fatalErrors);
            $belanjaSheetsData[$groupCode] = $parsed;
            $totalProposedAll += $parsed['parsed_total_proposed'];
            $totalApprovedAll += $parsed['parsed_total_approved'];

            foreach ($parsed['items'] as $item) {
                $allItems[] = $item;
            }
        }

        $adjustments = $this->parseDaftarPenyesuaian(
            $spreadsheet->getSheetByName('DAFTAR PENYESUAIAN'),
            $accountsByCode,
            $departments,
            $allItems,
            $warnings,
            $fatalErrors,
        );

        $ringkasan = $this->parseRingkasan($spreadsheet->getSheetByName('RINGKASAN'), $warnings);
        if ($ringkasan['usulan_awal_unit'] === null || $ringkasan['usulan_awal_unit'] < 0) {
            $warnings[] = "Nilai 'Usulan awal unit' pada worksheet RINGKASAN tidak ditemukan atau tidak valid.";
            $fatalErrors[] = "Nilai 'Usulan awal unit' pada worksheet RINGKASAN tidak ditemukan atau tidak dapat dibaca sehingga pagu_budget tidak dapat ditentukan tanpa menebak.";
        }
        $rekap = $this->parseRekap($spreadsheet->getSheetByName('REKAP'), $warnings);
        $beritaAcara = $this->parseBeritaAcara($spreadsheet->getSheetByName('BERITA ACARA'), $warnings);

        $reconciliation = $this->reconcile(
            belanjaSheetsData: $belanjaSheetsData,
            totalProposedAll: $totalProposedAll,
            totalApprovedAll: $totalApprovedAll,
            ringkasan: $ringkasan,
            rekap: $rekap,
            identitas: $identitas,
            warnings: $warnings,
            fatalErrors: $fatalErrors,
        );

        if (count($allItems) === 0) {
            $fatalErrors[] = 'Tidak ditemukan satu pun item belanja valid pada keenam worksheet Belanja.';
        }

        $infos[] = 'Worksheet dikenali: '.count($sheetsMeta).' sheet (sheet teknis TB/DAFTAR diabaikan dari data bisnis).';

        return [
            'format' => 'native',
            'sheets_meta' => $sheetsMeta,
            'identitas' => $identitas,
            'matched_department' => $matchedDepartment ? [
                'id' => $matchedDepartment->id,
                'code' => $matchedDepartment->code,
                'name' => $matchedDepartment->name,
            ] : null,
            'belanja_sheets' => $belanjaSheetsData,
            'belanja_rows' => $allItems,
            'adjustment_rows' => $adjustments,
            'ringkasan' => $ringkasan,
            'rekap' => $rekap,
            'berita_acara' => $beritaAcara,
            'reconciliation' => $reconciliation,
            'fatal_errors' => array_values(array_unique($fatalErrors)),
            'warnings' => array_values(array_unique($warnings)),
            'infos' => array_values(array_unique($infos)),
            'total_proposed' => round($totalProposedAll, 2),
            'total_approved' => round($totalApprovedAll, 2),
        ];
    }

    /**
     * Mencocokkan nama unit dari IDENTITAS!C5 ke master Department secara deterministik.
     *
     * @param  Collection<int, Department>  $departments
     */
    public function matchDepartment(?string $excelUnitName, Collection $departments): ?Department
    {
        return $this->departmentResolver->resolve($excelUnitName, $departments)['department'];
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function parseIdentitas(Worksheet $sheet, array &$warnings): array
    {
        $map = [];
        $highestRow = $sheet->getHighestRow();

        for ($r = 1; $r <= $highestRow; $r++) {
            $label = trim((string) $sheet->getCell("B{$r}")->getValue());
            if ($label === '') {
                continue;
            }

            $cell = $sheet->getCell("C{$r}");
            $val = $this->readScalarCell($cell, $warnings, "IDENTITAS!C{$r}");
            $map[mb_strtolower($label)] = $val;
        }

        $findByPrefix = function (string $prefix) use ($map): mixed {
            foreach ($map as $label => $value) {
                if (str_starts_with($label, mb_strtolower($prefix))) {
                    return $value;
                }
            }

            return null;
        };

        $paguRaw = $findByPrefix('pagu anggaran unit');
        $paguValue = ($paguRaw !== null && $paguRaw !== '' && is_numeric($paguRaw))
            ? round((float) $paguRaw, 2)
            : null;

        $reviewDateRaw = $findByPrefix('tanggal review');
        $reviewDate = $this->formatPossibleExcelDate($reviewDateRaw);

        $submissionDateRaw = $findByPrefix('tanggal pengajuan');
        $submissionDate = $this->formatPossibleExcelDate($submissionDateRaw);

        return [
            'unit_kerja' => $this->cleanNullableString($findByPrefix('unit kerja')),
            'kepala_unit' => $this->cleanNullableString($findByPrefix('nama kepala unit')),
            'nip_kepala_unit' => $this->cleanNullableString($findByPrefix('nip / nrp kepala unit')),
            'tanggal_pengajuan' => $submissionDate,
            'tahun_anggaran' => $this->cleanNullableString($findByPrefix('tahun anggaran')),
            'periode' => $this->cleanNullableString($findByPrefix('periode')),
            'pagu_anggaran' => $paguValue,
            'pagu_status' => $paguValue === null ? 'PAGU BELUM DIISI' : 'TERISI',
            'nama_direktur' => $this->cleanNullableString($findByPrefix('nama direktur')),
            'nrp_direktur' => $this->cleanNullableString($findByPrefix('nrp direktur')),
            'ketua_yayasan' => $this->cleanNullableString($findByPrefix('ketua yayasan')),
            'tempat_penandatanganan' => $this->cleanNullableString($findByPrefix('tempat penandatanganan')),
            'nama_reviewer' => $this->cleanNullableString($findByPrefix('nama reviewer')),
            'jabatan_reviewer' => $this->cleanNullableString($findByPrefix('jabatan reviewer')),
            'nip_reviewer' => $this->cleanNullableString($findByPrefix('nip / nrp reviewer')),
            'tanggal_review' => $reviewDate,
        ];
    }

    /**
     * @param  Collection<string, Account>  $accountsByCode
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $fatalErrors
     * @return array<string, mixed>
     */
    private function parseBelanjaSheet(
        Worksheet $sheet,
        string $groupCode,
        string $sheetName,
        Collection $accountsByCode,
        array &$warnings,
        array &$fatalErrors,
    ): array {
        $highestRow = $sheet->getHighestRow();
        $programName = '';
        $activityName = $sheetName;
        $location = 'Politeknik Kampar';
        $indicatorMatrix = [];
        $inIndicatorBlock = false;
        $headerRow = null;

        // 1. Baca metadata formulir, matriks indikator, dan cari baris header tabel secara dinamis
        for ($r = 1; $r <= $highestRow; $r++) {
            $colA = trim((string) $this->readScalarCell($sheet->getCell("A{$r}"), $warnings, "{$sheetName}!A{$r}"));
            $colB = trim((string) $this->readScalarCell($sheet->getCell("B{$r}"), $warnings, "{$sheetName}!B{$r}"));

            if (strcasecmp($colA, 'Kode Rekening') === 0 && strcasecmp($colB, 'Uraian') === 0) {
                $headerRow = $r;
                break;
            }

            if (strcasecmp($colA, 'Program') === 0) {
                $programName = $colB;
            } elseif (strcasecmp($colA, 'Kegiatan') === 0 && $colB !== '') {
                $activityName = $colB;
            } elseif (strcasecmp($colA, 'Lokasi Kegiatan') === 0 && $colB !== '') {
                $location = $colB;
            } elseif (strcasecmp($colA, 'Indikator') === 0) {
                $inIndicatorBlock = true;
            } elseif ($inIndicatorBlock) {
                if ($colA === '' && $colB === '') {
                    $inIndicatorBlock = false;
                } else {
                    $targetVal = $this->readScalarCell($sheet->getCell("F{$r}"), $warnings, "{$sheetName}!F{$r}");
                    $unitVal = trim((string) $this->readScalarCell($sheet->getCell("G{$r}"), $warnings, "{$sheetName}!G{$r}"));
                    $indicatorMatrix[] = [
                        'indicator' => $colA,
                        'benchmark' => $colB,
                        'target' => $targetVal,
                        'unit' => $unitVal,
                    ];
                }
            }
        }

        if ($headerRow === null) {
            $fatalErrors[] = "Header tabel (Kode Rekening | Uraian) tidak ditemukan pada worksheet '{$sheetName}'.";

            return [
                'sheet_name' => $sheetName,
                'group_code' => $groupCode,
                'program_name' => $programName ?: $sheetName,
                'activity_name' => $activityName,
                'location' => $location,
                'indicator_matrix' => $indicatorMatrix,
                'items' => [],
                'parsed_total_proposed' => 0.0,
                'parsed_total_approved' => 0.0,
                'sheet_grand_total_proposed' => null,
                'sheet_grand_total_approved' => null,
            ];
        }

        $items = [];
        $currentDetailCode = null;
        $seqPerAccount = [];
        $parsedTotalProposed = 0.0;
        $parsedTotalApproved = 0.0;
        $sheetGrandProposed = null;
        $sheetGrandApproved = null;

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $rawA = trim((string) $this->readScalarCell($sheet->getCell("A{$r}"), $warnings, "{$sheetName}!A{$r}"));
            $rawB = trim((string) $this->readScalarCell($sheet->getCell("B{$r}"), $warnings, "{$sheetName}!B{$r}"));

            // Lewati baris penomoran kolom (1 | 2 | 3 | 4 | 5 ...)
            if ($rawA === '1' && $rawB === '2') {
                continue;
            }

            // Berhenti saat mencapai blok pengesahan
            if (
                strcasecmp($rawB, 'Diusulkan oleh,') === 0
                || str_starts_with(mb_strtolower($rawB), 'bangkinang,')
            ) {
                break;
            }

            // Tangkap baris JUMLAH BELANJA ... untuk rekonsiliasi
            if (str_starts_with(strtoupper($rawA), 'JUMLAH')) {
                $sheetGrandProposed = $this->readNumericCell($sheet->getCell("F{$r}"), $warnings, "{$sheetName}!F{$r}");
                $sheetGrandApproved = $this->readNumericCell($sheet->getCell("H{$r}"), $warnings, "{$sheetName}!H{$r}");
                break;
            }

            if ($rawA === '' && $rawB === '') {
                continue;
            }

            if (str_starts_with(mb_strtolower($rawB), 'tidak ada usulan')) {
                continue;
            }

            $volUsulan = $this->readNumericCell($sheet->getCell("C{$r}"), $warnings, "{$sheetName}!C{$r}");
            $satuan = trim((string) $this->readScalarCell($sheet->getCell("D{$r}"), $warnings, "{$sheetName}!D{$r}"));
            $hargaUsulan = $this->readNumericCell($sheet->getCell("E{$r}"), $warnings, "{$sheetName}!E{$r}");
            $nilaiUsulan = $this->readNumericCell($sheet->getCell("F{$r}"), $warnings, "{$sheetName}!F{$r}");
            $volDisetujui = $this->readNumericCell($sheet->getCell("G{$r}"), $warnings, "{$sheetName}!G{$r}");
            $nilaiDisetujui = $this->readNumericCell($sheet->getCell("H{$r}"), $warnings, "{$sheetName}!H{$r}");

            // Identifikasi apakah baris adalah header hierarki rekening
            $isAccountHeaderRow = $rawA !== '' && preg_match('/^\d+(?:\.\d+)*$/', $rawA);

            if ($isAccountHeaderRow && $volUsulan === null && $hargaUsulan === null && $satuan === '') {
                $segments = substr_count($rawA, '.') + 1;
                // Rekening detail di SIMRKA umumnya 4 segmen (mis. 5.2.1.01) atau terdaftar sebagai Account level >= 3
                $matchedAcc = $accountsByCode->get($rawA);
                if ($segments >= 4 || ($matchedAcc && (int) $matchedAcc->level >= 3)) {
                    $currentDetailCode = $rawA;
                }

                continue;
            }

            // Jika kode rekening dituliskan langsung pada baris item
            if ($isAccountHeaderRow && ($volUsulan !== null || $hargaUsulan !== null)) {
                $currentDetailCode = $rawA;
            }

            // Pastikan benar-benar baris item belanja (memiliki uraian dan komponen kuantitas/harga)
            if ($rawB === '' || ($volUsulan === null && $hargaUsulan === null && $nilaiUsulan === null)) {
                continue;
            }

            $accountCode = $currentDetailCode;
            $account = $accountCode ? $accountsByCode->get($accountCode) : null;

            $validationStatus = 'SESUAI';
            $validationNotes = [];

            if (! $accountCode) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = 'Item tidak berada di bawah kode rekening detail yang valid.';
                $fatalErrors[] = "{$sheetName} baris {$r} ('{$rawB}'): tidak memiliki kode rekening detail.";
            } elseif (! $account) {
                $validationStatus = 'TIDAK DITEMUKAN';
                $validationNotes[] = "Kode rekening '{$accountCode}' tidak ditemukan pada master Account.";
                $fatalErrors[] = "{$sheetName} baris {$r} ('{$rawB}'): kode rekening '{$accountCode}' tidak ditemukan di master Account.";
            }

            $propVol = $volUsulan ?? 0.0;
            $propPrice = $hargaUsulan ?? 0.0;
            $propTotal = $nilaiUsulan ?? round($propVol * $propPrice, 2);

            if ($nilaiUsulan !== null && $volUsulan !== null && $hargaUsulan !== null) {
                $expectedUsulan = round($volUsulan * $hargaUsulan, 2);
                if (abs($expectedUsulan - round($nilaiUsulan, 2)) >= 1.0) {
                    $validationStatus = $validationStatus === 'SESUAI' ? 'BERBEDA' : $validationStatus;
                    $validationNotes[] = "Nilai usulan ({$nilaiUsulan}) berbeda dari Vol × Harga ({$expectedUsulan}).";
                    $warnings[] = "{$sheetName} baris {$r}: nilai usulan berbeda dengan perkalian volume × harga satuan.";
                }
            }

            // Nilai disetujui: jika cached value H null, tandai sebagai fatal error / warning tidak dapat diverifikasi
            if ($nilaiDisetujui === null) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = 'Cached value Jumlah Disetujui (kolom H) tidak tersedia.';
                $fatalErrors[] = "{$sheetName} baris {$r} ('{$rawB}'): nilai disetujui (kolom H) tidak memiliki cached value.";
                $appTotal = 0.0;
            } else {
                $appTotal = round($nilaiDisetujui, 2);
            }

            // Volume disetujui: JANGAN menganggap kosong sama dengan volume usulan
            $appVol = $volDisetujui;
            $appUnitPrice = ($appVol !== null && $appVol > 0 && $appTotal > 0)
                ? round($appTotal / $appVol, 2)
                : null;

            if ($appTotal <= 0 && $propTotal > 0) {
                $itemStatus = RkaItem::STATUS_REJECTED;
            } elseif ($appTotal < $propTotal) {
                $itemStatus = 'TRIMMED';
            } else {
                $itemStatus = RkaItem::STATUS_APPROVED;
            }

            $refAccountKey = $accountCode ?? 'UNKNOWN';
            $seqPerAccount[$refAccountKey] = ($seqPerAccount[$refAccountKey] ?? 0) + 1;
            $sourceRefId = "NATIVE:{$groupCode}:{$refAccountKey}:{$seqPerAccount[$refAccountKey]}";

            $parsedTotalProposed += $propTotal;
            $parsedTotalApproved += $appTotal;

            $items[] = [
                'row_number' => $r,
                'source_ref_id' => $sourceRefId,
                'sheet_name' => $sheetName,
                'group_code' => $groupCode,
                'kelompok_belanja' => $groupCode.' '.$sheetName,
                'program_kegiatan' => trim($programName.' - '.$activityName, ' -'),
                'kode_rekening' => $accountCode ?? '-',
                'nama_rekening' => $account?->name ?? '-',
                'account_id' => $account?->id,
                'mapped_account_name' => $account?->name,
                'group_header' => $account?->parent?->name ?? $sheetName,
                'uraian_item' => $rawB,
                'vol_usulan' => $propVol,
                'satuan' => $satuan !== '' ? $satuan : 'Unit',
                'harga_satuan_usulan' => $propPrice,
                'nilai_usulan' => $propTotal,
                'vol_disetujui' => $appVol,
                'harga_satuan_disetujui' => $appUnitPrice,
                'nilai_disetujui' => $appTotal,
                'status_item' => $itemStatus,
                'asal_item' => 'USULAN_ASLI',
                'keterangan_review' => $appVol !== null && $appVol < $propVol
                    ? "Volume disesuaikan dari {$propVol} menjadi {$appVol}"
                    : '',
                'validation_status' => $validationStatus,
                'validation_message' => implode(' ', $validationNotes),
            ];
        }

        return [
            'sheet_name' => $sheetName,
            'group_code' => $groupCode,
            'program_name' => $programName !== '' ? $programName : "Program {$sheetName}",
            'activity_name' => $activityName,
            'location' => $location,
            'indicator_matrix' => $indicatorMatrix,
            'items' => $items,
            'parsed_total_proposed' => round($parsedTotalProposed, 2),
            'parsed_total_approved' => round($parsedTotalApproved, 2),
            'sheet_grand_total_proposed' => $sheetGrandProposed !== null ? round($sheetGrandProposed, 2) : null,
            'sheet_grand_total_approved' => $sheetGrandApproved !== null ? round($sheetGrandApproved, 2) : null,
        ];
    }

    /**
     * @param  Collection<string, Account>  $accountsByCode
     * @param  Collection<int, Department>  $departments
     * @param  array<int, array<string, mixed>>  $allItems
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $fatalErrors
     * @return array<int, array<string, mixed>>
     */
    private function parseDaftarPenyesuaian(
        Worksheet $sheet,
        Collection $accountsByCode,
        Collection $departments,
        array $allItems,
        array &$warnings,
        array &$fatalErrors,
    ): array {
        $highestRow = $sheet->getHighestRow();
        $highestColIndex = max(7, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
        $rows = [];
        $currentSection = null;
        $sectionColumnMaps = [
            RkaAdjustment::SECTION_A => [],
            RkaAdjustment::SECTION_B => [],
        ];
        $sectionAmbiguousHeaders = [
            RkaAdjustment::SECTION_A => [],
            RkaAdjustment::SECTION_B => [],
        ];
        $seqA = 0;
        $seqB = 0;
        $itemsCollection = collect($allItems);

        for ($r = 1; $r <= $highestRow; $r++) {
            $firstCol = trim((string) $this->readScalarCell($sheet->getCell("A{$r}"), $warnings, "DAFTAR PENYESUAIAN!A{$r}"));
            $upperFirstCol = strtoupper($firstCol);

            if (str_starts_with($upperFirstCol, 'A. ')) {
                $currentSection = RkaAdjustment::SECTION_A;

                continue;
            }
            if (str_starts_with($upperFirstCol, 'B. ')) {
                $currentSection = RkaAdjustment::SECTION_B;

                continue;
            }

            $detectedHeader = $this->detectDaftarPenyesuaianHeaderMap($sheet, $r, $highestColIndex, $warnings);
            if ($detectedHeader !== null) {
                $detectedSection = $detectedHeader['section'] ?? $currentSection ?? RkaAdjustment::SECTION_A;
                $currentSection = $detectedSection;
                $sectionColumnMaps[$currentSection] = $detectedHeader['map'];
                $sectionAmbiguousHeaders[$currentSection] = $detectedHeader['ambiguous'];

                if (! empty($detectedHeader['ambiguous'])) {
                    $ambigList = implode(', ', $detectedHeader['ambiguous']);
                    $msg = "DAFTAR PENYESUAIAN baris {$r} (Section {$currentSection}): Header kolom ambigu ({$ambigList}).";
                    $warnings[] = $msg;
                    $fatalErrors[] = $msg;
                }

                continue;
            }

            $activeMap = $currentSection !== null ? ($sectionColumnMaps[$currentSection] ?? []) : [];
            $noCol = $activeMap['no'] ?? 'A';
            $kodeCol = $activeMap['kode'] ?? 'B';
            $uraianCol = $activeMap['uraian'] ?? 'C';

            $colNo = trim((string) $this->readScalarCell($sheet->getCell("{$noCol}{$r}"), $warnings, "DAFTAR PENYESUAIAN!{$noCol}{$r}"));
            $colKode = trim((string) $this->readScalarCell($sheet->getCell("{$kodeCol}{$r}"), $warnings, "DAFTAR PENYESUAIAN!{$kodeCol}{$r}"));
            $colUraian = trim((string) $this->readScalarCell($sheet->getCell("{$uraianCol}{$r}"), $warnings, "DAFTAR PENYESUAIAN!{$uraianCol}{$r}"));

            // Skip pesan kosong & baris JUMLAH
            if (
                $currentSection === null
                || strcasecmp($colKode, 'JUMLAH') === 0
                || strcasecmp($colNo, 'JUMLAH') === 0
                || strcasecmp($firstCol, 'JUMLAH') === 0
                || str_starts_with(mb_strtolower($firstCol), 'tidak ada ')
                || ($colNo === '' && $colKode === '' && $colUraian === '')
            ) {
                continue;
            }

            // Validasi jika header wajib pada section ambigu
            $ambiguousHeaders = $sectionAmbiguousHeaders[$currentSection] ?? [];
            $account = $colKode !== '' ? $accountsByCode->get($colKode) : null;

            $readTextByKey = function (string $key) use ($sheet, $activeMap, $r, &$warnings): string {
                if (! isset($activeMap[$key])) {
                    return '';
                }
                $col = $activeMap[$key];

                return trim((string) $this->readScalarCell($sheet->getCell("{$col}{$r}"), $warnings, "DAFTAR PENYESUAIAN!{$col}{$r}"));
            };

            if ($currentSection === RkaAdjustment::SECTION_A) {
                $seqA++;
                $rawVolume = $readTextByKey('volume');
                $volInfo = $this->parseVolumeAndUnit($rawVolume);

                $jumlahUsulan = isset($activeMap['jumlah_usulan'])
                    ? ($this->readNumericCell($sheet->getCell("{$activeMap['jumlah_usulan']}{$r}"), $warnings, "DAFTAR PENYESUAIAN!{$activeMap['jumlah_usulan']}{$r}") ?? 0.0)
                    : 0.0;
                $jumlahDisetujui = isset($activeMap['jumlah_disetujui'])
                    ? ($this->readNumericCell($sheet->getCell("{$activeMap['jumlah_disetujui']}{$r}"), $warnings, "DAFTAR PENYESUAIAN!{$activeMap['jumlah_disetujui']}{$r}") ?? 0.0)
                    : 0.0;

                $statusText = $readTextByKey('status');
                $notes = $readTextByKey('keterangan');
                $rawUnitTujuan = $readTextByKey('unit_tujuan');

                // Pencocokan item berlapis (hanya MATCH_UNIQUE yang membentuk rka_item_id)
                $normDesc = mb_strtolower(preg_replace('/\s+/', ' ', $colUraian) ?? $colUraian);
                $matchedItems = $itemsCollection->filter(function (array $item) use ($colKode, $normDesc, $jumlahUsulan): bool {
                    $itemDesc = mb_strtolower(preg_replace('/\s+/', ' ', (string) $item['uraian_item']) ?? '');

                    return $item['kode_rekening'] === $colKode
                        && $itemDesc === $normDesc
                        && abs((float) $item['nilai_usulan'] - round($jumlahUsulan, 2)) < 1.0;
                })->values();

                $originItemRef = null;
                $itemMatchStatus = 'MATCH_NONE';
                $matchedItemDesc = null;
                if ($matchedItems->count() === 1) {
                    $originItemRef = $matchedItems->first()['source_ref_id'];
                    $itemMatchStatus = 'MATCH_UNIQUE';
                    $matchedItemDesc = $matchedItems->first()['uraian_item'];
                } elseif ($matchedItems->count() > 1) {
                    $itemMatchStatus = 'MATCH_AMBIGUOUS';
                }

                $actionTypeA = $this->mapAdjustmentActionType($statusText);
                $requiresDestUnit = $actionTypeA === RkaAdjustment::ACTION_MOVED;

                $destDeptId = null;
                $destDeptName = null;
                $destDeptCode = null;
                $unitSource = null;

                if (! empty($ambiguousHeaders)) {
                    $relationStatus = 'HEADER AMBIGU';
                    $validationStatus = 'TIDAK VALID';
                    $validationMsg = 'Header kolom ambigu pada Section A: '.implode(', ', $ambiguousHeaders).'.';
                } elseif ($colUraian === '') {
                    $relationStatus = 'TIDAK VALID';
                    $validationStatus = 'TIDAK VALID';
                    $validationMsg = 'Uraian usulan kosong.';
                } elseif ($requiresDestUnit) {
                    if ($rawUnitTujuan !== '' && $rawUnitTujuan !== '-') {
                        $unitSource = 'EXCEL';
                        $resolvedDest = $this->departmentResolver->resolve($rawUnitTujuan, $departments);
                        if ($resolvedDest['status'] === DepartmentResolver::STATUS_RESOLVED && $resolvedDest['department']) {
                            $destDeptId = $resolvedDest['department']->id;
                            $destDeptName = $resolvedDest['department']->name;
                            $destDeptCode = $resolvedDest['department']->code;
                            $relationStatus = 'UNIT TELAH DITENTUKAN';
                            $validationStatus = 'SESUAI';
                            $validationMsg = "Unit tujuan dari Excel ({$rawUnitTujuan}) dipetakan ke {$destDeptName} ({$destDeptCode}).";
                        } elseif ($resolvedDest['status'] === DepartmentResolver::STATUS_AMBIGUOUS) {
                            $relationStatus = 'UNIT AMBIGU';
                            $validationStatus = 'PERLU PERHATIAN';
                            $validationMsg = $resolvedDest['reason'] ?? "Unit Tujuan '{$rawUnitTujuan}' ambigu.";
                            $warnings[] = "DAFTAR PENYESUAIAN baris {$r}: {$validationMsg}";
                        } else {
                            $relationStatus = 'UNIT TIDAK VALID';
                            $validationStatus = 'PERLU PERHATIAN';
                            $validationMsg = $resolvedDest['reason'] ?? "Unit Tujuan '{$rawUnitTujuan}' tidak ditemukan.";
                            $warnings[] = "DAFTAR PENYESUAIAN baris {$r}: {$validationMsg}";
                        }
                    } else {
                        $relationStatus = 'BELUM DITENTUKAN';
                        $validationStatus = 'PERLU PERHATIAN';
                        $validationMsg = 'Silakan tentukan Unit Tujuan sebelum finalisasi import.';
                    }
                } else {
                    $unitSource = ($rawUnitTujuan !== '' && $rawUnitTujuan !== '-') ? 'EXCEL' : null;
                    $relationStatus = 'TIDAK MEMERLUKAN UNIT';
                    $validationStatus = 'SESUAI';
                    $validationMsg = 'Tidak memerlukan unit tujuan.';
                }

                $rows[] = [
                    'row_number' => $r,
                    'adj_ref_id' => "NATIVE:ADJ:A:{$seqA}",
                    'original_no' => $colNo,
                    'section' => RkaAdjustment::SECTION_A,
                    'action_type' => $actionTypeA,
                    'status_penyesuaian' => $statusText !== '' ? $statusText : 'PENYESUAIAN',
                    'kode_rekening' => $colKode,
                    'nama_rekening' => $account?->name ?? '-',
                    'account_id' => $account?->id,
                    'uraian' => $colUraian,
                    'unit_asal_excel' => null,
                    'unit_tujuan_excel' => $rawUnitTujuan !== '' ? $rawUnitTujuan : null,
                    'unit_asal' => null,
                    'unit_tujuan' => $destDeptName,
                    'unit_source' => $unitSource,
                    'destination_department_id' => $destDeptId,
                    'destination_unit_name' => $destDeptName,
                    'destination_unit_code' => $destDeptCode,
                    'target_department_id' => null,
                    'target_unit_name' => null,
                    'target_unit_code' => null,
                    'origin_item_id' => $originItemRef,
                    'imported_item_id' => null,
                    'item_match_status' => $itemMatchStatus,
                    'matched_item_description' => $matchedItemDesc,
                    'relation_status' => $relationStatus,
                    'volume' => $volInfo['volume'],
                    'satuan' => $volInfo['unit'],
                    'harga_satuan' => $volInfo['volume'] > 0 ? round($jumlahUsulan / $volInfo['volume'], 2) : $jumlahUsulan,
                    'jumlah_usulan' => round($jumlahUsulan, 2),
                    'jumlah_disetujui' => round($jumlahDisetujui, 2),
                    'keterangan_reviewer' => $notes,
                    'validation_status' => $validationStatus,
                    'validation_message' => $validationMsg,
                ];
            } else {
                $seqB++;
                $usulanCol = $activeMap['jumlah_usulan'] ?? null;
                $disetujuiCol = $activeMap['jumlah_disetujui'] ?? null;

                $eCell = $usulanCol !== null ? $sheet->getCell("{$usulanCol}{$r}") : null;
                $jumlahUsulan = $usulanCol !== null
                    ? ($this->readNumericCell($eCell, $warnings, "DAFTAR PENYESUAIAN!{$usulanCol}{$r}") ?? 0.0)
                    : 0.0;
                $jumlahDisetujui = $disetujuiCol !== null
                    ? ($this->readNumericCell($sheet->getCell("{$disetujuiCol}{$r}"), $warnings, "DAFTAR PENYESUAIAN!{$disetujuiCol}{$r}") ?? 0.0)
                    : 0.0;

                $rawVolume = $readTextByKey('volume');
                $volInfo = $rawVolume !== '' ? $this->parseVolumeAndUnit($rawVolume) : ['volume' => 1.0, 'unit' => 'Paket'];
                $notes = $readTextByKey('keterangan');
                $statusText = $readTextByKey('status');
                $rawUnitAsal = $readTextByKey('unit_asal');

                // Pemetaan Unit Asal Section B -> target_unit_id (JANGAN simpan ke destination_unit_id)
                $targetDeptId = null;
                $targetDeptName = null;
                $targetDeptCode = null;
                $unitSource = null;

                if (! empty($ambiguousHeaders)) {
                    $relationStatus = 'HEADER AMBIGU';
                    $validationStatus = 'TIDAK VALID';
                    $validationMsg = 'Header kolom ambigu pada Section B: '.implode(', ', $ambiguousHeaders).'.';
                } else {
                    $resolvedDept = $this->departmentResolver->resolve($rawUnitAsal, $departments);
                    if ($resolvedDept['status'] === DepartmentResolver::STATUS_RESOLVED && $resolvedDept['department']) {
                        $targetDeptId = $resolvedDept['department']->id;
                        $targetDeptName = $resolvedDept['department']->name;
                        $targetDeptCode = $resolvedDept['department']->code;
                        $unitSource = 'EXCEL';
                        $relationStatus = 'UNIT TELAH DITENTUKAN';
                        $validationStatus = 'SESUAI';
                        $validationMsg = "Unit asal dari Excel ({$rawUnitAsal}) dipetakan ke {$targetDeptName} ({$targetDeptCode}).";
                    } elseif ($resolvedDept['status'] === DepartmentResolver::STATUS_INVALID_INPUT) {
                        $relationStatus = 'BELUM DITENTUKAN';
                        $validationStatus = 'PERLU PERHATIAN';
                        $validationMsg = 'Silakan tentukan Unit Asal/Sumber sebelum finalisasi import.';
                    } elseif ($resolvedDept['status'] === DepartmentResolver::STATUS_AMBIGUOUS) {
                        $unitSource = 'EXCEL';
                        $relationStatus = 'UNIT AMBIGU';
                        $validationStatus = 'PERLU PERHATIAN';
                        $validationMsg = $resolvedDept['reason'] ?? "Unit Asal '{$rawUnitAsal}' ambigu.";
                        $warnings[] = "DAFTAR PENYESUAIAN baris {$r}: {$validationMsg}";
                    } else {
                        $unitSource = 'EXCEL';
                        $relationStatus = 'UNIT TIDAK VALID';
                        $validationStatus = 'PERLU PERHATIAN';
                        $validationMsg = $resolvedDept['reason'] ?? "Unit Asal '{$rawUnitAsal}' gagal dipetakan ke Department.";
                        $warnings[] = "DAFTAR PENYESUAIAN baris {$r}: {$validationMsg}";
                    }
                }

                // Pencocokan item operasional Section B melalui formula referensi sheet (mis. ='Belanja Barang dan Jasa'!F36)
                $importedItemRef = null;
                $itemMatchStatus = 'MATCH_NONE';
                $matchedItemDesc = null;

                if ($eCell !== null && $eCell->isFormula() && preg_match("/^='?([^'!]+)'?!\\$?[A-Z]+\\$?(\d+)$/", (string) $eCell->getValue(), $refMatch)) {
                    $refSheet = $refMatch[1];
                    $refRow = (int) $refMatch[2];
                    $matchedItems = $itemsCollection->filter(
                        fn (array $item): bool => $item['sheet_name'] === $refSheet && (int) $item['row_number'] === $refRow
                    )->values();

                    if ($matchedItems->count() === 1) {
                        $importedItemRef = $matchedItems->first()['source_ref_id'];
                        $itemMatchStatus = 'MATCH_UNIQUE';
                        $matchedItemDesc = $matchedItems->first()['uraian_item'];
                    } elseif ($matchedItems->count() > 1) {
                        $itemMatchStatus = 'MATCH_AMBIGUOUS';
                    }
                }

                // Fallback deterministik: cocokkan kode rekening + uraian + nominal unik pada BELANJA unit pengelola
                if ($importedItemRef === null && $itemMatchStatus === 'MATCH_NONE' && $colKode !== '' && $colUraian !== '') {
                    $normDescB = mb_strtolower(preg_replace('/\s+/', ' ', $colUraian) ?? $colUraian);
                    $matchedByContent = $itemsCollection->filter(function (array $item) use ($colKode, $normDescB, $jumlahUsulan, $jumlahDisetujui): bool {
                        $itemDesc = mb_strtolower(preg_replace('/\s+/', ' ', (string) $item['uraian_item']) ?? '');

                        return $item['kode_rekening'] === $colKode
                            && $itemDesc === $normDescB
                            && (
                                abs((float) $item['nilai_usulan'] - round($jumlahUsulan, 2)) < 1.0
                                || abs((float) $item['nilai_disetujui'] - round($jumlahDisetujui, 2)) < 1.0
                            );
                    })->values();

                    if ($matchedByContent->count() === 1) {
                        $importedItemRef = $matchedByContent->first()['source_ref_id'];
                        $itemMatchStatus = 'MATCH_UNIQUE';
                        $matchedItemDesc = $matchedByContent->first()['uraian_item'];
                    } elseif ($matchedByContent->count() > 1) {
                        $itemMatchStatus = 'MATCH_AMBIGUOUS';
                    }
                }

                $actionTypeB = $this->mapSectionBActionType($statusText, $notes);

                $rows[] = [
                    'row_number' => $r,
                    'adj_ref_id' => "NATIVE:ADJ:B:{$seqB}",
                    'original_no' => $colNo,
                    'section' => RkaAdjustment::SECTION_B,
                    'action_type' => $actionTypeB,
                    'status_penyesuaian' => $statusText !== '' ? $statusText : 'SHARED_COST / TAMBAHAN REVIEW',
                    'kode_rekening' => $colKode,
                    'nama_rekening' => $account?->name ?? '-',
                    'account_id' => $account?->id,
                    'uraian' => $colUraian,
                    'unit_asal_excel' => $rawUnitAsal !== '' ? $rawUnitAsal : null,
                    'unit_tujuan_excel' => null,
                    'unit_asal' => $rawUnitAsal !== '' ? $rawUnitAsal : null,
                    'unit_tujuan' => null,
                    'unit_source' => $unitSource,
                    'destination_department_id' => null,
                    'destination_unit_name' => null,
                    'destination_unit_code' => null,
                    'target_department_id' => $targetDeptId,
                    'target_unit_name' => $targetDeptName,
                    'target_unit_code' => $targetDeptCode,
                    'origin_item_id' => null,
                    'imported_item_id' => $importedItemRef,
                    'item_match_status' => $itemMatchStatus,
                    'matched_item_description' => $matchedItemDesc,
                    'relation_status' => $relationStatus,
                    'volume' => $volInfo['volume'],
                    'satuan' => $volInfo['unit'],
                    'harga_satuan' => $volInfo['volume'] > 0 ? round($jumlahUsulan / $volInfo['volume'], 2) : $jumlahUsulan,
                    'jumlah_usulan' => round($jumlahUsulan, 2),
                    'jumlah_disetujui' => round($jumlahDisetujui, 2),
                    'keterangan_reviewer' => $notes,
                    'validation_status' => $validationStatus,
                    'validation_message' => $validationMsg,
                ];
            }
        }

        if (count($rows) > 0) {
            $warnings[] = 'Terdapat '.count($rows).' baris pada DAFTAR PENYESUAIAN; relasi unit dan item hanya dibentuk apabila sumber terverifikasi secara unik.';
        }

        return $rows;
    }

    /**
     * Mendeteksi baris header dinamis pada worksheet DAFTAR PENYESUAIAN berdasarkan nama kolom ternormalisasi.
     *
     * @param  array<int, string>  $warnings
     * @return array{section: ?string, map: array<string, string>, ambiguous: array<int, string>}|null
     */
    private function detectDaftarPenyesuaianHeaderMap(Worksheet $sheet, int $row, int $highestColIndex, array &$warnings): ?array
    {
        $rawHeaders = [];
        for ($colIdx = 1; $colIdx <= $highestColIndex; $colIdx++) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $val = trim((string) $this->readScalarCell($sheet->getCell("{$colLetter}{$row}"), $warnings, "DAFTAR PENYESUAIAN!{$colLetter}{$row}"));
            if ($val !== '') {
                $rawHeaders[$colLetter] = $val;
            }
        }

        if (count($rawHeaders) < 3) {
            return null;
        }

        $mapped = [];
        $ambiguous = [];

        foreach ($rawHeaders as $colLetter => $headerText) {
            $canonical = $this->normalizeDaftarPenyesuaianHeader($headerText);
            if ($canonical === null) {
                continue;
            }

            if (isset($mapped[$canonical])) {
                $ambiguous[] = $canonical;
            } else {
                $mapped[$canonical] = $colLetter;
            }
        }

        if (! isset($mapped['no']) || (! isset($mapped['kode']) && ! isset($mapped['uraian']))) {
            return null;
        }

        $section = null;
        if (isset($mapped['unit_asal']) || isset($mapped['jumlah_disetujui'])) {
            $section = RkaAdjustment::SECTION_B;
        } elseif (isset($mapped['unit_tujuan']) || isset($mapped['status']) || isset($mapped['volume'])) {
            $section = RkaAdjustment::SECTION_A;
        }

        return [
            'section' => $section,
            'map' => $mapped,
            'ambiguous' => array_values(array_unique($ambiguous)),
        ];
    }

    private function normalizeDaftarPenyesuaianHeader(string $headerText): ?string
    {
        $clean = mb_strtolower(trim($headerText));
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);
        $cleanNoUnit = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $clean));

        return match (true) {
            $cleanNoUnit === 'no' || $cleanNoUnit === 'nomor' => 'no',
            $cleanNoUnit === 'kode' || $cleanNoUnit === 'kode rekening' => 'kode',
            $cleanNoUnit === 'uraian' || $cleanNoUnit === 'uraian usulan' || $cleanNoUnit === 'uraian item' => 'uraian',
            $cleanNoUnit === 'volume' => 'volume',
            $cleanNoUnit === 'unit tujuan' => 'unit_tujuan',
            in_array($cleanNoUnit, ['unit asal', 'unit asal/sumber', 'unit asal / sumber', 'unit sumber'], true) => 'unit_asal',
            $cleanNoUnit === 'jumlah usulan' => 'jumlah_usulan',
            $cleanNoUnit === 'jumlah disetujui' => 'jumlah_disetujui',
            $cleanNoUnit === 'status' => 'status',
            in_array($cleanNoUnit, ['keterangan', 'keterangan / dianggarkan di', 'dianggarkan di'], true) => 'keterangan',
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function parseRingkasan(Worksheet $sheet, array &$warnings): array
    {
        $highestRow = $sheet->getHighestRow();
        $usulanAwalUnit = null;
        $usulanAwalSource = null;
        $usulanAwalLabel = null;
        $usulanDibahas = null;
        $disetujui = null;
        $groups = [];

        for ($r = 1; $r <= $highestRow; $r++) {
            $colA = trim((string) $this->readScalarCell($sheet->getCell("A{$r}"), $warnings, "RINGKASAN!A{$r}"));
            $colB = trim((string) $this->readScalarCell($sheet->getCell("B{$r}"), $warnings, "RINGKASAN!B{$r}"));
            $normB = trim((string) preg_replace('/\s+/', ' ', mb_strtolower($colB)));

            if ($normB === 'usulan awal unit' || str_starts_with($normB, 'usulan awal unit')) {
                $valC = $this->readNumericCell($sheet->getCell("C{$r}"), $warnings, "RINGKASAN!C{$r}");
                if ($valC !== null) {
                    $usulanAwalUnit = $valC;
                    $usulanAwalSource = "RINGKASAN!C{$r}";
                    $usulanAwalLabel = $colB;
                } else {
                    $valD = $this->readNumericCell($sheet->getCell("D{$r}"), $warnings, "RINGKASAN!D{$r}");
                    if ($valD !== null) {
                        $usulanAwalUnit = $valD;
                        $usulanAwalSource = "RINGKASAN!D{$r}";
                        $usulanAwalLabel = $colB;
                    }
                }
            } elseif (str_starts_with($normB, 'usulan yang dibahas')) {
                $usulanDibahas = $this->readNumericCell($sheet->getCell("C{$r}"), $warnings, "RINGKASAN!C{$r}");
            } elseif (str_starts_with($normB, 'disetujui setelah review')) {
                $disetujui = $this->readNumericCell($sheet->getCell("C{$r}"), $warnings, "RINGKASAN!C{$r}");
            } elseif (in_array($colA, ['5.1', '5.2', '5.3', '5.4', '5.5', '2.1'], true)) {
                $groups[$colA] = [
                    'label' => $colB,
                    'proposed' => $this->readNumericCell($sheet->getCell("C{$r}"), $warnings, "RINGKASAN!C{$r}"),
                    'approved' => $this->readNumericCell($sheet->getCell("D{$r}"), $warnings, "RINGKASAN!D{$r}"),
                ];
            }
        }

        return [
            'usulan_awal_unit' => $usulanAwalUnit !== null ? round($usulanAwalUnit, 2) : null,
            'usulan_awal_unit_source' => $usulanAwalSource,
            'usulan_awal_unit_label' => $usulanAwalLabel,
            'usulan_dibahas' => $usulanDibahas !== null ? round($usulanDibahas, 2) : null,
            'disetujui' => $disetujui !== null ? round($disetujui, 2) : null,
            'groups' => $groups,
        ];
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function parseRekap(Worksheet $sheet, array &$warnings): array
    {
        $highestRow = $sheet->getHighestRow();
        $totalProposed = null;
        $totalApproved = null;
        $paguUnit = null;
        $statusUsulan = null;
        $statusReview = null;

        for ($r = 1; $r <= $highestRow; $r++) {
            $colA = trim((string) $this->readScalarCell($sheet->getCell("A{$r}"), $warnings, "REKAP!A{$r}"));
            $colB = trim((string) $this->readScalarCell($sheet->getCell("B{$r}"), $warnings, "REKAP!B{$r}"));

            if (str_starts_with(strtoupper($colA), 'TOTAL USULAN')) {
                $totalProposed = $this->readNumericCell($sheet->getCell("C{$r}"), $warnings, "REKAP!C{$r}");
                $totalApproved = $this->readNumericCell($sheet->getCell("D{$r}"), $warnings, "REKAP!D{$r}");
            } elseif (strcasecmp($colB, 'Pagu anggaran unit') === 0) {
                $paguUnit = $this->readNumericCell($sheet->getCell("C{$r}"), $warnings, "REKAP!C{$r}");
            } elseif (strcasecmp($colB, 'Status') === 0) {
                $statusUsulan = trim((string) $this->readScalarCell($sheet->getCell("C{$r}"), $warnings, "REKAP!C{$r}"));
                $statusReview = trim((string) $this->readScalarCell($sheet->getCell("D{$r}"), $warnings, "REKAP!D{$r}"));
            }
        }

        return [
            'total_proposed' => $totalProposed !== null ? round($totalProposed, 2) : null,
            'total_approved' => $totalApproved !== null ? round($totalApproved, 2) : null,
            'pagu_unit' => $paguUnit !== null ? round($paguUnit, 2) : null,
            'status_usulan' => $statusUsulan,
            'status_review' => $statusReview,
        ];
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function parseBeritaAcara(Worksheet $sheet, array &$warnings): array
    {
        $highestRow = $sheet->getHighestRow();
        $nomor = null;
        $pembuka = null;
        $hasPlaceholder = false;

        for ($r = 1; $r <= $highestRow; $r++) {
            $colA = trim((string) $this->readScalarCell($sheet->getCell("A{$r}"), $warnings, "BERITA ACARA!A{$r}"));
            if ($colA === '') {
                continue;
            }
            if (str_contains($colA, '[....]') || str_contains($colA, '........')) {
                $hasPlaceholder = true;
            }
            if (str_starts_with(mb_strtolower($colA), 'nomor:')) {
                $nomor = $colA;
            } elseif (str_starts_with(mb_strtolower($colA), 'pada hari ini')) {
                $pembuka = $colA;
            }
        }

        if ($hasPlaceholder) {
            $warnings[] = 'Worksheet BERITA ACARA masih mengandung placeholder ([....]) — dokumen belum memuat nomor/tanggal pengesahan final.';
        }

        return [
            'nomor' => $nomor,
            'pembuka' => $pembuka,
            'has_placeholder' => $hasPlaceholder,
        ];
    }

    /**
     * Rekonsiliasi menyeluruh antara item hasil parsing, subtotal sheet, RINGKASAN, dan REKAP.
     *
     * @param  array<string, array<string, mixed>>  $belanjaSheetsData
     * @param  array<string, mixed>  $ringkasan
     * @param  array<string, mixed>  $rekap
     * @param  array<string, mixed>  $identitas
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $fatalErrors
     * @return array<int, array<string, mixed>>
     */
    private function reconcile(
        array $belanjaSheetsData,
        float $totalProposedAll,
        float $totalApprovedAll,
        array $ringkasan,
        array $rekap,
        array $identitas,
        array &$warnings,
        array &$fatalErrors,
    ): array {
        $checks = [];

        // 1. Perbandingan tiap worksheet Belanja dengan Grand Total sheet masing-masing
        foreach ($belanjaSheetsData as $groupCode => $sheetData) {
            $sheetProposed = $sheetData['sheet_grand_total_proposed'];
            $sheetApproved = $sheetData['sheet_grand_total_approved'];
            $parsedProposed = $sheetData['parsed_total_proposed'];
            $parsedApproved = $sheetData['parsed_total_approved'];

            if ($sheetProposed === null || $sheetApproved === null) {
                $checks[] = [
                    'scope' => "Sheet {$sheetData['sheet_name']} ({$groupCode})",
                    'status' => 'PERLU PERHATIAN',
                    'expected' => null,
                    'actual' => $parsedApproved,
                    'diff' => null,
                    'notes' => 'Cached value total sheet tidak tersedia.',
                ];

                continue;
            }

            $diffProposed = round($parsedProposed - $sheetProposed, 2);
            $diffApproved = round($parsedApproved - $sheetApproved, 2);
            $isMatch = abs($diffProposed) < 1.0 && abs($diffApproved) < 1.0;

            if (! $isMatch) {
                $fatalErrors[] = "Selisih rekonsiliasi pada {$sheetData['sheet_name']}: hasil parsing (Usulan {$parsedProposed}, Disetujui {$parsedApproved}) berbeda dengan total sheet (Usulan {$sheetProposed}, Disetujui {$sheetApproved}).";
            }

            $checks[] = [
                'scope' => "Sheet {$sheetData['sheet_name']} ({$groupCode})",
                'status' => $isMatch ? 'SESUAI' : 'BERBEDA',
                'expected' => $sheetApproved,
                'actual' => $parsedApproved,
                'diff' => $diffApproved,
                'notes' => $isMatch ? 'Total item cocok dengan grand total sheet.' : "Selisih Usulan: {$diffProposed}, Disetujui: {$diffApproved}.",
            ];
        }

        // 2. Total 6 worksheet vs REKAP
        $rekapProposed = $rekap['total_proposed'];
        $rekapApproved = $rekap['total_approved'];
        if ($rekapProposed === null || $rekapApproved === null) {
            $warnings[] = 'Total REKAP tidak memiliki cached value; rekonsiliasi REKAP tidak dapat diverifikasi otomatis.';
            $checks[] = [
                'scope' => 'Total 6 Sheet vs REKAP',
                'status' => 'PERLU PERHATIAN',
                'expected' => null,
                'actual' => $totalApprovedAll,
                'diff' => null,
                'notes' => 'Cached value REKAP tidak tersedia.',
            ];
        } else {
            $diffRekap = round($totalApprovedAll - $rekapApproved, 2);
            $isRekapMatch = abs($totalProposedAll - $rekapProposed) < 1.0 && abs($diffRekap) < 1.0;
            if (! $isRekapMatch) {
                $fatalErrors[] = "Total hasil parsing ({$totalApprovedAll}) tidak cocok dengan total REKAP ({$rekapApproved}).";
            }
            $checks[] = [
                'scope' => 'Total 6 Sheet vs REKAP',
                'status' => $isRekapMatch ? 'SESUAI' : 'BERBEDA',
                'expected' => $rekapApproved,
                'actual' => $totalApprovedAll,
                'diff' => $diffRekap,
                'notes' => $isRekapMatch ? 'Total seluruh item cocok dengan REKAP.' : "Selisih terhadap REKAP: {$diffRekap}.",
            ];
        }

        // 3. RINGKASAN vs REKAP
        if ($ringkasan['disetujui'] !== null && $rekapApproved !== null) {
            $diffRingkasan = round($ringkasan['disetujui'] - $rekapApproved, 2);
            $isRingkasanMatch = abs($diffRingkasan) < 1.0;
            if (! $isRingkasanMatch) {
                $fatalErrors[] = "Total RINGKASAN ({$ringkasan['disetujui']}) berbeda dengan total REKAP ({$rekapApproved}).";
            }
            $checks[] = [
                'scope' => 'RINGKASAN vs REKAP',
                'status' => $isRingkasanMatch ? 'SESUAI' : 'BERBEDA',
                'expected' => $rekapApproved,
                'actual' => $ringkasan['disetujui'],
                'diff' => $diffRingkasan,
                'notes' => $isRingkasanMatch ? 'RINGKASAN konsisten dengan REKAP.' : "Selisih RINGKASAN vs REKAP: {$diffRingkasan}.",
            ];
        }

        // 4. Kontrol Pagu
        if ($identitas['pagu_anggaran'] !== null && $totalApprovedAll > $identitas['pagu_anggaran']) {
            $warnings[] = 'Total disetujui ('.number_format($totalApprovedAll, 0, ',', '.').') melebihi Pagu Anggaran Unit ('.number_format($identitas['pagu_anggaran'], 0, ',', '.').').';
        }

        return $checks;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function readScalarCell(Cell $cell, array &$warnings, string $label): mixed
    {
        if ($cell->isFormula()) {
            $cached = $cell->getOldCalculatedValue();
            if ($cached === null) {
                $warnings[] = "Cached formula value tidak tersedia pada {$label}.";
            }

            return $cached;
        }

        return $cell->getValue();
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function readNumericCell(Cell $cell, array &$warnings, string $label): ?float
    {
        $val = $this->readScalarCell($cell, $warnings, $label);
        if ($val === null || $val === '') {
            return null;
        }

        if (is_numeric($val)) {
            return (float) $val;
        }

        return null;
    }

    private function cleanNullableString(mixed $val): ?string
    {
        $str = trim((string) $val);

        return $str === '' ? null : $str;
    }

    private function formatPossibleExcelDate(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        if (is_numeric($val) && (float) $val > 25569) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $val))->format('Y-m-d');
            } catch (\Throwable) {
                return (string) $val;
            }
        }

        return trim((string) $val);
    }

    /**
     * @return array{volume: float, unit: string}
     */
    private function parseVolumeAndUnit(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return ['volume' => 1.0, 'unit' => 'Unit'];
        }

        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*(.*)$/', $trimmed, $m)) {
            $vol = (float) str_replace(',', '.', $m[1]);
            $unit = trim($m[2]) !== '' ? trim($m[2]) : 'Unit';

            return ['volume' => $vol > 0 ? $vol : 1.0, 'unit' => $unit];
        }

        return ['volume' => 1.0, 'unit' => $trimmed];
    }

    private function mapAdjustmentActionType(string $statusText): string
    {
        $lower = mb_strtolower($statusText);

        if (str_contains($lower, 'disesuaikan lalu ditolak') || str_contains($lower, 'rejected_with_adjustment')) {
            return RkaAdjustment::ACTION_REJECTED_WITH_ADJUSTMENT;
        }
        if (str_contains($lower, 'dipindah') || str_contains($lower, 'moved')) {
            return RkaAdjustment::ACTION_MOVED;
        }
        if (str_contains($lower, 'dianggarkan di tempat lain') || str_contains($lower, 'dianggarkan di unit lain') || str_contains($lower, 'sudah dianggarkan') || str_contains($lower, 'budgeted_elsewhere')) {
            return RkaAdjustment::ACTION_BUDGETED_ELSEWHERE;
        }
        if (str_contains($lower, 'ditolak') || str_contains($lower, 'dibatalkan') || str_contains($lower, 'rejected')) {
            return RkaAdjustment::ACTION_REJECTED;
        }
        if (str_contains($lower, 'dihapus') || str_contains($lower, 'dipangkas') || str_contains($lower, 'removed')) {
            return RkaAdjustment::ACTION_REMOVED;
        }

        return RkaAdjustment::ACTION_PENDING;
    }

    private function mapSectionBActionType(string $statusText, string $notes): string
    {
        $combined = mb_strtolower(trim($statusText.' '.$notes));

        if (str_contains($combined, 'dipindah') || str_contains($combined, 'moved') || str_contains($combined, 'pindahan')) {
            return RkaAdjustment::ACTION_MOVED;
        }
        if (str_contains($combined, 'tambahan reviewer') || str_contains($combined, 'added_by_reviewer')) {
            return RkaAdjustment::ACTION_ADDED_BY_REVIEWER;
        }

        return RkaAdjustment::ACTION_SHARED_COST;
    }
}
