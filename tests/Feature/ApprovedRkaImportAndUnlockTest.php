<?php

use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\RkaUnlockRequest;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use App\Services\ApprovedRkaImport\RkaUnlockRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeApprovedImportTestContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohon = User::factory()->create(['name' => 'Pemohon '.$suffix]);
    $otherPemohon = User::factory()->create(['name' => 'Other Pemohon '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Keuangan '.$suffix]);
    $wadir = User::factory()->create(['name' => 'Wadir Keuangan '.$suffix]);
    $direktur = User::factory()->create(['name' => 'Direktur '.$suffix]);

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $wadirRole = Role::firstOrCreate(['name' => 'wadir_keuangan', 'guard_name' => 'web']);
    $direkturRole = Role::firstOrCreate(['name' => 'direktur', 'guard_name' => 'web']);

    Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);

    $pemohon->assignRole($pemohonRole);
    $otherPemohon->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);
    $wadir->assignRole($wadirRole);
    $direktur->assignRole($direkturRole);

    $dept = Department::create([
        'type' => 'UPT',
        'code' => 'IMP1-'.$suffix,
        'level_code' => '2',
        'name' => 'UPT Import '.$suffix,
    ]);

    $otherDept = Department::create([
        'type' => 'PRODI',
        'code' => 'IMP2-'.$suffix,
        'level_code' => '3',
        'name' => 'Prodi Lain '.$suffix,
    ]);

    $dept->users()->attach($pemohon->id);
    $otherDept->users()->attach($otherPemohon->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Import Phase '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $pemohon->id,
    ]);

    $programAccount = Account::create([
        'code' => 'PRG-'.$suffix,
        'name' => 'Program Operasional '.$suffix,
        'category' => 'BELANJA',
        'is_active' => true,
    ]);

    $itemAccount = Account::create([
        'code' => '5211-'.$suffix,
        'name' => 'Belanja Barang '.$suffix,
        'category' => 'BELANJA',
        'is_active' => true,
    ]);

    RkaSetting::create([
        'setting_name' => 'Setting Import '.$suffix,
        'director_user_id' => $direktur->id,
        'director_name' => $direktur->name,
        'director_nrp' => 'NRP-888',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);

    return compact(
        'suffix',
        'pemohon',
        'otherPemohon',
        'keuangan',
        'wadir',
        'direktur',
        'dept',
        'otherDept',
        'schedule',
        'programAccount',
        'itemAccount',
    );
}

/**
 * @param  array<int, array<int, mixed>>  $belanjaRows
 * @param  array<int, array<int, mixed>>  $adjustmentRows
 */
function buildApprovedRkaExcelFile(
    array $belanjaRows,
    array $adjustmentRows = [],
    bool $includeAdjustmentSheet = true,
): string {
    $spreadsheet = new Spreadsheet;

    $belanjaSheet = $spreadsheet->getActiveSheet();
    $belanjaSheet->setTitle('BELANJA');
    $belanjaSheet->fromArray(ApprovedRkaImportService::BELANJA_COLUMNS, null, 'A1');
    if (count($belanjaRows) > 0) {
        $belanjaSheet->fromArray($belanjaRows, null, 'A2');
    }

    if ($includeAdjustmentSheet) {
        $adjSheet = $spreadsheet->createSheet();
        $adjSheet->setTitle('DAFTAR PENYESUAIAN');
        $adjSheet->fromArray(ApprovedRkaImportService::ADJUSTMENT_COLUMNS, null, 'A1');
        if (count($adjustmentRows) > 0) {
            $adjSheet->fromArray($adjustmentRows, null, 'A2');
        }
    }

    $tmpPath = sys_get_temp_dir().'/approved_rka_test_'.uniqid().'.xlsx';
    (new Xlsx($spreadsheet))->save($tmpPath);

    return $tmpPath;
}

it('allows pemohon to preview read-only and execute initial approved RKA import with locked APPROVED status', function () {
    $ctx = makeApprovedImportTestContext();
    $service = app(ApprovedRkaImportService::class);

    $filePath = buildApprovedRkaExcelFile(
        belanjaRows: [
            [
                101,
                'OPERASIONAL',
                'Program Peningkatan Layanan',
                $ctx['itemAccount']->code,
                $ctx['itemAccount']->name,
                'Pengadaan ATK',
                'Kertas A4',
                10,
                'Rim',
                50000,
                500000,
                8,
                50000,
                400000,
                'TRIMMED',
                'UNIT_ASAL',
                'Penyesuaian volume oleh keuangan',
            ],
        ],
        adjustmentRows: [
            [
                201,
                101,
                null,
                'A',
                RkaAdjustment::ACTION_PENDING,
                $ctx['itemAccount']->code,
                $ctx['itemAccount']->name,
                'Menunggu verifikasi tambahan',
                $ctx['dept']->name,
                $ctx['otherDept']->name,
                2,
                'Rim',
                50000,
                100000,
                0,
                'Pending di unit asal',
                $ctx['keuangan']->name,
            ],
        ],
    );

    $headersBefore = RkaHeader::count();

    $preview = $service->preview(
        filePath: $filePath,
        originalFilename: 'rka_approved.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['summary']['total_belanja'])->toBe(1)
        ->and($preview['summary']['total_adjustment'])->toBe(1)
        ->and((float) $preview['summary']['total_approved'])->toBe(400000.0)
        ->and(RkaHeader::count())->toBe($headersBefore);

    $batch = $service->executeImport($preview['token'], $ctx['pemohon']);
    $header = RkaHeader::findOrFail($batch->rka_header_id);

    expect($batch->import_type)->toBe(RkaApprovedImportBatch::TYPE_INITIAL)
        ->and($batch->status)->toBe(RkaApprovedImportBatch::STATUS_SUCCESS)
        ->and($header->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($header->is_locked)->toBeTrue()
        ->and((float) $header->pagu_budget)->toBe(500000.0);

    $item = RkaItem::where('rka_header_id', $header->id)->firstOrFail();
    expect($item->source_ref_id)->toBe('101')
        ->and($item->status)->toBe('TRIMMED')
        ->and((float) $item->approved_total)->toBe(400000.0);

    $adjustment = RkaAdjustment::where('rka_header_id', $header->id)->firstOrFail();
    expect($adjustment->source_ref_id)->toBe('201')
        ->and($adjustment->section_type)->toBe(RkaAdjustment::SECTION_A)
        ->and($adjustment->action_type)->toBe(RkaAdjustment::ACTION_PENDING)
        ->and(DepartmentBudget::where('department_id', $ctx['otherDept']->id)->where('schedule_id', $ctx['schedule']->id)->exists())->toBeFalse();

    $executedLogCount = RkaLog::where('rka_header_id', $header->id)
        ->where('action', RkaLog::ACTION_APPROVED_IMPORT_EXECUTED)
        ->count();
    expect($executedLogCount)->toBe(1);

    @unlink($filePath);
});

it('blocks unauthorized users and other-unit pemohon from previewing or importing approved RKA', function () {
    $ctx = makeApprovedImportTestContext();
    $service = app(ApprovedRkaImportService::class);

    $filePath = buildApprovedRkaExcelFile([
        [
            101, 'OPERASIONAL', 'Program A', $ctx['itemAccount']->code, $ctx['itemAccount']->name,
            null, 'Item A', 1, 'Unit', 100000, 100000, 1, 100000, 100000, 'APPROVED', 'UNIT_ASAL', null,
        ],
    ]);

    expect(fn () => $service->preview(
        filePath: $filePath,
        originalFilename: 'rka.xlsx',
        user: $ctx['otherPemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
    ))->toThrow(AuthorizationException::class);

    expect(fn () => $service->preview(
        filePath: $filePath,
        originalFilename: 'rka.xlsx',
        user: $ctx['keuangan'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
    ))->toThrow(AuthorizationException::class);

    @unlink($filePath);
});

it('rejects excel missing DAFTAR PENYESUAIAN, invalid account code, or duplicate ITEM_REF_ID', function () {
    $ctx = makeApprovedImportTestContext();
    $service = app(ApprovedRkaImportService::class);

    $missingSheetFile = buildApprovedRkaExcelFile([], [], includeAdjustmentSheet: false);
    expect(fn () => $service->preview(
        filePath: $missingSheetFile,
        originalFilename: 'missing_sheet.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
    ))->toThrow(InvalidArgumentException::class);
    @unlink($missingSheetFile);

    $invalidFile = buildApprovedRkaExcelFile([
        [
            101, 'OPERASIONAL', 'Program A', '9999-TIDAK-ADA', 'Invalid Account',
            null, 'Item A', 1, 'Unit', 100000, 100000, 1, 100000, 100000, 'APPROVED', 'UNIT_ASAL', null,
        ],
        [
            101, 'OPERASIONAL', 'Program A', $ctx['itemAccount']->code, $ctx['itemAccount']->name,
            null, 'Item Duplikat', 1, 'Unit', 100000, 100000, 1, 100000, 100000, 'APPROVED', 'UNIT_ASAL', null,
        ],
    ]);

    $preview = $service->preview(
        filePath: $invalidFile,
        originalFilename: 'invalid.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['has_fatal_errors'])->toBeTrue()
        ->and(fn () => $service->executeImport($preview['token'], $ctx['pemohon']))
        ->toThrow(InvalidArgumentException::class);

    @unlink($invalidFile);
});

it('enforces lock on approved RKA and supports unlock request, finance approval/rejection, and controlled reimport', function () {
    $ctx = makeApprovedImportTestContext();
    $importService = app(ApprovedRkaImportService::class);
    $unlockService = app(RkaUnlockRequestService::class);

    $initialFile = buildApprovedRkaExcelFile([
        [
            501, 'OPERASIONAL', 'Program Awal', $ctx['itemAccount']->code, $ctx['itemAccount']->name,
            null, 'Printer Laser', 2, 'Unit', 2000000, 4000000, 2, 2000000, 4000000, 'APPROVED', 'UNIT_ASAL', null,
        ],
    ]);

    $initialPreview = $importService->preview(
        filePath: $initialFile,
        originalFilename: 'initial.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
    );
    $initialBatch = $importService->executeImport($initialPreview['token'], $ctx['pemohon']);
    $header = RkaHeader::findOrFail($initialBatch->rka_header_id);
    @unlink($initialFile);

    // Locked RKA cannot be edited or deleted by pemohon
    expect($ctx['pemohon']->can('update', $header))->toBeFalse()
        ->and($ctx['pemohon']->can('delete', $header))->toBeFalse();

    // Pemohon submits unlock request; duplicate pending request is blocked
    $unlockRequest = $unlockService->submitRequest(
        header: $header,
        user: $ctx['pemohon'],
        reason: 'Nominal harga satuan Printer Laser keliru pada sheet BELANJA.',
        errorDescription: 'Seharusnya Rp 2.500.000 per unit.',
    );

    expect($unlockRequest->status)->toBe(RkaUnlockRequest::STATUS_PENDING)
        ->and(fn () => $unlockService->submitRequest($header, $ctx['pemohon'], 'Duplikat'))
        ->toThrow(InvalidArgumentException::class);

    // Non-finance cannot approve or reject
    expect(fn () => $unlockService->approveRequest($unlockRequest, $ctx['pemohon']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $unlockService->rejectRequest($unlockRequest, $ctx['direktur'], 'Ditolak'))
        ->toThrow(AuthorizationException::class);

    // Finance rejects without notes -> fails; rejects with notes -> RKA remains APPROVED & locked
    expect(fn () => $unlockService->rejectRequest($unlockRequest, $ctx['keuangan'], '   '))
        ->toThrow(InvalidArgumentException::class);

    $rejectedRequest = $unlockService->rejectRequest($unlockRequest, $ctx['keuangan'], 'Bukti pendukung belum lengkap.');
    $header->refresh();
    expect($rejectedRequest->status)->toBe(RkaUnlockRequest::STATUS_REJECTED)
        ->and($header->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($header->is_locked)->toBeTrue();

    // Pemohon submits new unlock request, finance approves it -> status stays APPROVED (never DRAFT)
    $secondRequest = $unlockService->submitRequest(
        header: $header,
        user: $ctx['pemohon'],
        reason: 'Perbaikan setelah melengkapi nota koreksi.',
    );

    $approvedRequest = $unlockService->approveRequest(
        request: $secondRequest,
        financeUser: $ctx['wadir'],
        notes: 'Disetujui untuk satu kali import ulang.',
        validHours: 24,
    );
    $header->refresh();

    expect($approvedRequest->status)->toBe(RkaUnlockRequest::STATUS_APPROVED)
        ->and($approvedRequest->isReimportActive())->toBeTrue()
        ->and($header->status)->toBe(RkaHeader::STATUS_APPROVED);

    // Controlled Reimport reconciles by source_ref_id, keeps old batch, marks request USED, and re-locks RKA
    $reimportFile = buildApprovedRkaExcelFile([
        [
            501, 'OPERASIONAL', 'Program Awal', $ctx['itemAccount']->code, $ctx['itemAccount']->name,
            null, 'Printer Laser (Revisi)', 2, 'Unit', 2500000, 5000000, 2, 2500000, 5000000, 'APPROVED', 'UNIT_ASAL', 'Revisi disetujui',
        ],
        [
            502, 'OPERASIONAL', 'Program Awal', $ctx['itemAccount']->code, $ctx['itemAccount']->name,
            null, 'Toner Cadangan', 4, 'Buah', 250000, 1000000, 4, 250000, 1000000, 'APPROVED', 'UNIT_ASAL', null,
        ],
    ]);

    $reimportPreview = $importService->preview(
        filePath: $reimportFile,
        originalFilename: 'reimport.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_REIMPORT,
        targetHeader: $header,
        unlockRequestId: $approvedRequest->id,
    );

    $reimportBatch = $importService->executeReimport(
        previewToken: $reimportPreview['token'],
        header: $header,
        unlockRequest: $approvedRequest,
        user: $ctx['pemohon'],
    );
    @unlink($reimportFile);

    $header->refresh();
    $approvedRequest->refresh();

    expect($reimportBatch->import_type)->toBe(RkaApprovedImportBatch::TYPE_REIMPORT)
        ->and($header->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($header->is_locked)->toBeTrue()
        ->and((float) $header->pagu_budget)->toBe(6000000.0)
        ->and(RkaItem::where('rka_header_id', $header->id)->count())->toBe(2)
        ->and(RkaApprovedImportBatch::where('rka_header_id', $header->id)->count())->toBe(2)
        ->and($approvedRequest->status)->toBe(RkaUnlockRequest::STATUS_USED)
        ->and($approvedRequest->reimport_batch_id)->toBe($reimportBatch->id)
        ->and($approvedRequest->isReimportActive())->toBeFalse();

    // Token reuse is blocked
    expect(fn () => $importService->executeReimport(
        previewToken: $reimportPreview['token'],
        header: $header,
        unlockRequest: $approvedRequest,
        user: $ctx['pemohon'],
    ))->toThrow(InvalidArgumentException::class);
});

it('allows pemohon to download blank approved RKA template matching PHASE 2 and PHASE 3 without mutating data or logs', function () {
    $ctx = makeApprovedImportTestContext();

    $headersBefore = RkaHeader::count();
    $batchesBefore = RkaApprovedImportBatch::count();
    $logsBefore = RkaLog::count();

    // Unauthorized roles cannot download via direct endpoint
    $this->actingAs($ctx['keuangan'])
        ->get(route('rka-headers.import-approved.template'))
        ->assertForbidden();

    $this->actingAs($ctx['direktur'])
        ->get(route('rka-headers.import-approved.template'))
        ->assertForbidden();

    // Pemohon downloads template
    $response = $this->actingAs($ctx['pemohon'])
        ->get(route('rka-headers.import-approved.template'));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('TEMPLATE-IMPORT-APPROVED-RKA.xlsx');

    // Verify no RKA data, batches, or logs were created
    expect(RkaHeader::count())->toBe($headersBefore)
        ->and(RkaApprovedImportBatch::count())->toBe($batchesBefore)
        ->and(RkaLog::count())->toBe($logsBefore);

    // Load downloaded workbook and verify valid ZIP/XLSX container, not HTML/JSON, sheets & exact headers
    $filePath = $response->getFile()->getPathname();
    $binary = file_get_contents($filePath);
    expect($binary)->not->toBeFalse()
        ->and(substr($binary, 0, 2))->toBe('PK')
        ->and(str_contains($binary, '<html'))->toBeFalse()
        ->and(str_starts_with(trim($binary), '{'))->toBeFalse();

    $spreadsheet = IOFactory::load($filePath);

    expect($spreadsheet->getSheetCount())->toBe(2)
        ->and($spreadsheet->getSheetNames())->toBe(['DAFTAR PENYESUAIAN', 'BELANJA']);

    $adjSheet = $spreadsheet->getSheetByName('DAFTAR PENYESUAIAN');
    $belanjaSheet = $spreadsheet->getSheetByName('BELANJA');

    $adjHeaders = array_values($adjSheet->rangeToArray('A6:Q6')[0]);
    $belanjaHeaders = array_values($belanjaSheet->rangeToArray('A11:Q11')[0]);

    expect($adjHeaders)->toBe(ApprovedRkaImportService::ADJUSTMENT_COLUMNS)
        ->and($belanjaHeaders)->toBe(ApprovedRkaImportService::BELANJA_COLUMNS);

    // Verify compatibility with importer by filling 1 valid item row on BELANJA and running preview()
    $belanjaSheet->fromArray([
        '901',
        '5.2 Belanja Barang dan Jasa',
        'Program Layanan',
        $ctx['itemAccount']->code,
        $ctx['itemAccount']->name,
        '5.2.1 Belanja Barang',
        'Kertas HVS',
        5,
        'Rim',
        60000,
        300000,
        5,
        60000,
        300000,
        'APPROVED',
        'USULAN_ASLI',
        'Sesuai',
    ], null, 'A12');

    $filledTmp = sys_get_temp_dir().'/filled_template_'.uniqid().'.xlsx';
    (new Xlsx($spreadsheet))->save($filledTmp);

    $preview = app(ApprovedRkaImportService::class)->preview(
        filePath: $filledTmp,
        originalFilename: 'TEMPLATE-IMPORT-APPROVED-RKA.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['summary']['total_belanja'])->toBe(1)
        ->and((float) $preview['summary']['total_approved'])->toBe(300000.0);

    @unlink($filledTmp);
});
