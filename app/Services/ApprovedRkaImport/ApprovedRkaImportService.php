<?php

namespace App\Services\ApprovedRkaImport;

use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\RkaUnlockRequest;
use App\Models\User;
use App\Services\Rka\Reconciliation\RkaReconciliationService;
use App\Services\RkaActivityLogService;
use App\Services\RkaApprovedTemplateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Service terpusat untuk Preview, Initial Import, dan Controlled Reimport RKA Approved.
 */
class ApprovedRkaImportService
{
    public const BELANJA_COLUMNS = [
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

    public const ADJUSTMENT_COLUMNS = [
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

    public const VALID_ITEM_STATUSES = [
        'APPROVED',
        'TRIMMED',
        'REJECTED',
        'REJECTED_WITH_ADJUSTMENT',
        'REMOVED',
        'BUDGETED_ELSEWHERE',
        'MOVED',
        'PENDING',
    ];

    public const VALID_ADJUSTMENT_STATUSES = [
        RkaAdjustment::ACTION_MOVED,
        RkaAdjustment::ACTION_BUDGETED_ELSEWHERE,
        RkaAdjustment::ACTION_REJECTED_WITH_ADJUSTMENT,
        RkaAdjustment::ACTION_REJECTED,
        RkaAdjustment::ACTION_REMOVED,
        RkaAdjustment::ACTION_PENDING,
        RkaAdjustment::ACTION_SHARED_COST,
        RkaAdjustment::ACTION_ADDED_BY_REVIEWER,
    ];

    public function __construct(
        private readonly RkaActivityLogService $activityLogService,
        private readonly NativeRkaWorkbookParser $nativeParser,
        private readonly RkaReconciliationService $reconciliationService,
    ) {}

    /**
     * Memvalidasi hak akses user untuk melakukan import / preview pada department tertentu.
     *
     * @throws AuthorizationException
     */
    public function authorizeUserForDepartment(User $user, int $departmentId, ?RkaHeader $header = null): void
    {
        if (! $user->can('importApproved', $header ?? RkaHeader::class)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengimpor RKA Approved.');
        }

        if ($user->hasRole('super_admin')) {
            return;
        }

        $ownsDepartment = $user->departments()
            ->where('departments.id', $departmentId)
            ->exists();

        if (! $ownsDepartment) {
            throw new AuthorizationException('Anda tidak diizinkan mengimpor data untuk unit kerja lain.');
        }
    }

    /**
     * Membaca dan memvalidasi file Excel tanpa mengubah tabel bisnis RKA.
     *
     * @return array<string, mixed>
     */
    public function preview(
        string $filePath,
        string $originalFilename,
        User $user,
        int $departmentId,
        int $scheduleId,
        string $mode = RkaApprovedImportBatch::TYPE_INITIAL,
        ?RkaHeader $targetHeader = null,
        ?int $unlockRequestId = null,
    ): array {
        if ($mode === RkaApprovedImportBatch::TYPE_DRAFT) {
            if ($targetHeader === null) {
                throw new InvalidArgumentException('RKA Header Draft tujuan wajib dipilih sebelum melakukan preview.');
            }

            $this->validateTargetDraftHeader($targetHeader, $user);
            $departmentId = (int) $targetHeader->department_id;
            $scheduleId = (int) $targetHeader->schedule_id;
        } else {
            $this->authorizeUserForDepartment($user, $departmentId, $targetHeader);
        }

        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException('File Excel tidak ditemukan atau tidak dapat dibaca.');
        }

        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            throw new InvalidArgumentException('Format file harus berupa .xlsx.');
        }

        if ($this->nativeParser->isNativeWorkbook($filePath)) {
            return $this->previewNativeWorkbook(
                filePath: $filePath,
                originalFilename: $originalFilename,
                user: $user,
                departmentId: $departmentId,
                scheduleId: $scheduleId,
                mode: $mode,
                targetHeader: $targetHeader,
                unlockRequestId: $unlockRequestId,
            );
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('File Excel tidak valid atau rusak: '.$e->getMessage());
        }

        $belanjaSheet = $spreadsheet->getSheetByName('BELANJA');
        $adjustmentSheet = $spreadsheet->getSheetByName('DAFTAR PENYESUAIAN');

        if (! $belanjaSheet || ! $adjustmentSheet) {
            throw new InvalidArgumentException('Workbook wajib memiliki worksheet "BELANJA" dan "DAFTAR PENYESUAIAN".');
        }

        $accountsByCode = Account::query()->get()->keyBy('code');
        $departmentsByName = Department::query()->get()->keyBy(
            fn (Department $dept): string => mb_strtolower(trim($dept->name))
        );

        $parsedBelanja = $this->parseBelanjaSheet($belanjaSheet, $accountsByCode);
        $parsedAdjustments = $this->parseAdjustmentSheet($adjustmentSheet, $accountsByCode, $departmentsByName);

        $department = Department::findOrFail($departmentId);
        $schedule = RkaSchedule::findOrFail($scheduleId);

        $allRows = array_merge($parsedBelanja['rows'], $parsedAdjustments['rows']);
        $counts = [
            'SESUAI' => 0,
            'BERBEDA' => 0,
            'TIDAK DITEMUKAN' => 0,
            'TIDAK VALID' => 0,
            'PERLU PERHATIAN' => 0,
        ];

        foreach ($allRows as $row) {
            $status = $row['validation_status'];
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $hasFatalErrors = ($counts['TIDAK DITEMUKAN'] + $counts['TIDAK VALID']) > 0
            || count($parsedBelanja['rows']) === 0;

        $token = (string) Str::uuid();
        $checksum = hash_file('sha256', $filePath) ?: Str::random(64);

        $previewData = [
            'token' => $token,
            'user_id' => $user->id,
            'mode' => $mode,
            'rka_header_id' => $targetHeader?->id,
            'unlock_request_id' => $unlockRequestId,
            'department_id' => $department->id,
            'department_name' => $department->name,
            'schedule_id' => $schedule->id,
            'fiscal_year' => $schedule->fiscal_year,
            'period_range' => $schedule->period_range,
            'original_filename' => basename($originalFilename),
            'file_checksum' => $checksum,
            'belanja_rows' => $parsedBelanja['rows'],
            'adjustment_rows' => $parsedAdjustments['rows'],
            'summary' => [
                'total_belanja' => count($parsedBelanja['rows']),
                'total_adjustment' => count($parsedAdjustments['rows']),
                'valid_count' => $counts['SESUAI'],
                'diff_count' => $counts['BERBEDA'] + $counts['PERLU PERHATIAN'],
                'not_found_count' => $counts['TIDAK DITEMUKAN'],
                'invalid_count' => $counts['TIDAK VALID'],
                'total_proposed' => $parsedBelanja['total_proposed'],
                'total_approved' => $parsedBelanja['total_approved'],
            ],
            'has_fatal_errors' => $hasFatalErrors,
            'fatal_reason' => count($parsedBelanja['rows']) === 0
                ? 'Worksheet BELANJA tidak memiliki baris item.'
                : ($hasFatalErrors ? 'Terdapat data TIDAK DITEMUKAN atau TIDAK VALID pada hasil preview.' : null),
            'created_at' => now()->toDateTimeString(),
        ];

        Cache::put($this->previewCacheKey($token), $previewData, now()->addMinutes(30));

        $this->activityLogService->log(
            action: RkaLog::ACTION_APPROVED_IMPORT_PREVIEW,
            headerId: $targetHeader?->id,
            subject: $targetHeader,
            description: ($hasFatalErrors ? 'Preview import RKA Approved gagal validasi: ' : 'Preview import RKA Approved berhasil: ').basename($originalFilename),
            newValues: [
                'mode' => $mode,
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'filename' => basename($originalFilename),
                'has_fatal_errors' => $hasFatalErrors,
                'summary' => $previewData['summary'],
            ],
            userId: $user->id,
        );

        return $previewData;
    }

    /**
     * Membaca dan memvalidasi workbook RKA asli Politeknik Kampar (12-13 sheet) tanpa mutasi DB.
     *
     * @return array<string, mixed>
     */
    private function previewNativeWorkbook(
        string $filePath,
        string $originalFilename,
        User $user,
        int $departmentId,
        int $scheduleId,
        string $mode,
        ?RkaHeader $targetHeader,
        ?int $unlockRequestId,
    ): array {
        $accountsByCode = Account::with('parent')->get()->keyBy('code');
        $departments = Department::query()->get();
        $department = $departments->firstWhere('id', $departmentId) ?? Department::findOrFail($departmentId);
        $schedule = RkaSchedule::findOrFail($scheduleId);

        $parsed = $this->nativeParser->parse($filePath, $accountsByCode, $departments);

        $fatalErrors = $parsed['fatal_errors'];
        $warnings = $parsed['warnings'];
        $infos = $parsed['infos'];

        $matchedDept = $parsed['matched_department'];
        if ($mode === RkaApprovedImportBatch::TYPE_DRAFT) {
            if ($matchedDept === null) {
                $excelUnit = $parsed['identitas']['unit_kerja'] ?? '(kosong)';
                $warnings[] = "Unit kerja pada file Excel ('{$excelUnit}') tidak terpetakan ke master Departemen. Data tetap akan diimpor ke RKA Draft '{$department->name}' tanpa mengubah identitas target.";
            } elseif ((int) $matchedDept['id'] !== (int) $department->id) {
                $warnings[] = "Unit asal pada Excel ('{$matchedDept['name']}') berbeda dengan unit RKA Draft ('{$department->name}'). Identitas unit, periode, indikator, dan pagu RKA Draft tetap dipertahankan.";
            }
        } else {
            if ($matchedDept === null) {
                $excelUnit = $parsed['identitas']['unit_kerja'] ?? '(kosong)';
                $fatalErrors[] = "Identitas Unit Kerja pada workbook ('{$excelUnit}') tidak dapat dicocokkan dengan master Departemen.";
            } elseif ((int) $matchedDept['id'] !== (int) $department->id) {
                if (! $user->hasRole('super_admin')) {
                    throw new AuthorizationException(
                        "Workbook ini milik unit '{$matchedDept['name']}', bukan unit '{$department->name}'."
                    );
                }
                $fatalErrors[] = "Unit Kerja pada workbook ('{$matchedDept['name']}') tidak cocok dengan unit yang dipilih ('{$department->name}').";
            }
        }

        $counts = [
            'SESUAI' => 0,
            'BERBEDA' => 0,
            'TIDAK DITEMUKAN' => 0,
            'TIDAK VALID' => 0,
            'PERLU PERHATIAN' => 0,
        ];

        foreach (array_merge($parsed['belanja_rows'], $parsed['adjustment_rows']) as $row) {
            $st = $row['validation_status'] ?? 'SESUAI';
            if (isset($counts[$st])) {
                $counts[$st]++;
            }
        }

        $sectionAProposed = 0.0;
        $sectionAApproved = 0.0;
        $sectionBProposed = 0.0;
        $sectionBApproved = 0.0;

        foreach ($parsed['adjustment_rows'] as $adjRow) {
            $secType = $adjRow['section_type'] ?? RkaAdjustment::SECTION_A;
            $prop = (float) ($adjRow['jumlah_usulan'] ?? 0);
            $appr = (float) ($adjRow['jumlah_disetujui'] ?? 0);

            if ($secType === RkaAdjustment::SECTION_B) {
                $sectionBProposed += $prop;
                $sectionBApproved += $appr;
            } else {
                $sectionAProposed += $prop;
                $sectionAApproved += $appr;
            }
        }

        $paguAwalUnit = $parsed['ringkasan']['usulan_awal_unit'] ?? null;

        $token = (string) Str::uuid();
        $checksum = hash_file('sha256', $filePath) ?: Str::random(64);

        $targetDraftMeta = null;
        if ($mode === RkaApprovedImportBatch::TYPE_DRAFT && $targetHeader !== null) {
            $duplicateFile = RkaApprovedImportBatch::query()
                ->where('rka_header_id', $targetHeader->id)
                ->where('status', RkaApprovedImportBatch::STATUS_SUCCESS)
                ->where('file_checksum', $checksum)
                ->exists();

            if ($duplicateFile) {
                $fatalErrors[] = 'File Excel yang sama sudah pernah diimpor ke RKA Draft ini. Import ganda file identik ditolak.';
            }

            if ($paguAwalUnit === null || ! is_numeric($paguAwalUnit) || (float) $paguAwalUnit < 0) {
                $fatalErrors[] = 'Nilai Usulan Awal Unit pada worksheet RINGKASAN tidak ditemukan atau tidak valid sehingga pagu_budget tidak dapat diisi.';
            }

            $existingItemRefs = RkaItem::query()
                ->where('rka_header_id', $targetHeader->id)
                ->whereNotNull('source_ref_id')
                ->pluck('source_ref_id')
                ->all();
            $existingAdjRefs = RkaAdjustment::query()
                ->where('rka_header_id', $targetHeader->id)
                ->whereNotNull('source_ref_id')
                ->pluck('source_ref_id')
                ->all();

            $duplicateItems = collect($parsed['belanja_rows'])
                ->filter(fn (array $row): bool => in_array((string) ($row['item_ref_id'] ?? $row['source_ref_id'] ?? ''), $existingItemRefs, true))
                ->count();
            $duplicateAdjustments = collect($parsed['adjustment_rows'])
                ->filter(fn (array $row): bool => in_array((string) ($row['adj_ref_id'] ?? ''), $existingAdjRefs, true))
                ->count();

            $sectionACount = collect($parsed['adjustment_rows'])
                ->filter(fn (array $row): bool => strtoupper((string) ($row['section'] ?? $row['section_type'] ?? 'A')) === RkaAdjustment::SECTION_A)
                ->count();
            $sectionBCount = collect($parsed['adjustment_rows'])
                ->filter(fn (array $row): bool => strtoupper((string) ($row['section'] ?? $row['section_type'] ?? '')) === RkaAdjustment::SECTION_B)
                ->count();

            $targetDraftMeta = [
                'rka_header_id' => $targetHeader->id,
                'department_name' => $department->name,
                'fiscal_year' => $schedule->fiscal_year,
                'period_range' => $schedule->period_range,
                'status' => $targetHeader->status,
                'pagu_budget' => (float) $targetHeader->pagu_budget,
                'new_pagu_budget' => ($paguAwalUnit !== null && is_numeric($paguAwalUnit)) ? (float) $paguAwalUnit : null,
                'pagu_awal_source' => $parsed['ringkasan']['usulan_awal_unit_source'] ?? null,
                'pagu_awal_label' => $parsed['ringkasan']['usulan_awal_unit_label'] ?? null,
                'indicator_count' => $targetHeader->indicators()->count(),
                'existing_item_count' => RkaItem::where('rka_header_id', $targetHeader->id)->count(),
                'existing_adjustment_count' => RkaAdjustment::where('rka_header_id', $targetHeader->id)->count(),
                'duplicate_file_detected' => $duplicateFile,
                'duplicate_item_count' => $duplicateItems,
                'new_item_count' => max(0, count($parsed['belanja_rows']) - $duplicateItems),
                'duplicate_adjustment_count' => $duplicateAdjustments,
                'new_adjustment_count' => max(0, count($parsed['adjustment_rows']) - $duplicateAdjustments),
                'section_a_count' => $sectionACount,
                'section_b_count' => $sectionBCount,
                'final_status' => RkaHeader::STATUS_APPROVED,
            ];
        }

        $hasFatalErrors = count($fatalErrors) > 0;

        $previewData = [
            'token' => $token,
            'format' => 'native',
            'user_id' => $user->id,
            'mode' => $mode,
            'rka_header_id' => $targetHeader?->id,
            'target_draft' => $targetDraftMeta,
            'unlock_request_id' => $unlockRequestId,
            'department_id' => $department->id,
            'department_name' => $department->name,
            'schedule_id' => $schedule->id,
            'fiscal_year' => $schedule->fiscal_year,
            'period_range' => $schedule->period_range,
            'original_filename' => basename($originalFilename),
            'file_checksum' => $checksum,
            'sheets_meta' => $parsed['sheets_meta'],
            'identitas' => $parsed['identitas'],
            'ringkasan' => $parsed['ringkasan'],
            'rekap' => $parsed['rekap'],
            'berita_acara' => $parsed['berita_acara'],
            'reconciliation' => $parsed['reconciliation'],
            'belanja_sheets' => $parsed['belanja_sheets'],
            'belanja_rows' => $parsed['belanja_rows'],
            'adjustment_rows' => $parsed['adjustment_rows'],
            'errors' => array_values(array_unique($fatalErrors)),
            'warnings' => array_values(array_unique($warnings)),
            'infos' => array_values(array_unique($infos)),
            'summary' => array_merge([
                'total_belanja' => count($parsed['belanja_rows']),
                'total_adjustment' => count($parsed['adjustment_rows']),
                'valid_count' => $counts['SESUAI'],
                'diff_count' => $counts['BERBEDA'] + $counts['PERLU PERHATIAN'],
                'not_found_count' => $counts['TIDAK DITEMUKAN'],
                'invalid_count' => $counts['TIDAK VALID'],
                'total_proposed' => $parsed['total_proposed'],
                'total_approved' => $parsed['total_approved'],
                'pagu_awal_unit' => $paguAwalUnit,
                'pagu_awal_source' => $parsed['ringkasan']['usulan_awal_unit_source'] ?? null,
                'pagu_awal_label' => $parsed['ringkasan']['usulan_awal_unit_label'] ?? null,
                'pagu_validation_status' => $paguAwalUnit !== null && $paguAwalUnit >= 0 ? 'VALID' : 'TIDAK DITEMUKAN',
                'total_section_a_proposed' => round($sectionAProposed, 2),
                'total_section_a_approved' => round($sectionAApproved, 2),
                'total_section_b_proposed' => round($sectionBProposed, 2),
                'total_section_b_approved' => round($sectionBApproved, 2),
                'total_adjustment_approved' => round($sectionAApproved + $sectionBApproved, 2),
            ], $this->summarizeUnitAssignments($parsed['adjustment_rows'], $department->id)),
            'has_fatal_errors' => $hasFatalErrors,
            'fatal_reason' => $hasFatalErrors ? implode(' ', array_slice($fatalErrors, 0, 3)) : null,
            'created_at' => now()->toDateTimeString(),
        ];

        Cache::put($this->previewCacheKey($token), $previewData, now()->addMinutes(30));

        $this->activityLogService->log(
            action: RkaLog::ACTION_APPROVED_IMPORT_PREVIEW,
            headerId: $targetHeader?->id,
            subject: $targetHeader,
            description: ($hasFatalErrors ? 'Preview import RKA asli gagal validasi: ' : 'Preview import RKA asli berhasil: ').basename($originalFilename),
            newValues: [
                'format' => 'native',
                'mode' => $mode,
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'filename' => basename($originalFilename),
                'has_fatal_errors' => $hasFatalErrors,
                'summary' => $previewData['summary'],
            ],
            userId: $user->id,
        );

        return $previewData;
    }

    /**
     * Mengeksekusi Initial Import Approved RKA dari sesi preview yang valid.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function executeImport(string $previewToken, User $user): RkaApprovedImportBatch
    {
        $preview = $this->resolveAndValidatePreview($previewToken, $user, RkaApprovedImportBatch::TYPE_INITIAL);

        $this->authorizeUserForDepartment($user, (int) $preview['department_id']);
        $this->validateAdjustmentUnitsBeforeFinalize($preview);

        $existingHeader = RkaHeader::query()
            ->where('department_id', $preview['department_id'])
            ->where('schedule_id', $preview['schedule_id'])
            ->first();

        if ($existingHeader !== null) {
            throw new InvalidArgumentException(
                'RKA untuk unit dan jadwal ini sudah tersedia. Gunakan alur Request Unlock & Import Ulang jika ingin memperbarui RKA Approved.'
            );
        }

        try {
            $batch = DB::transaction(function () use ($preview, $user): RkaApprovedImportBatch {
                $setting = RkaSetting::query()->latest('id')->first();
                if (! $setting) {
                    throw new InvalidArgumentException('RKA Setting aktif belum tersedia di sistem.');
                }

                $paguBudget = $this->resolvePaguBudgetForImport($preview);

                $header = RkaHeader::create([
                    'schedule_id' => $preview['schedule_id'],
                    'department_id' => $preview['department_id'],
                    'setting_id' => $setting->id,
                    'unit_head_user_id' => $user->id,
                    'status' => RkaHeader::STATUS_APPROVED,
                    'is_locked' => true,
                    'approved_at' => now(),
                    'pagu_budget' => $paguBudget,
                ]);

                $itemIdMap = $this->persistBelanjaItems($header, $preview['belanja_rows']);
                $this->persistAdjustments($header, $preview['adjustment_rows'], $itemIdMap);

                $batch = RkaApprovedImportBatch::create([
                    'rka_header_id' => $header->id,
                    'department_id' => $header->department_id,
                    'schedule_id' => $header->schedule_id,
                    'imported_by' => $user->id,
                    'import_type' => RkaApprovedImportBatch::TYPE_INITIAL,
                    'original_filename' => $preview['original_filename'],
                    'file_checksum' => $preview['file_checksum'],
                    'belanja_count' => $preview['summary']['total_belanja'],
                    'adjustment_count' => $preview['summary']['total_adjustment'],
                    'total_proposed' => $preview['summary']['total_proposed'],
                    'total_approved' => $preview['summary']['total_approved'],
                    'status' => RkaApprovedImportBatch::STATUS_SUCCESS,
                    'summary' => $preview['summary'],
                ]);

                $this->activityLogService->log(
                    action: RkaLog::ACTION_APPROVED_IMPORT_EXECUTED,
                    headerId: $header->id,
                    subject: $batch,
                    description: "Import RKA Approved berhasil ({$preview['original_filename']}); RKA langsung berstatus APPROVED & terkunci.",
                    newValues: [
                        'batch_id' => $batch->id,
                        'department_id' => $header->department_id,
                        'schedule_id' => $header->schedule_id,
                        'status' => RkaHeader::STATUS_APPROVED,
                        'is_locked' => true,
                        'belanja_count' => $batch->belanja_count,
                        'adjustment_count' => $batch->adjustment_count,
                    ],
                    userId: $user->id,
                );

                return $batch;
            });
        } catch (Throwable $e) {
            $this->activityLogService->log(
                action: RkaLog::ACTION_APPROVED_IMPORT_FAILED,
                headerId: null,
                subject: null,
                description: "Gagal Import RKA Approved ({$preview['original_filename']}): {$e->getMessage()}",
                newValues: [
                    'department_id' => $preview['department_id'],
                    'schedule_id' => $preview['schedule_id'],
                    'error' => $e->getMessage(),
                ],
                userId: $user->id,
            );

            throw $e;
        }

        Cache::forget($this->previewCacheKey($previewToken));

        if ($batch->rkaHeader) {
            $this->reconciliationService->reconcileForHeader(
                header: $batch->rkaHeader,
                batch: $batch,
                actorId: $user->id,
                source: ($preview['format'] ?? 'legacy') === 'native' ? 'NATIVE_IMPORT' : 'LEGACY_IMPORT',
            );
        }

        return $batch;
    }

    /**
     * Memvalidasi bahwa RKA Header Draft memenuhi syarat kepemilikan, status, jadwal, dan indikator Finance.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function validateTargetDraftHeader(RkaHeader $header, User $user): void
    {
        $this->authorizeUserForDepartment($user, (int) $header->department_id, $header);

        if (strtoupper((string) $header->status) !== RkaHeader::STATUS_DRAFT) {
            throw new InvalidArgumentException(
                "Import Approved RKA ke Draft ditolak: status RKA saat ini adalah '{$header->status}', bukan DRAFT."
            );
        }

        if ((bool) $header->is_locked) {
            throw new InvalidArgumentException('RKA Draft sedang terkunci sehingga tidak dapat diimpor.');
        }

        if ($header->schedule_id === null || $header->schedule === null) {
            throw new InvalidArgumentException('RKA Draft belum memiliki jadwal/periode anggaran yang valid.');
        }

        if (! $header->indicators()->exists()) {
            throw new InvalidArgumentException(
                'RKA Draft belum memiliki indikator yang disiapkan oleh Finance. Siapkan indikator terlebih dahulu sebelum melakukan import.'
            );
        }
    }

    /**
     * Mengeksekusi import data Excel Approved ke RKA Header Draft yang sudah dibuat Finance:
     * mengisi pagu_budget dari Usulan Awal Unit (RINGKASAN), menyimpan item & adjustment,
     * menyinkronkan DepartmentBudget, mengubah status menjadi APPROVED, dan mencatat provenance tanpa approval palsu.
     */
    public function executeDraftImport(
        string $previewToken,
        RkaHeader $header,
        User $user,
    ): RkaApprovedImportBatch {
        $preview = $this->resolveAndValidatePreview($previewToken, $user, RkaApprovedImportBatch::TYPE_DRAFT);

        if ((int) ($preview['rka_header_id'] ?? 0) !== (int) $header->id) {
            throw new InvalidArgumentException('Sesi preview tidak cocok dengan RKA Draft yang sedang dibuka.');
        }

        $this->validateAdjustmentUnitsBeforeFinalize($preview);
        $paguBudget = $this->resolvePaguBudgetForImport($preview);

        try {
            $batch = DB::transaction(function () use ($preview, $header, $user, $paguBudget): RkaApprovedImportBatch {
                $lockedHeader = RkaHeader::query()
                    ->with(['schedule', 'department'])
                    ->lockForUpdate()
                    ->findOrFail($header->id);

                $this->validateTargetDraftHeader($lockedHeader, $user);

                $duplicateFile = RkaApprovedImportBatch::query()
                    ->where('rka_header_id', $lockedHeader->id)
                    ->where('status', RkaApprovedImportBatch::STATUS_SUCCESS)
                    ->where('file_checksum', $preview['file_checksum'])
                    ->exists();

                if ($duplicateFile) {
                    throw new InvalidArgumentException(
                        'File Excel yang sama sudah pernah diimpor ke RKA Draft ini. Import ganda ditolak.'
                    );
                }

                $existingIndicators = $lockedHeader->indicators()
                    ->with('account')
                    ->orderBy('id')
                    ->get();

                $belanjaResult = $this->persistBelanjaItemsForDraft(
                    $lockedHeader,
                    $preview['belanja_rows'],
                    $existingIndicators,
                );

                $adjResult = $this->persistAdjustmentsForDraft(
                    $lockedHeader,
                    $preview['adjustment_rows'],
                    $belanjaResult['itemIdMap'],
                    $user,
                );

                $this->syncDepartmentBudgetForApprovedHeader($lockedHeader);

                $previousStatus = $lockedHeader->status;
                $previousPagu = (float) $lockedHeader->pagu_budget;

                $lockedHeader->update([
                    'pagu_budget' => $paguBudget,
                    'status' => RkaHeader::STATUS_APPROVED,
                    'is_locked' => true,
                ]);

                $batch = RkaApprovedImportBatch::create([
                    'rka_header_id' => $lockedHeader->id,
                    'department_id' => $lockedHeader->department_id,
                    'schedule_id' => $lockedHeader->schedule_id,
                    'imported_by' => $user->id,
                    'import_type' => RkaApprovedImportBatch::TYPE_DRAFT,
                    'original_filename' => $preview['original_filename'],
                    'file_checksum' => $preview['file_checksum'],
                    'belanja_count' => $belanjaResult['createdCount'],
                    'adjustment_count' => $adjResult['createdCount'],
                    'total_proposed' => $preview['summary']['total_proposed'],
                    'total_approved' => $preview['summary']['total_approved'],
                    'status' => RkaApprovedImportBatch::STATUS_SUCCESS,
                    'summary' => array_merge($preview['summary'], [
                        'created_items' => $belanjaResult['createdCount'],
                        'skipped_duplicate_items' => $belanjaResult['skippedCount'],
                        'created_adjustments' => $adjResult['createdCount'],
                        'skipped_duplicate_adjustments' => $adjResult['skippedCount'],
                        'provenance' => 'APPROVED_DOCUMENT_IMPORT',
                    ]),
                ]);

                $sectionACount = collect($preview['adjustment_rows'])
                    ->filter(fn (array $row): bool => strtoupper((string) ($row['section'] ?? $row['section_type'] ?? 'A')) === RkaAdjustment::SECTION_A)
                    ->count();
                $sectionBCount = collect($preview['adjustment_rows'])
                    ->filter(fn (array $row): bool => strtoupper((string) ($row['section'] ?? $row['section_type'] ?? '')) === RkaAdjustment::SECTION_B)
                    ->count();

                $this->activityLogService->log(
                    action: RkaLog::ACTION_APPROVED_IMPORT_EXECUTED,
                    headerId: $lockedHeader->id,
                    subject: $batch,
                    description: "Import Approved RKA ke RKA #{$lockedHeader->id} ({$preview['original_filename']}) berhasil: pagu_budget Rp ".number_format($paguBudget, 0, ',', '.').", {$belanjaResult['createdCount']} item, {$adjResult['createdCount']} penyesuaian, status {$previousStatus} -> APPROVED.",
                    oldValues: [
                        'status' => $previousStatus,
                        'pagu_budget' => $previousPagu,
                    ],
                    newValues: [
                        'batch_id' => $batch->id,
                        'rka_header_id' => $lockedHeader->id,
                        'department_id' => $lockedHeader->department_id,
                        'fiscal_year' => $lockedHeader->schedule?->fiscal_year,
                        'filename' => $preview['original_filename'],
                        'pagu_budget' => $paguBudget,
                        'pagu_awal_source' => $preview['summary']['pagu_awal_source'] ?? null,
                        'belanja_count' => $belanjaResult['createdCount'],
                        'adjustment_count' => $adjResult['createdCount'],
                        'section_a_count' => $sectionACount,
                        'section_b_count' => $sectionBCount,
                        'status' => RkaHeader::STATUS_APPROVED,
                        'is_locked' => true,
                        'provenance' => 'APPROVED_DOCUMENT_IMPORT',
                        'imported_at' => now()->toDateTimeString(),
                    ],
                    userId: $user->id,
                );

                return $batch;
            });
        } catch (Throwable $e) {
            $this->activityLogService->log(
                action: RkaLog::ACTION_APPROVED_IMPORT_FAILED,
                headerId: $header->id,
                subject: $header,
                description: "Gagal Import Approved RKA ke Draft #{$header->id} ({$preview['original_filename']}): {$e->getMessage()}",
                newValues: [
                    'rka_header_id' => $header->id,
                    'department_id' => $header->department_id,
                    'schedule_id' => $header->schedule_id,
                    'error' => $e->getMessage(),
                ],
                userId: $user->id,
            );

            throw $e;
        }

        Cache::forget($this->previewCacheKey($previewToken));

        return $batch;
    }

    /**
     * Menyinkronkan DepartmentBudget berdasarkan item RKA yang disetujui pada RKA Header APPROVED.
     */
    private function syncDepartmentBudgetForApprovedHeader(RkaHeader $rkaHeader): void
    {
        $approvedTotalsByAccount = $rkaHeader->items()
            ->whereIn('status', [
                RkaItem::STATUS_APPROVED,
                RkaItem::STATUS_ACCEPTED,
                RkaItem::STATUS_REDUCED,
                'TRIMMED',
            ])
            ->where('approved_total', '>', 0)
            ->select('account_id', DB::raw('SUM(approved_total) as total_approved'))
            ->groupBy('account_id')
            ->get();

        $fiscalYear = $rkaHeader->schedule?->fiscal_year ?? date('Y');
        $approvedAccountIds = [];

        foreach ($approvedTotalsByAccount as $itemSum) {
            $approvedAccountIds[] = $itemSum->account_id;

            DepartmentBudget::updateOrCreate(
                [
                    'department_id' => $rkaHeader->department_id,
                    'account_id' => $itemSum->account_id,
                    'fiscal_year' => $fiscalYear,
                ],
                [
                    'schedule_id' => $rkaHeader->schedule_id,
                    'allocated_amount' => $itemSum->total_approved,
                ]
            );
        }

        DepartmentBudget::query()
            ->where('department_id', $rkaHeader->department_id)
            ->where('fiscal_year', $fiscalYear)
            ->where('schedule_id', $rkaHeader->schedule_id)
            ->when(
                ! empty($approvedAccountIds),
                fn ($query) => $query->whereNotIn('account_id', $approvedAccountIds)
            )
            ->update(['allocated_amount' => 0]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $belanjaRows
     * @param  Collection<int, RkaIndicator>  $existingIndicators
     * @return array{itemIdMap: array<string, int>, createdCount: int, skippedCount: int}
     */
    private function persistBelanjaItemsForDraft(
        RkaHeader $header,
        array $belanjaRows,
        Collection $existingIndicators,
    ): array {
        $existingItemsByRef = RkaItem::query()
            ->where('rka_header_id', $header->id)
            ->whereNotNull('source_ref_id')
            ->get()
            ->keyBy('source_ref_id');

        $itemIdMap = [];
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($belanjaRows as $row) {
            $itemRefId = $row['item_ref_id'] ?? $row['source_ref_id'] ?? null;

            if ($itemRefId !== null && $existingItemsByRef->has((string) $itemRefId)) {
                $existingItem = $existingItemsByRef->get((string) $itemRefId);
                $itemIdMap[(string) $itemRefId] = $existingItem->id;
                $skippedCount++;

                continue;
            }

            $indicator = $this->resolveExistingIndicatorForDraft($row, $existingIndicators);
            $formType = $this->extractFormType($row['kelompok_belanja'], $row['kode_rekening']);

            $item = RkaItem::create([
                'source_ref_id' => $itemRefId,
                'rka_header_id' => $header->id,
                'rka_indicator_id' => $indicator->id,
                'account_id' => $row['account_id'],
                'form_type' => $formType,
                'description' => $row['uraian_item'],
                'group_header' => $row['group_header'],
                'proposed_volume' => $row['vol_usulan'],
                'unit_symbol' => $row['satuan'],
                'unit_price' => $row['harga_satuan_usulan'],
                'approved_volume' => $row['vol_disetujui'],
                'approved_total' => $row['nilai_disetujui'],
                'status' => $row['status_item'],
                'is_trimmed' => $row['status_item'] === 'TRIMMED',
                'is_imported' => $row['asal_item'] === 'IMPORT_SECTION_B',
                'review_notes' => $row['keterangan_review'] !== '' ? $row['keterangan_review'] : null,
            ]);

            $createdCount++;
            if ($itemRefId !== null) {
                $itemIdMap[(string) $itemRefId] = $item->id;
                $existingItemsByRef->put((string) $itemRefId, $item);
            }
        }

        return [
            'itemIdMap' => $itemIdMap,
            'createdCount' => $createdCount,
            'skippedCount' => $skippedCount,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $adjustmentRows
     * @param  array<string, int>  $itemIdMap
     * @return array{createdCount: int, skippedCount: int}
     */
    private function persistAdjustmentsForDraft(
        RkaHeader $header,
        array $adjustmentRows,
        array $itemIdMap,
        User $user,
    ): array {
        $existingAdjRefs = RkaAdjustment::query()
            ->where('rka_header_id', $header->id)
            ->whereNotNull('source_ref_id')
            ->pluck('source_ref_id')
            ->all();

        $createdCount = 0;
        $skippedCount = 0;
        $indicatorCache = [];

        foreach ($adjustmentRows as $row) {
            $adjRefId = (string) ($row['adj_ref_id'] ?? '');
            if ($adjRefId !== '' && in_array($adjRefId, $existingAdjRefs, true)) {
                $skippedCount++;

                continue;
            }

            $payload = $this->buildAdjustmentPayload($header, $row, $itemIdMap, $indicatorCache);
            $payload['adjusted_by'] = $user->id;
            $this->upsertIdempotentAdjustment($header, $payload);
            $createdCount++;

            if ($adjRefId !== '') {
                $existingAdjRefs[] = $adjRefId;
            }
        }

        return [
            'createdCount' => $createdCount,
            'skippedCount' => $skippedCount,
        ];
    }

    /**
     * Memetakan item Excel ke indikator yang SUDAH disiapkan oleh Finance pada RKA Draft
     * tanpa membuat, mengubah, atau menghapus indikator.
     *
     * @param  array<string, mixed>  $row
     * @param  Collection<int, RkaIndicator>  $existingIndicators
     */
    private function resolveExistingIndicatorForDraft(array $row, Collection $existingIndicators): RkaIndicator
    {
        $formType = $this->extractFormType((string) ($row['kelompok_belanja'] ?? ''), (string) ($row['kode_rekening'] ?? ''));
        $programKegiatan = mb_strtolower(trim((string) ($row['program_kegiatan'] ?? '')));

        $byAccountAndProgram = $existingIndicators->first(function (RkaIndicator $ind) use ($formType, $programKegiatan): bool {
            if ($ind->account?->code !== $formType) {
                return false;
            }

            if ($programKegiatan === '') {
                return false;
            }

            $indName = mb_strtolower(trim((string) $ind->program_name.' - '.(string) $ind->activity_name));

            return $indName === $programKegiatan
                || mb_strtolower(trim((string) $ind->activity_name)) === $programKegiatan;
        });

        if ($byAccountAndProgram !== null) {
            return $byAccountAndProgram;
        }

        $byAccount = $existingIndicators->first(
            fn (RkaIndicator $ind): bool => $ind->account?->code === $formType
        );

        return $byAccount ?? $existingIndicators->first();
    }

    /**
     * Mengeksekusi Import Ulang (Reimport) setelah Unlock Request disetujui Finance.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function executeReimport(
        string $previewToken,
        RkaHeader $header,
        RkaUnlockRequest $unlockRequest,
        User $user,
    ): RkaApprovedImportBatch {
        $preview = $this->resolveAndValidatePreview($previewToken, $user, RkaApprovedImportBatch::TYPE_REIMPORT);

        $this->authorizeUserForDepartment($user, $header->department_id, $header);
        $this->validateAdjustmentUnitsBeforeFinalize($preview);

        if ((int) $preview['rka_header_id'] !== $header->id) {
            throw new InvalidArgumentException('Sesi preview tidak cocok dengan dokumen RKA yang dipilih.');
        }

        if ($unlockRequest->rka_header_id !== $header->id) {
            throw new InvalidArgumentException('Unlock request tidak terikat pada dokumen RKA ini.');
        }

        try {
            $batch = DB::transaction(function () use ($preview, $header, $unlockRequest, $user): RkaApprovedImportBatch {
                $lockedHeader = RkaHeader::query()->lockForUpdate()->findOrFail($header->id);
                $lockedRequest = RkaUnlockRequest::query()->lockForUpdate()->findOrFail($unlockRequest->id);

                if (strtoupper((string) $lockedHeader->status) !== RkaHeader::STATUS_APPROVED) {
                    throw new InvalidArgumentException('Hanya RKA berstatus APPROVED yang dapat diimport ulang.');
                }

                if (! $lockedRequest->isReimportActive()) {
                    if ($lockedRequest->reimport_expires_at !== null && $lockedRequest->reimport_expires_at->isPast() && $lockedRequest->status === RkaUnlockRequest::STATUS_APPROVED) {
                        $lockedRequest->update(['status' => RkaUnlockRequest::STATUS_EXPIRED]);
                    }

                    throw new InvalidArgumentException('Hak import ulang tidak aktif, sudah digunakan, atau telah kedaluwarsa.');
                }

                $itemIdMap = $this->reconcileBelanjaItems($lockedHeader, $preview['belanja_rows']);
                $this->reconcileAdjustments($lockedHeader, $preview['adjustment_rows'], $itemIdMap);

                $paguBudget = $this->resolvePaguBudgetForImport($preview);

                $lockedHeader->update([
                    'status' => RkaHeader::STATUS_APPROVED,
                    'is_locked' => true,
                    'pagu_budget' => $paguBudget,
                ]);

                $batch = RkaApprovedImportBatch::create([
                    'rka_header_id' => $lockedHeader->id,
                    'department_id' => $lockedHeader->department_id,
                    'schedule_id' => $lockedHeader->schedule_id,
                    'imported_by' => $user->id,
                    'unlock_request_id' => $lockedRequest->id,
                    'import_type' => RkaApprovedImportBatch::TYPE_REIMPORT,
                    'original_filename' => $preview['original_filename'],
                    'file_checksum' => $preview['file_checksum'],
                    'belanja_count' => $preview['summary']['total_belanja'],
                    'adjustment_count' => $preview['summary']['total_adjustment'],
                    'total_proposed' => $preview['summary']['total_proposed'],
                    'total_approved' => $preview['summary']['total_approved'],
                    'status' => RkaApprovedImportBatch::STATUS_SUCCESS,
                    'summary' => $preview['summary'],
                ]);

                $lockedRequest->update([
                    'status' => RkaUnlockRequest::STATUS_USED,
                    'reimport_used_at' => now(),
                    'reimport_batch_id' => $batch->id,
                ]);

                $this->activityLogService->log(
                    action: RkaLog::ACTION_APPROVED_REIMPORT_EXECUTED,
                    headerId: $lockedHeader->id,
                    subject: $batch,
                    description: "Import ulang RKA Approved berhasil ({$preview['original_filename']}); RKA dikunci kembali.",
                    newValues: [
                        'batch_id' => $batch->id,
                        'unlock_request_id' => $lockedRequest->id,
                        'belanja_count' => $batch->belanja_count,
                        'adjustment_count' => $batch->adjustment_count,
                    ],
                    userId: $user->id,
                );

                $this->activityLogService->log(
                    action: RkaLog::ACTION_UNLOCK_REIMPORT_USED,
                    headerId: $lockedHeader->id,
                    subject: $lockedRequest,
                    description: "Hak reimport Request #{$lockedRequest->id} telah digunakan.",
                    newValues: ['reimport_batch_id' => $batch->id],
                    userId: $user->id,
                );

                $this->activityLogService->log(
                    action: RkaLog::ACTION_RELOCKED,
                    headerId: $lockedHeader->id,
                    subject: $lockedHeader,
                    description: 'RKA dikunci kembali setelah import ulang selesai.',
                    newValues: ['is_locked' => true, 'status' => RkaHeader::STATUS_APPROVED],
                    userId: $user->id,
                );

                return $batch;
            });
        } catch (Throwable $e) {
            $this->activityLogService->log(
                action: RkaLog::ACTION_APPROVED_REIMPORT_FAILED,
                headerId: $header->id,
                subject: $unlockRequest,
                description: "Gagal Import Ulang RKA Approved: {$e->getMessage()}",
                newValues: ['unlock_request_id' => $unlockRequest->id, 'error' => $e->getMessage()],
                userId: $user->id,
            );

            throw $e;
        }

        Cache::forget($this->previewCacheKey($previewToken));

        if ($batch->rkaHeader) {
            $this->reconciliationService->reconcileForHeader(
                header: $batch->rkaHeader,
                batch: $batch,
                actorId: $user->id,
                source: ($preview['format'] ?? 'legacy') === 'native' ? 'NATIVE_REIMPORT' : 'LEGACY_REIMPORT',
            );
        }

        return $batch;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAndValidatePreview(string $previewToken, User $user, string $expectedMode): array
    {
        $preview = Cache::get($this->previewCacheKey($previewToken));

        if (! is_array($preview)) {
            throw new InvalidArgumentException('Sesi preview tidak ditemukan atau telah kedaluwarsa. Silakan upload ulang file Excel.');
        }

        if ((int) ($preview['user_id'] ?? 0) !== $user->id) {
            throw new AuthorizationException('Sesi preview ini bukan milik akun Anda.');
        }

        if (($preview['mode'] ?? '') !== $expectedMode) {
            throw new InvalidArgumentException('Mode sesi preview tidak sesuai dengan operasi yang dijalankan.');
        }

        if (! empty($preview['has_fatal_errors'])) {
            throw new InvalidArgumentException('Import dibatalkan karena masih terdapat data tidak valid atau tidak ditemukan pada hasil preview.');
        }

        return $preview;
    }

    /**
     * @param  Collection<string, Account>  $accountsByCode
     * @return array{rows: array<int, array<string, mixed>>, total_proposed: float, total_approved: float}
     */
    private function parseBelanjaSheet(Worksheet $sheet, $accountsByCode): array
    {
        $headerRow = $this->findHeaderRow($sheet, 'ITEM_REF_ID');
        if ($headerRow === null) {
            throw new InvalidArgumentException('Struktur header worksheet BELANJA tidak sesuai template (kolom ITEM_REF_ID tidak ditemukan).');
        }

        $colMap = $this->mapHeaderColumns($sheet, $headerRow, self::BELANJA_COLUMNS);
        $highestRow = $sheet->getHighestRow();

        $rows = [];
        $seenRefs = [];
        $totalProposed = 0.0;
        $totalApproved = 0.0;

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $itemRef = trim((string) $sheet->getCell($colMap['ITEM_REF_ID'].$r)->getValue());
            $kodeRekening = trim((string) $sheet->getCell($colMap['KODE_REKENING'].$r)->getValue());
            $uraian = trim((string) $sheet->getCell($colMap['URAIAN_ITEM'].$r)->getValue());

            // Berhenti saat mencapai blok pengesahan atau skip banner/subtotal/baris kosong
            if ($itemRef === '' && $kodeRekening === '' && $uraian === '') {
                continue;
            }
            if (str_starts_with(strtoupper($itemRef), 'JUMLAH') || str_starts_with(strtoupper($itemRef), 'GRAND TOTAL')) {
                continue;
            }
            if ($kodeRekening === '' && $uraian === '') {
                continue;
            }

            $kelompok = trim((string) $sheet->getCell($colMap['KELOMPOK_BELANJA'].$r)->getValue());
            if (strcasecmp($kelompok, 'Diusulkan oleh,') === 0 || strcasecmp($uraian, 'Direview oleh,') === 0) {
                break;
            }
            if (str_starts_with(strtoupper($itemRef), 'TIDAK ADA ')) {
                continue;
            }

            $programKegiatan = trim((string) $sheet->getCell($colMap['PROGRAM_KEGIATAN'].$r)->getValue());
            $namaRekening = trim((string) $sheet->getCell($colMap['NAMA_REKENING'].$r)->getValue());
            $groupHeader = trim((string) $sheet->getCell($colMap['GROUP_HEADER'].$r)->getValue());
            $volUsulan = (float) $sheet->getCell($colMap['VOL_USULAN'].$r)->getCalculatedValue();
            $satuan = trim((string) $sheet->getCell($colMap['SATUAN'].$r)->getValue());
            $hargaUsulan = (float) $sheet->getCell($colMap['HARGA_SATUAN_USULAN'].$r)->getCalculatedValue();
            $nilaiUsulan = (float) $sheet->getCell($colMap['NILAI_USULAN'].$r)->getCalculatedValue();
            $volDisetujui = (float) $sheet->getCell($colMap['VOL_DISETUJUI'].$r)->getCalculatedValue();
            $hargaDisetujui = (float) $sheet->getCell($colMap['HARGA_SATUAN_DISETUJUI'].$r)->getCalculatedValue();
            $nilaiDisetujui = (float) $sheet->getCell($colMap['NILAI_DISETUJUI'].$r)->getCalculatedValue();
            $statusItem = strtoupper(trim((string) $sheet->getCell($colMap['STATUS_ITEM'].$r)->getValue()));
            $asalItem = strtoupper(trim((string) $sheet->getCell($colMap['ASAL_ITEM'].$r)->getValue()));
            $keterangan = trim((string) $sheet->getCell($colMap['KETERANGAN_REVIEW'].$r)->getValue());

            $account = $accountsByCode->get($kodeRekening);
            $validationStatus = 'SESUAI';
            $validationNotes = [];

            if ($itemRef !== '' && isset($seenRefs[$itemRef])) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = "Duplikat ITEM_REF_ID ({$itemRef}).";
            } elseif ($itemRef !== '') {
                $seenRefs[$itemRef] = true;
            }

            if (! $account) {
                $validationStatus = 'TIDAK DITEMUKAN';
                $validationNotes[] = "Kode rekening '{$kodeRekening}' tidak ditemukan di master Account.";
            }

            if (! in_array($statusItem, self::VALID_ITEM_STATUSES, true)) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = "STATUS_ITEM '{$statusItem}' tidak dikenali.";
            }

            if ($uraian === '' || $satuan === '') {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = 'Uraian atau satuan wajib diisi.';
            }

            if ($validationStatus === 'SESUAI') {
                $calcUsulan = round($volUsulan * $hargaUsulan, 2);
                if (abs($calcUsulan - round($nilaiUsulan, 2)) >= 1.0) {
                    $validationStatus = 'BERBEDA';
                    $validationNotes[] = "Nilai usulan Excel ({$nilaiUsulan}) berbeda dengan Vol × Harga ({$calcUsulan}).";
                }
            }

            $totalProposed += $nilaiUsulan;
            $totalApproved += $nilaiDisetujui;

            $rows[] = [
                'row_number' => $r,
                'item_ref_id' => $itemRef !== '' ? $itemRef : null,
                'kelompok_belanja' => $kelompok,
                'program_kegiatan' => $programKegiatan,
                'kode_rekening' => $kodeRekening,
                'nama_rekening' => $namaRekening,
                'account_id' => $account?->id,
                'mapped_account_name' => $account?->name,
                'group_header' => $groupHeader,
                'uraian_item' => $uraian,
                'vol_usulan' => $volUsulan,
                'satuan' => $satuan,
                'harga_satuan_usulan' => $hargaUsulan,
                'nilai_usulan' => $nilaiUsulan,
                'vol_disetujui' => $volDisetujui,
                'harga_satuan_disetujui' => $hargaDisetujui,
                'nilai_disetujui' => $nilaiDisetujui,
                'status_item' => $statusItem,
                'asal_item' => $asalItem === 'IMPORT_SECTION_B' ? 'IMPORT_SECTION_B' : 'USULAN_ASLI',
                'keterangan_review' => $keterangan,
                'validation_status' => $validationStatus,
                'validation_message' => implode(' ', $validationNotes) ?: 'Data valid dan sesuai master.',
            ];
        }

        return [
            'rows' => $rows,
            'total_proposed' => $totalProposed,
            'total_approved' => $totalApproved,
        ];
    }

    /**
     * @param  Collection<string, Account>  $accountsByCode
     * @param  Collection<string, Department>  $departmentsByName
     * @return array{rows: array<int, array<string, mixed>>}
     */
    private function parseAdjustmentSheet(Worksheet $sheet, $accountsByCode, $departmentsByName): array
    {
        $highestRow = $sheet->getHighestRow();
        $rows = [];
        $seenAdjRefs = [];
        $colMap = null;

        for ($r = 1; $r <= $highestRow; $r++) {
            $firstCell = trim((string) $sheet->getCell("A{$r}")->getValue());

            if ($firstCell === 'ADJ_REF_ID') {
                $colMap = $this->mapHeaderColumns($sheet, $r, self::ADJUSTMENT_COLUMNS);

                continue;
            }

            if ($colMap === null) {
                continue;
            }

            if (
                $firstCell === ''
                || str_starts_with(strtoupper($firstCell), 'JUMLAH')
                || str_starts_with(strtoupper($firstCell), 'A. ')
                || str_starts_with(strtoupper($firstCell), 'B. ')
                || str_starts_with(strtoupper($firstCell), 'TIDAK ADA ')
            ) {
                continue;
            }

            $adjRef = trim((string) $sheet->getCell($colMap['ADJ_REF_ID'].$r)->getValue());
            $originItemId = trim((string) $sheet->getCell($colMap['ORIGIN_ITEM_ID'].$r)->getValue());
            $importedItemId = trim((string) $sheet->getCell($colMap['IMPORTED_ITEM_ID'].$r)->getValue());
            $bagian = strtoupper(trim((string) $sheet->getCell($colMap['BAGIAN'].$r)->getValue()));
            $statusAdj = strtoupper(trim((string) $sheet->getCell($colMap['STATUS_PENYESUAIAN'].$r)->getValue()));
            $kodeRekening = trim((string) $sheet->getCell($colMap['KODE_REKENING'].$r)->getValue());
            $namaRekening = trim((string) $sheet->getCell($colMap['NAMA_REKENING'].$r)->getValue());
            $uraian = trim((string) $sheet->getCell($colMap['URAIAN'].$r)->getValue());
            $unitAsal = trim((string) $sheet->getCell($colMap['UNIT_ASAL'].$r)->getValue());
            $unitTujuan = trim((string) $sheet->getCell($colMap['UNIT_TUJUAN'].$r)->getValue());
            $volume = (float) $sheet->getCell($colMap['VOLUME'].$r)->getCalculatedValue();
            $satuan = trim((string) $sheet->getCell($colMap['SATUAN'].$r)->getValue());
            $hargaSatuan = (float) $sheet->getCell($colMap['HARGA_SATUAN'].$r)->getCalculatedValue();
            $jumlahUsulan = (float) $sheet->getCell($colMap['JUMLAH_USULAN'].$r)->getCalculatedValue();
            $jumlahDisetujui = (float) $sheet->getCell($colMap['JUMLAH_DISETUJUI'].$r)->getCalculatedValue();
            $keterangan = trim((string) $sheet->getCell($colMap['KETERANGAN_REVIEWER'].$r)->getValue());
            $reviewer = trim((string) $sheet->getCell($colMap['REVIEWER'].$r)->getValue());

            $account = $kodeRekening !== '' && $kodeRekening !== '-' ? $accountsByCode->get($kodeRekening) : null;
            $targetDept = ($unitTujuan !== '' && $unitTujuan !== '-')
                ? $departmentsByName->get(mb_strtolower($unitTujuan))
                : null;

            $validationStatus = 'SESUAI';
            $validationNotes = [];

            if ($adjRef !== '' && isset($seenAdjRefs[$adjRef])) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = "Duplikat ADJ_REF_ID ({$adjRef}).";
            } elseif ($adjRef !== '') {
                $seenAdjRefs[$adjRef] = true;
            }

            if (! in_array($bagian, [RkaAdjustment::SECTION_A, RkaAdjustment::SECTION_B], true)) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = "BAGIAN '{$bagian}' harus A atau B.";
            }

            if (! in_array($statusAdj, self::VALID_ADJUSTMENT_STATUSES, true)) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = "STATUS_PENYESUAIAN '{$statusAdj}' tidak valid.";
            }

            // Business rule PHASE 1: PENDING wajib masuk Section A
            if ($statusAdj === RkaAdjustment::ACTION_PENDING && $bagian !== RkaAdjustment::SECTION_A) {
                $validationStatus = 'TIDAK VALID';
                $validationNotes[] = 'Status PENDING wajib berada pada BAGIAN A (Section A).';
            }

            if ($kodeRekening !== '' && $kodeRekening !== '-' && ! $account) {
                $validationStatus = 'TIDAK DITEMUKAN';
                $validationNotes[] = "Kode rekening '{$kodeRekening}' tidak ditemukan.";
            }

            $rows[] = [
                'row_number' => $r,
                'adj_ref_id' => $adjRef !== '' ? $adjRef : null,
                'origin_item_id' => $originItemId !== '' ? $originItemId : null,
                'imported_item_id' => $importedItemId !== '' ? $importedItemId : null,
                'bagian' => $bagian,
                'status_penyesuaian' => $statusAdj,
                'kode_rekening' => $kodeRekening,
                'nama_rekening' => $namaRekening,
                'account_id' => $account?->id,
                'uraian' => $uraian,
                'unit_asal' => $unitAsal,
                'unit_tujuan' => $unitTujuan,
                'target_department_id' => $targetDept?->id,
                'volume' => $volume,
                'satuan' => $satuan !== '' ? $satuan : 'Unit',
                'harga_satuan' => $hargaSatuan,
                'jumlah_usulan' => $jumlahUsulan,
                'jumlah_disetujui' => $statusAdj === RkaAdjustment::ACTION_PENDING ? 0.0 : $jumlahDisetujui,
                'keterangan_reviewer' => $keterangan,
                'reviewer' => $reviewer,
                'validation_status' => $validationStatus,
                'validation_message' => implode(' ', $validationNotes) ?: 'Data penyesuaian valid.',
            ];
        }

        return ['rows' => $rows];
    }

    private function findHeaderRow(Worksheet $sheet, string $expectedFirstColumn): ?int
    {
        $highestRow = min($sheet->getHighestRow(), 50);
        for ($r = 1; $r <= $highestRow; $r++) {
            if (trim((string) $sheet->getCell("A{$r}")->getValue()) === $expectedFirstColumn) {
                return $r;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $requiredColumns
     * @return array<string, string>
     */
    private function mapHeaderColumns(Worksheet $sheet, int $headerRow, array $requiredColumns): array
    {
        $highestColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $found = [];

        for ($c = 1; $c <= $highestColIdx; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $val = trim((string) $sheet->getCell("{$colLetter}{$headerRow}")->getValue());
            if ($val !== '') {
                $found[$val] = $colLetter;
            }
        }

        foreach ($requiredColumns as $colName) {
            if (! isset($found[$colName])) {
                throw new InvalidArgumentException("Kolom wajib '{$colName}' tidak ditemukan pada baris header {$headerRow}.");
            }
        }

        return $found;
    }

    /**
     * @param  array<int, array<string, mixed>>  $belanjaRows
     * @return array<string, int> Mapping source_ref_id => new rka_items.id
     */
    private function persistBelanjaItems(RkaHeader $header, array $belanjaRows): array
    {
        $indicatorCache = [];
        $itemIdMap = [];

        foreach ($belanjaRows as $row) {
            $indicator = $this->resolveOrCreateIndicator($header, $row, $indicatorCache);
            $formType = $this->extractFormType($row['kelompok_belanja'], $row['kode_rekening']);
            $itemRefId = $row['item_ref_id'] ?? $row['source_ref_id'] ?? null;

            $item = RkaItem::create([
                'source_ref_id' => $itemRefId,
                'rka_header_id' => $header->id,
                'rka_indicator_id' => $indicator->id,
                'account_id' => $row['account_id'],
                'form_type' => $formType,
                'description' => $row['uraian_item'],
                'group_header' => $row['group_header'],
                'proposed_volume' => $row['vol_usulan'],
                'unit_symbol' => $row['satuan'],
                'unit_price' => $row['harga_satuan_usulan'],
                'approved_volume' => $row['vol_disetujui'],
                'approved_total' => $row['nilai_disetujui'],
                'status' => $row['status_item'],
                'is_trimmed' => $row['status_item'] === 'TRIMMED',
                'is_imported' => $row['asal_item'] === 'IMPORT_SECTION_B',
                'review_notes' => $row['keterangan_review'] !== '' ? $row['keterangan_review'] : null,
            ]);

            if ($itemRefId !== null) {
                $itemIdMap[(string) $itemRefId] = $item->id;
            }
        }

        return $itemIdMap;
    }

    /**
     * @param  array<int, array<string, mixed>>  $belanjaRows
     * @return array<string, int>
     */
    private function reconcileBelanjaItems(RkaHeader $header, array $belanjaRows): array
    {
        $indicatorCache = [];
        $itemIdMap = [];
        $keptIds = [];

        $existingItems = RkaItem::query()
            ->where('rka_header_id', $header->id)
            ->get();

        foreach ($belanjaRows as $row) {
            $indicator = $this->resolveOrCreateIndicator($header, $row, $indicatorCache);
            $formType = $this->extractFormType($row['kelompok_belanja'], $row['kode_rekening']);
            $refId = $row['item_ref_id'] ?? $row['source_ref_id'] ?? null;

            $match = null;
            if ($refId !== null) {
                $match = $existingItems->first(
                    fn (RkaItem $i): bool => (string) $i->id === (string) $refId || (string) $i->source_ref_id === (string) $refId
                );
            }

            $payload = [
                'source_ref_id' => $refId,
                'rka_indicator_id' => $indicator->id,
                'account_id' => $row['account_id'],
                'form_type' => $formType,
                'description' => $row['uraian_item'],
                'group_header' => $row['group_header'],
                'proposed_volume' => $row['vol_usulan'],
                'unit_symbol' => $row['satuan'],
                'unit_price' => $row['harga_satuan_usulan'],
                'approved_volume' => $row['vol_disetujui'],
                'approved_total' => $row['nilai_disetujui'],
                'status' => $row['status_item'],
                'is_trimmed' => $row['status_item'] === 'TRIMMED',
                'is_imported' => $row['asal_item'] === 'IMPORT_SECTION_B',
                'review_notes' => $row['keterangan_review'] !== '' ? $row['keterangan_review'] : null,
            ];

            if ($match) {
                $match->update($payload);
                $item = $match;
            } else {
                $item = RkaItem::create(array_merge($payload, ['rka_header_id' => $header->id]));
            }

            $keptIds[] = $item->id;
            if ($refId !== null) {
                $itemIdMap[(string) $refId] = $item->id;
            }
        }

        // Hapus item lama pada header ini yang tidak lagi ada di file Excel terbaru (cegah hapus jika sudah ada transaksi keuangan)
        $itemsToDelete = RkaItem::query()
            ->where('rka_header_id', $header->id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->get();

        foreach ($itemsToDelete as $obsoleteItem) {
            if (method_exists($obsoleteItem, 'fundRequestItems') && $obsoleteItem->fundRequestItems()->exists()) {
                throw new InvalidArgumentException("Item '{$obsoleteItem->description}' (#{$obsoleteItem->id}) tidak dapat dihapus saat import ulang karena sudah memiliki transaksi pengajuan dana.");
            }
            $obsoleteItem->delete();
        }

        return $itemIdMap;
    }

    /**
     * @param  array<int, array<string, mixed>>  $adjustmentRows
     * @param  array<string, int>  $itemIdMap
     */
    private function persistAdjustments(RkaHeader $header, array $adjustmentRows, array $itemIdMap): void
    {
        $existingAdjRefs = RkaAdjustment::query()
            ->where('rka_header_id', $header->id)
            ->whereNotNull('source_ref_id')
            ->pluck('source_ref_id')
            ->all();

        $indicatorCache = [];

        foreach ($adjustmentRows as $row) {
            $adjRefId = (string) ($row['adj_ref_id'] ?? '');
            if ($adjRefId !== '' && in_array($adjRefId, $existingAdjRefs, true)) {
                continue;
            }

            $payload = $this->buildAdjustmentPayload($header, $row, $itemIdMap, $indicatorCache);
            $this->upsertIdempotentAdjustment($header, $payload);

            if ($adjRefId !== '') {
                $existingAdjRefs[] = $adjRefId;
            }
        }
    }

    /**
     * Simpan atau perbarui adjustment secara idempoten (setara RkaReviewService) dan selaraskan status item asal Section A.
     *
     * @param  array<string, mixed>  $payload
     */
    private function upsertIdempotentAdjustment(RkaHeader $header, array $payload): RkaAdjustment
    {
        if (! empty($payload['rka_item_id'])) {
            $adjustment = RkaAdjustment::updateOrCreate(
                [
                    'rka_header_id' => $header->id,
                    'rka_item_id' => $payload['rka_item_id'],
                    'section_type' => $payload['section_type'],
                ],
                $payload
            );
        } elseif (! empty($payload['source_ref_id'])) {
            $adjustment = RkaAdjustment::updateOrCreate(
                [
                    'rka_header_id' => $header->id,
                    'source_ref_id' => $payload['source_ref_id'],
                    'section_type' => $payload['section_type'],
                ],
                $payload
            );
        } else {
            $adjustment = RkaAdjustment::create($payload);
        }

        // Selaraskan status RkaItem milik unit asal pada Section A tanpa menghapus atau memindahkan kepemilikan item
        if ($payload['section_type'] === RkaAdjustment::SECTION_A && ! empty($payload['rka_item_id'])) {
            $originItem = RkaItem::query()
                ->where('rka_header_id', $header->id)
                ->find($payload['rka_item_id']);

            if ($originItem && ($originItem->status === RkaItem::STATUS_PENDING || $payload['action_type'] !== RkaAdjustment::ACTION_PENDING)) {
                $originItem->update([
                    'status' => $payload['action_type'],
                    'approved_volume' => 0,
                    'approved_total' => 0,
                    'is_trimmed' => true,
                ]);
            }
        }

        return $adjustment;
    }

    /**
     * @param  array<int, array<string, mixed>>  $adjustmentRows
     * @param  array<string, int>  $itemIdMap
     */
    private function reconcileAdjustments(RkaHeader $header, array $adjustmentRows, array $itemIdMap): void
    {
        $existingAdjustments = RkaAdjustment::query()
            ->where('rka_header_id', $header->id)
            ->get();
        $keptIds = [];

        foreach ($adjustmentRows as $row) {
            $refId = $row['adj_ref_id'];
            $match = null;
            if ($refId !== null) {
                $match = $existingAdjustments->first(
                    fn (RkaAdjustment $a): bool => (string) $a->id === (string) $refId || (string) $a->source_ref_id === (string) $refId
                );
            }

            $payload = $this->buildAdjustmentPayload($header, $row, $itemIdMap);

            if ($match) {
                $match->update($payload);
                $adj = $match;
            } else {
                $adj = RkaAdjustment::create($payload);
            }

            $keptIds[] = $adj->id;
        }

        RkaAdjustment::query()
            ->where('rka_header_id', $header->id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $itemIdMap
     * @param  array<string, RkaIndicator>  $indicatorCache
     * @return array<string, mixed>
     */
    private function buildAdjustmentPayload(RkaHeader $header, array $row, array &$itemIdMap, array &$indicatorCache = []): array
    {
        $actionType = $row['action_type'] ?? $row['status_penyesuaian'];
        $rawSection = $row['bagian'] ?? $row['section'] ?? RkaAdjustment::SECTION_A;

        $sectionType = $actionType === RkaAdjustment::ACTION_PENDING
            ? RkaAdjustment::SECTION_A
            : $rawSection;

        $originRef = $row['origin_item_id'] ?? null;
        $importedRef = $row['imported_item_id'] ?? null;
        $targetDeptId = $row['target_department_id'] ?? null;

        $originItemId = $originRef !== null
            ? ($itemIdMap[(string) $originRef] ?? null)
            : null;
        $importedItemId = $importedRef !== null
            ? ($itemIdMap[(string) $importedRef] ?? null)
            : null;

        $notes = trim((string) ($row['keterangan_reviewer'] ?? ''));
        if (isset($row['action_type']) && ! empty($row['status_penyesuaian'])) {
            $prefix = "[{$row['status_penyesuaian']}]";
            $notes = $notes !== '' ? "{$prefix} {$notes}" : $prefix;
        }

        // Section A: pastikan item belanja milik unit asal tersedia di rka_items (buat baru jika belum ada dan tidak ambigu)
        if ($sectionType === RkaAdjustment::SECTION_A && $originItemId === null) {
            $originItemId = $this->ensureSectionAOriginItemId($header, $row, $actionType, $notes, $itemIdMap, $indicatorCache);
        } elseif ($sectionType === RkaAdjustment::SECTION_B && $importedItemId === null) {
            // Section B: hanya tautkan ke item belanja pengelola eksisting (JANGAN pernah membuat RkaItem baru)
            $foundB = $this->findUniqueExistingItemId($header->id, $row, true);
            $importedItemId = ($foundB !== null && $foundB > 0) ? $foundB : null;
        }

        $linkedItemId = $sectionType === RkaAdjustment::SECTION_B
            ? ($originItemId ?? $importedItemId)
            : $originItemId;

        $indicatorId = null;
        if ($sectionType === RkaAdjustment::SECTION_A && $linkedItemId !== null) {
            $indicatorId = RkaItem::query()
                ->where('rka_header_id', $header->id)
                ->where('id', $linkedItemId)
                ->value('rka_indicator_id');
        }

        $sectionAZeroStatuses = [
            RkaAdjustment::ACTION_MOVED,
            RkaAdjustment::ACTION_BUDGETED_ELSEWHERE,
            RkaAdjustment::ACTION_REJECTED,
            RkaAdjustment::ACTION_REJECTED_WITH_ADJUSTMENT,
            RkaAdjustment::ACTION_REMOVED,
            RkaAdjustment::ACTION_PENDING,
        ];

        $approvedAmount = $sectionType === RkaAdjustment::SECTION_A && in_array($actionType, $sectionAZeroStatuses, true)
            ? 0.0
            : (float) ($row['jumlah_disetujui'] ?? 0);

        return [
            'source_ref_id' => $row['adj_ref_id'],
            'rka_header_id' => $header->id,
            'rka_indicator_id' => $indicatorId,
            'rka_item_id' => $linkedItemId,
            'imported_rka_item_id' => $importedItemId,
            'section_type' => $sectionType,
            'section_name' => $sectionType === RkaAdjustment::SECTION_A
                ? 'A. Usulan Dipindah / Tidak Dianggarkan di Unit Ini'
                : 'B. Pos Dikelola Unit Ini untuk Unit Lain / Tambahan Review',
            'account_id' => $row['account_id'] ?? null,
            'description' => $row['uraian'],
            'destination_unit_id' => $sectionType === RkaAdjustment::SECTION_A && $actionType === RkaAdjustment::ACTION_MOVED
                ? ($row['destination_department_id'] ?? $targetDeptId)
                : null,
            'target_unit_id' => $sectionType === RkaAdjustment::SECTION_B
                ? $targetDeptId
                : null,
            'proposed_volume' => $row['volume'],
            'unit_symbol' => $row['satuan'],
            'unit_price' => $row['harga_satuan'],
            'proposed_amount' => $row['jumlah_usulan'],
            'approved_amount' => $approvedAmount,
            'action_type' => $actionType,
            'notes' => $notes !== '' ? $notes : null,
            'is_imported' => $importedItemId !== null,
        ];
    }

    /**
     * Memastikan item Section A memiliki record di rka_items milik RKA unit asal (tanpa duplikasi atau tebakan ambigu).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $itemIdMap
     * @param  array<string, RkaIndicator>  $indicatorCache
     */
    private function ensureSectionAOriginItemId(
        RkaHeader $header,
        array $row,
        string $actionType,
        string $notes,
        array &$itemIdMap,
        array &$indicatorCache,
    ): ?int {
        $adjRef = (string) ($row['adj_ref_id'] ?? '');
        if ($adjRef !== '') {
            if (isset($itemIdMap[$adjRef])) {
                return $itemIdMap[$adjRef];
            }

            $byAdjRef = RkaItem::query()
                ->where('rka_header_id', $header->id)
                ->where('source_ref_id', $adjRef)
                ->first();

            if ($byAdjRef) {
                $itemIdMap[$adjRef] = $byAdjRef->id;

                return $byAdjRef->id;
            }
        }

        if (($row['item_match_status'] ?? null) === 'MATCH_AMBIGUOUS') {
            return null;
        }

        $existingMatch = $this->findUniqueExistingItemId($header->id, $row, false);
        if ($existingMatch === -1) {
            // Ambigu (>1 kandidat di DB): jangan pilih acak dan jangan buat duplikat
            return null;
        }
        if ($existingMatch !== null) {
            return $existingMatch;
        }

        $accountId = $row['account_id'] ?? null;
        $uraian = trim((string) ($row['uraian'] ?? ''));
        if ($accountId === null || $uraian === '') {
            return null;
        }

        $kodeRekening = (string) ($row['kode_rekening'] ?? '');
        $formType = $this->extractFormType('', $kodeRekening);
        $indicator = $this->resolveIndicatorForSectionAItem($header, $formType, (int) $accountId, $indicatorCache);

        $volume = (float) ($row['volume'] ?? 1);
        $unitPrice = (float) ($row['harga_satuan'] ?? 0);
        $unitSymbol = trim((string) ($row['satuan'] ?? ''));

        $item = RkaItem::create([
            'source_ref_id' => $adjRef !== '' ? $adjRef : null,
            'rka_header_id' => $header->id,
            'rka_indicator_id' => $indicator->id,
            'account_id' => $accountId,
            'form_type' => $formType,
            'description' => $uraian,
            'group_header' => null,
            'proposed_volume' => $volume > 0 ? $volume : 1.0,
            'unit_symbol' => $unitSymbol !== '' ? $unitSymbol : 'Unit',
            'unit_price' => $unitPrice,
            'approved_volume' => 0,
            'approved_total' => 0,
            'status' => $actionType,
            'is_trimmed' => true,
            'is_imported' => false,
            'review_notes' => $notes !== '' ? $notes : null,
        ]);

        if ($adjRef !== '') {
            $itemIdMap[$adjRef] = $item->id;
        }

        return $item->id;
    }

    /**
     * @param  array<string, RkaIndicator>  $indicatorCache
     */
    private function resolveIndicatorForSectionAItem(
        RkaHeader $header,
        string $formType,
        int $accountId,
        array &$indicatorCache,
    ): RkaIndicator {
        $existingIndicators = $header->indicators()->with('account')->get();
        if ($existingIndicators->isNotEmpty()) {
            return $existingIndicators->first(fn (RkaIndicator $ind): bool => $ind->account?->code === $formType)
                ?? $existingIndicators->first();
        }

        return $this->resolveOrCreateIndicator($header, [
            'kelompok_belanja' => '',
            'kode_rekening' => $formType,
            'program_kegiatan' => $formType,
            'account_id' => $accountId,
        ], $indicatorCache);
    }

    /**
     * Pencocokan deterministik unik ke RkaItem eksisting di header target (-1 jika ambigu >1 kandidat).
     *
     * @param  array<string, mixed>  $row
     */
    private function findUniqueExistingItemId(int $headerId, array $row, bool $allowApprovedTotalMatch): ?int
    {
        $accountId = $row['account_id'] ?? null;
        $uraian = trim((string) ($row['uraian'] ?? ''));

        if ($accountId === null || $uraian === '') {
            return null;
        }

        $normDesc = mb_strtolower(preg_replace('/\s+/', ' ', $uraian) ?? $uraian);
        $usulan = round((float) ($row['jumlah_usulan'] ?? 0), 2);
        $disetujui = round((float) ($row['jumlah_disetujui'] ?? 0), 2);

        $matches = RkaItem::query()
            ->where('rka_header_id', $headerId)
            ->where('account_id', $accountId)
            ->get(['id', 'description', 'proposed_total', 'approved_total'])
            ->filter(function (RkaItem $item) use ($normDesc, $usulan, $disetujui, $allowApprovedTotalMatch): bool {
                $itemDesc = mb_strtolower(preg_replace('/\s+/', ' ', (string) $item->description) ?? '');
                if ($itemDesc !== $normDesc) {
                    return false;
                }

                if (abs((float) $item->proposed_total - $usulan) < 1.0) {
                    return true;
                }

                return $allowApprovedTotalMatch && abs((float) ($item->approved_total ?? 0) - $disetujui) < 1.0;
            })
            ->values();

        if ($matches->count() > 1) {
            return -1;
        }

        return $matches->count() === 1 ? $matches->first()->id : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, RkaIndicator>  $indicatorCache
     */
    private function resolveOrCreateIndicator(RkaHeader $header, array $row, array &$indicatorCache): RkaIndicator
    {
        $formType = $this->extractFormType($row['kelompok_belanja'], $row['kode_rekening']);
        $programKegiatan = $row['program_kegiatan'] ?: $formType;
        $cacheKey = $formType.'|'.$programKegiatan;

        if (isset($indicatorCache[$cacheKey])) {
            return $indicatorCache[$cacheKey];
        }

        $parts = explode(' - ', $programKegiatan, 2);
        $programName = trim($parts[0]) ?: 'Program RKA';
        $activityName = trim($parts[1] ?? $programName);

        $level1Account = Account::query()->where('code', $formType)->first()
            ?? Account::query()->where('id', $row['account_id'])->first();

        $indicator = RkaIndicator::firstOrCreate(
            [
                'rka_header_id' => $header->id,
                'account_id' => $level1Account->id,
                'program_name' => $programName,
                'activity_name' => $activityName,
            ],
            [
                'location' => 'Politeknik Kampar',
                'indicator_matrix' => [],
            ]
        );

        $indicatorCache[$cacheKey] = $indicator;

        return $indicator;
    }

    private function extractFormType(string $kelompokBelanja, string $kodeRekening): string
    {
        foreach (array_keys(RkaApprovedTemplateService::SPENDING_GROUPS) as $prefix) {
            if (str_starts_with($kelompokBelanja, $prefix) || str_starts_with($kodeRekening, $prefix)) {
                return $prefix;
            }
        }

        return '5.2';
    }

    /**
     * Menentukan pagu_budget dari sumber Excel asli untuk native workbook (Usulan Awal Unit pada RINGKASAN).
     *
     * @param  array<string, mixed>  $preview
     */
    private function resolvePaguBudgetForImport(array $preview): float
    {
        if (($preview['format'] ?? 'legacy') !== 'native') {
            return round((float) ($preview['summary']['total_proposed'] ?? 0), 2);
        }

        $paguAwal = $preview['summary']['pagu_awal_unit'] ?? $preview['ringkasan']['usulan_awal_unit'] ?? null;

        if ($paguAwal === null || ! is_numeric($paguAwal) || (float) $paguAwal < 0) {
            throw new InvalidArgumentException(
                'Nilai Usulan Awal Unit pada worksheet RINGKASAN tidak ditemukan atau tidak valid sehingga pagu_budget tidak dapat disimpan.'
            );
        }

        return round((float) $paguAwal, 2);
    }

    /**
     * Menentukan atau mengubah unit tujuan (Section A MOVED) / unit asal (Section B) secara manual pada sesi preview.
     *
     * @return array<string, mixed>
     */
    public function assignUnitOnPreview(string $previewToken, string $adjRefId, ?int $departmentId, User $user): array
    {
        $preview = Cache::get($this->previewCacheKey($previewToken));

        if (! is_array($preview)) {
            throw new InvalidArgumentException('Sesi preview tidak ditemukan atau telah kedaluwarsa. Silakan upload ulang file Excel.');
        }

        if ((int) ($preview['user_id'] ?? 0) !== $user->id) {
            throw new AuthorizationException('Sesi preview ini bukan milik akun Anda.');
        }

        $ownerDepartmentId = (int) ($preview['department_id'] ?? 0);
        $this->authorizeUserForDepartment($user, $ownerDepartmentId);

        if ($departmentId === null || $departmentId <= 0) {
            throw new InvalidArgumentException('Unit wajib dipilih dari daftar master unit.');
        }

        $selectedDepartment = Department::query()->find($departmentId);
        if (! $selectedDepartment) {
            throw new InvalidArgumentException('Unit yang dipilih tidak ditemukan pada master unit.');
        }

        if ($selectedDepartment->id === $ownerDepartmentId) {
            throw new InvalidArgumentException('Unit tujuan/asal tidak boleh sama dengan unit pemilik RKA ini.');
        }

        $adjustmentRows = is_array($preview['adjustment_rows'] ?? null) ? $preview['adjustment_rows'] : [];
        $updatedIndex = null;
        $oldUnitId = null;
        $oldUnitName = null;

        foreach ($adjustmentRows as $index => $row) {
            if ((string) ($row['adj_ref_id'] ?? '') !== $adjRefId) {
                continue;
            }

            $sectionType = strtoupper((string) ($row['section'] ?? $row['section_type'] ?? ''));
            $actionType = strtoupper((string) ($row['action_type'] ?? ''));

            if ($sectionType === 'A' && $actionType !== 'MOVED') {
                throw new InvalidArgumentException("Penyesuaian Section A dengan status {$actionType} tidak memerlukan penentuan unit tujuan.");
            }

            if ($sectionType !== 'A' && $sectionType !== 'B') {
                throw new InvalidArgumentException("Section penyesuaian {$sectionType} tidak valid untuk penentuan unit.");
            }

            if ($sectionType === 'A') {
                $oldUnitId = $row['destination_department_id'] ?? null;
                $oldUnitName = $row['destination_unit_name'] ?? $row['unit_tujuan'] ?? null;
                $row['destination_department_id'] = $selectedDepartment->id;
                $row['destination_unit_name'] = $selectedDepartment->name;
                $row['destination_unit_code'] = $selectedDepartment->code;
                $row['unit_tujuan'] = $selectedDepartment->name;
                $row['target_department_id'] = null;
                $row['target_unit_name'] = null;
                $row['target_unit_code'] = null;
                $row['validation_message'] = "Unit tujuan ditentukan manual: {$selectedDepartment->name} ({$selectedDepartment->code}).";
            } else {
                $oldUnitId = $row['target_department_id'] ?? null;
                $oldUnitName = $row['target_unit_name'] ?? $row['unit_asal'] ?? null;
                $row['target_department_id'] = $selectedDepartment->id;
                $row['target_unit_name'] = $selectedDepartment->name;
                $row['target_unit_code'] = $selectedDepartment->code;
                $row['destination_department_id'] = null;
                $row['destination_unit_name'] = null;
                $row['destination_unit_code'] = null;
                $row['validation_message'] = "Unit asal ditentukan manual: {$selectedDepartment->name} ({$selectedDepartment->code}).";
            }

            $row['unit_source'] = 'MANUAL';
            $row['relation_status'] = 'UNIT TELAH DITENTUKAN';
            $row['validation_status'] = 'SESUAI';
            $adjustmentRows[$index] = $row;
            $updatedIndex = $index;
            break;
        }

        if ($updatedIndex === null) {
            throw new InvalidArgumentException('Baris penyesuaian tidak ditemukan pada sesi preview.');
        }

        $preview['adjustment_rows'] = $adjustmentRows;
        $preview['summary'] = array_merge(
            is_array($preview['summary'] ?? null) ? $preview['summary'] : [],
            $this->summarizeUnitAssignments($adjustmentRows, $ownerDepartmentId),
        );

        Cache::put($this->previewCacheKey($previewToken), $preview, now()->addMinutes(30));

        $updatedRow = $adjustmentRows[$updatedIndex];
        $updatedSection = strtoupper((string) ($updatedRow['section'] ?? $updatedRow['section_type'] ?? 'A'));
        $sectionLabel = $updatedSection === 'A' ? 'Section A (Unit Tujuan)' : 'Section B (Unit Asal/Sumber)';
        $itemDescription = (string) ($updatedRow['uraian'] ?? $updatedRow['description'] ?? '-');

        $this->activityLogService->log(
            action: RkaLog::ACTION_APPROVED_IMPORT_UNIT_ASSIGNED,
            headerId: isset($preview['rka_header_id']) ? (int) $preview['rka_header_id'] : null,
            description: "Penentuan manual {$sectionLabel} pada preview import: {$itemDescription} -> {$selectedDepartment->name} ({$selectedDepartment->code}).",
            oldValues: [
                'adj_ref_id' => $adjRefId,
                'section_type' => $updatedSection,
                'unit_id' => $oldUnitId,
                'unit_name' => $oldUnitName,
            ],
            newValues: [
                'adj_ref_id' => $adjRefId,
                'section_type' => $updatedSection,
                'action_type' => $updatedRow['action_type'] ?? null,
                'unit_id' => $selectedDepartment->id,
                'unit_name' => $selectedDepartment->name,
                'unit_code' => $selectedDepartment->code,
                'department_id' => $ownerDepartmentId,
            ],
            userId: $user->id,
        );

        return $preview;
    }

    /**
     * Memastikan seluruh penyesuaian yang mewajibkan relasi unit sudah terisi sebelum finalisasi import.
     *
     * @param  array<string, mixed>  $preview
     */
    public function validateAdjustmentUnitsBeforeFinalize(array $preview): void
    {
        if (($preview['format'] ?? 'legacy') !== 'native') {
            return;
        }

        $ownerDepartmentId = (int) ($preview['department_id'] ?? 0);
        $adjustmentRows = is_array($preview['adjustment_rows'] ?? null) ? $preview['adjustment_rows'] : [];
        $summary = $this->summarizeUnitAssignments($adjustmentRows, $ownerDepartmentId);

        if ($summary['incomplete_unit_count'] > 0) {
            $firstMissing = $summary['missing_unit_items'][0] ?? null;
            $detail = $firstMissing !== null
                ? " Contoh: {$firstMissing['section_label']} - {$firstMissing['description']}."
                : '';

            throw new InvalidArgumentException(
                "Finalisasi import ditolak: masih ada {$summary['incomplete_unit_count']} baris penyesuaian yang belum memiliki unit tujuan/asal yang valid.{$detail}"
            );
        }
    }

    /**
     * Menghitung ringkasan kelengkapan relasi unit pada daftar penyesuaian preview.
     *
     * @param  array<int, array<string, mixed>>  $adjustmentRows
     * @return array{
     *     incomplete_unit_count: int,
     *     complete_unit_count: int,
     *     ambiguous_unit_count: int,
     *     not_required_unit_count: int,
     *     missing_unit_items: array<int, array<string, mixed>>
     * }
     */
    private function summarizeUnitAssignments(array $adjustmentRows, int $ownerDepartmentId): array
    {
        $incomplete = 0;
        $complete = 0;
        $ambiguous = 0;
        $notRequired = 0;
        $missingItems = [];

        foreach ($adjustmentRows as $row) {
            $sectionType = strtoupper((string) ($row['section'] ?? $row['section_type'] ?? ''));
            $actionType = strtoupper((string) ($row['action_type'] ?? ''));
            $description = (string) ($row['uraian'] ?? $row['description'] ?? '-');
            $relStatus = (string) ($row['relation_status'] ?? '');

            if ($relStatus === 'UNIT AMBIGU' || $relStatus === 'HEADER AMBIGU') {
                $ambiguous++;
            }

            if ($sectionType === 'A') {
                if ($actionType !== 'MOVED') {
                    $notRequired++;

                    continue;
                }

                $destId = isset($row['destination_department_id']) ? (int) $row['destination_department_id'] : null;
                if ($destId !== null && $destId > 0 && $destId !== $ownerDepartmentId) {
                    $complete++;
                } else {
                    $incomplete++;
                    $missingItems[] = [
                        'adj_ref_id' => $row['adj_ref_id'] ?? '',
                        'section_type' => 'A',
                        'section_label' => 'Section A (MOVED)',
                        'action_type' => $actionType,
                        'description' => $description,
                        'reason' => $destId === $ownerDepartmentId
                            ? 'Unit tujuan tidak boleh sama dengan unit pemilik RKA.'
                            : ($relStatus === 'UNIT AMBIGU'
                                ? 'Unit tujuan dari Excel ambigu, pilih ulang dari master unit.'
                                : ($relStatus === 'UNIT TIDAK VALID'
                                    ? 'Unit tujuan dari Excel tidak valid, pilih ulang dari master unit.'
                                    : 'Unit tujuan belum ditentukan.')),
                    ];
                }

                continue;
            }

            if ($sectionType === 'B') {
                $targetId = isset($row['target_department_id']) ? (int) $row['target_department_id'] : null;
                if ($targetId !== null && $targetId > 0 && $targetId !== $ownerDepartmentId) {
                    $complete++;
                } else {
                    $incomplete++;
                    $missingItems[] = [
                        'adj_ref_id' => $row['adj_ref_id'] ?? '',
                        'section_type' => 'B',
                        'section_label' => 'Section B',
                        'action_type' => $actionType,
                        'description' => $description,
                        'reason' => $targetId === $ownerDepartmentId
                            ? 'Unit asal tidak boleh sama dengan unit pemilik RKA.'
                            : ($relStatus === 'UNIT AMBIGU'
                                ? 'Unit asal dari Excel ambigu, pilih ulang dari master unit.'
                                : ($relStatus === 'UNIT TIDAK VALID'
                                    ? 'Unit asal dari Excel tidak valid, pilih ulang dari master unit.'
                                    : 'Unit asal/sumber belum ditentukan.')),
                    ];
                }
            }
        }

        return [
            'incomplete_unit_count' => $incomplete,
            'complete_unit_count' => $complete,
            'ambiguous_unit_count' => $ambiguous,
            'not_required_unit_count' => $notRequired,
            'missing_unit_items' => $missingItems,
        ];
    }

    private function previewCacheKey(string $token): string
    {
        return "approved_rka_preview:{$token}";
    }
}
