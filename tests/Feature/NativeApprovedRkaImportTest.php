<?php

use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaApproval;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\RkaUnlockRequest;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use App\Services\ApprovedRkaImport\DepartmentResolver;
use App\Services\ApprovedRkaImport\RkaUnlockRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeNativeImportTestContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $p3mDept = Department::where('code', 'P3M')->firstOrFail();
    $tifDept = Department::where('code', 'PRODI-TIF')->firstOrFail();

    $ictUser = User::factory()->create(['name' => 'Pemohon ICT '.$suffix]);
    $p3mUser = User::factory()->create(['name' => 'Pemohon P3M '.$suffix]);
    $tifUser = User::factory()->create(['name' => 'Pemohon TIF '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Keuangan '.$suffix]);

    $ictUser->assignRole($pemohonRole);
    $p3mUser->assignRole($pemohonRole);
    $tifUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);

    $ictDept->users()->attach($ictUser->id);
    $p3mDept->users()->attach($p3mUser->id);
    $tifDept->users()->attach($tifUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'September 2026 – Agustus 2027',
        'phase_name' => 'Native Import '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $ictUser->id,
    ]);

    RkaSetting::create([
        'setting_name' => 'Setting Native '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    return compact(
        'ictDept',
        'p3mDept',
        'tifDept',
        'ictUser',
        'p3mUser',
        'tifUser',
        'keuangan',
        'schedule',
    );
}

it('previews and imports real 05_RKA_2026-2027_UPT_ICT.xlsx natively into locked APPROVED RKA and supports controlled reimport', function () {
    $ctx = makeNativeImportTestContext();
    $importService = app(ApprovedRkaImportService::class);
    $unlockService = app(RkaUnlockRequestService::class);
    $filePath = base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx');

    $headersBefore = RkaHeader::count();
    $approvalsBefore = RkaApproval::count();

    $preview = $importService->preview(
        filePath: $filePath,
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['format'])->toBe('native')
        ->and($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['summary']['total_belanja'])->toBe(33)
        ->and($preview['summary']['total_adjustment'])->toBe(10)
        ->and((float) $preview['summary']['total_proposed'])->toBe(174202000.0)
        ->and((float) $preview['summary']['total_approved'])->toBe(173902000.0)
        ->and((float) $preview['summary']['pagu_awal_unit'])->toBe(156583000.0)
        ->and($preview['summary']['pagu_awal_source'])->toBe('RINGKASAN!C6')
        ->and($preview['summary']['pagu_validation_status'])->toBe('VALID')
        ->and((float) $preview['ringkasan']['usulan_awal_unit'])->toBe(156583000.0)
        ->and($preview['identitas']['pagu_status'])->toBe('PAGU BELUM DIISI')
        ->and(RkaHeader::count())->toBe($headersBefore);

    $batch = $importService->executeImport($preview['token'], $ctx['ictUser']);
    $header = RkaHeader::findOrFail($batch->rka_header_id);

    expect($header->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($header->is_locked)->toBeTrue()
        ->and((float) $header->pagu_budget)->toBe(156583000.0)
        ->and((float) $header->pagu_budget)->not->toBe((float) $preview['summary']['total_proposed'])
        ->and((float) $header->pagu_budget)->not->toBe((float) $preview['summary']['total_approved'])
        ->and(RkaItem::where('rka_header_id', $header->id)->count())->toBe(33)
        ->and(RkaAdjustment::where('rka_header_id', $header->id)->count())->toBe(10)
        ->and(RkaApproval::count())->toBe($approvalsBefore)
        ->and(RkaLog::where('rka_header_id', $header->id)->where('action', RkaLog::ACTION_APPROVED_IMPORT_EXECUTED)->count())->toBe(1);

    // Verifikasi relasi Section B UPT ICT: target_unit_id terpetakan ke P3M, UPT-MEDIA, PRODI-TIF, PRODI-ABI
    $ictAdjustments = RkaAdjustment::where('rka_header_id', $header->id)->get();
    $mediaDept = Department::where('code', 'UPT-MEDIA')->firstOrFail();
    $abiDept = Department::where('code', 'PRODI-ABI')->firstOrFail();

    expect($ictAdjustments->where('section_type', RkaAdjustment::SECTION_B)->count())->toBe(10)
        ->and($ictAdjustments->where('action_type', RkaAdjustment::ACTION_SHARED_COST)->count())->toBe(10)
        ->and($ictAdjustments->whereNotNull('destination_unit_id')->count())->toBe(0)
        ->and($ictAdjustments->whereNotNull('adjusted_by')->count())->toBe(0)
        ->and($ictAdjustments->where('target_unit_id', $ctx['p3mDept']->id)->count())->toBe(2)
        ->and($ictAdjustments->where('target_unit_id', $mediaDept->id)->count())->toBe(3)
        ->and($ictAdjustments->where('target_unit_id', $ctx['tifDept']->id)->count())->toBe(3)
        ->and($ictAdjustments->where('target_unit_id', $abiDept->id)->count())->toBe(2)
        ->and($ictAdjustments->whereNotNull('imported_rka_item_id')->count())->toBe(10)
        ->and($ictAdjustments->where('is_imported', true)->count())->toBe(10);

    // Verifikasi visibilitas antarunit setelah APPROVED: P3M melihat 2 alokasi Section B untuk unitnya, tidak melihat milik ABI/TIF/Media
    $visibleToP3m = RkaAdjustment::visibleToUser($ctx['p3mUser'])
        ->where('rka_header_id', $header->id)
        ->get();
    expect($visibleToP3m->count())->toBe(2)
        ->and($visibleToP3m->every(fn (RkaAdjustment $a): bool => (int) $a->target_unit_id === (int) $ctx['p3mDept']->id))->toBeTrue();

    $abiAdj = $ictAdjustments->firstWhere('target_unit_id', $abiDept->id);
    expect($ctx['p3mUser']->can('view', $abiAdj))->toBeFalse()
        ->and($ctx['p3mUser']->can('view', $visibleToP3m->first()))->toBeTrue();

    // Data belum APPROVED tidak terlihat lintas unit
    $draftHeader = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $abiDept->id,
        'setting_id' => RkaSetting::latest('id')->value('id'),
        'unit_head_user_id' => $ctx['ictUser']->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'is_locked' => false,
    ]);
    $draftAdj = RkaAdjustment::create([
        'rka_header_id' => $draftHeader->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'section_name' => 'Draft Section B',
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        'description' => 'Draft belum approved',
        'target_unit_id' => $ctx['p3mDept']->id,
        'proposed_amount' => 100000,
        'approved_amount' => 100000,
    ]);
    expect($ctx['p3mUser']->can('view', $draftAdj))->toBeFalse()
        ->and(RkaAdjustment::visibleToUser($ctx['p3mUser'])->where('id', $draftAdj->id)->exists())->toBeFalse();

    // Controlled Unlock & Reimport on native workbook
    $unlock = $unlockService->submitRequest($header, $ctx['ictUser'], 'Koreksi workbook UPT ICT');
    $approvedUnlock = $unlockService->approveRequest($unlock, $ctx['keuangan'], 'Silakan reimport');

    $reimportPreview = $importService->preview(
        filePath: $filePath,
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_REIMPORT,
        targetHeader: $header,
        unlockRequestId: $approvedUnlock->id,
    );

    $reimportBatch = $importService->executeReimport(
        previewToken: $reimportPreview['token'],
        header: $header,
        unlockRequest: $approvedUnlock,
        user: $ctx['ictUser'],
    );

    $header->refresh();
    $approvedUnlock->refresh();

    expect($reimportBatch->import_type)->toBe(RkaApprovedImportBatch::TYPE_REIMPORT)
        ->and((float) $header->pagu_budget)->toBe(156583000.0)
        ->and(RkaItem::where('rka_header_id', $header->id)->count())->toBe(33)
        ->and(RkaAdjustment::where('rka_header_id', $header->id)->count())->toBe(10)
        ->and(RkaApprovedImportBatch::where('rka_header_id', $header->id)->count())->toBe(2)
        ->and($approvedUnlock->status)->toBe(RkaUnlockRequest::STATUS_USED)
        ->and($header->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($header->is_locked)->toBeTrue();
});

it('previews and imports real 10_RKA_2026-2027_P3M.xlsx natively', function () {
    $ctx = makeNativeImportTestContext();
    $importService = app(ApprovedRkaImportService::class);
    $filePath = base_path('data-rka/10_RKA_2026-2027_P3M.xlsx');

    $preview = $importService->preview(
        filePath: $filePath,
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['p3mDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['summary']['total_belanja'])->toBe(30)
        ->and($preview['summary']['total_adjustment'])->toBe(23)
        ->and((float) $preview['summary']['total_proposed'])->toBe(408500000.0)
        ->and((float) $preview['summary']['total_approved'])->toBe(340600000.0)
        ->and((float) $preview['summary']['pagu_awal_unit'])->toBe(496090000.0)
        ->and($preview['summary']['pagu_awal_source'])->toBe('RINGKASAN!C6')
        ->and($preview['summary']['pagu_validation_status'])->toBe('VALID');

    foreach ($preview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED') {
            $preview = $importService->assignUnitOnPreview(
                previewToken: $preview['token'],
                adjRefId: $row['adj_ref_id'],
                departmentId: $ctx['ictDept']->id,
                user: $ctx['p3mUser'],
            );
        }
    }

    $batch = $importService->executeImport($preview['token'], $ctx['p3mUser']);
    $header = RkaHeader::findOrFail($batch->rka_header_id);

    expect($header->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($header->is_locked)->toBeTrue()
        ->and((float) $header->pagu_budget)->toBe(496090000.0)
        ->and((float) $header->pagu_budget)->not->toBe((float) $preview['summary']['total_proposed'])
        ->and((float) $header->pagu_budget)->not->toBe((float) $preview['summary']['total_approved'])
        ->and(RkaItem::where('rka_header_id', $header->id)->count())->toBe(30)
        ->and(RkaAdjustment::where('rka_header_id', $header->id)->count())->toBe(23);

    $p3mAdjs = RkaAdjustment::where('rka_header_id', $header->id)->get();
    expect($p3mAdjs->where('section_type', RkaAdjustment::SECTION_A)->count())->toBe(23)
        ->and($p3mAdjs->whereNotNull('destination_unit_id')->count())->toBe(23)
        ->and($p3mAdjs->whereNotNull('target_unit_id')->count())->toBe(0)
        ->and($p3mAdjs->whereNotNull('imported_rka_item_id')->count())->toBe(0)
        ->and($p3mAdjs->where('is_imported', true)->count())->toBe(0)
        ->and($p3mAdjs->whereNotNull('adjusted_by')->count())->toBe(0);
});

it('previews and imports real 19_RKA_2026-2027_Prodi_TIF.xlsx natively and blocks unit mismatch', function () {
    $ctx = makeNativeImportTestContext();
    $importService = app(ApprovedRkaImportService::class);
    $tifPath = base_path('data-rka/19_RKA_2026-2027_Prodi_TIF.xlsx');
    $ictPath = base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx');

    // Unit manipulation: TIF user trying to upload ICT workbook is blocked
    expect(fn () => $importService->preview(
        filePath: $ictPath,
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['tifUser'],
        departmentId: $ctx['tifDept']->id,
        scheduleId: $ctx['schedule']->id,
    ))->toThrow(AuthorizationException::class);

    $preview = $importService->preview(
        filePath: $tifPath,
        originalFilename: '19_RKA_2026-2027_Prodi_TIF.xlsx',
        user: $ctx['tifUser'],
        departmentId: $ctx['tifDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['summary']['total_belanja'])->toBe(69)
        ->and($preview['summary']['total_adjustment'])->toBe(37)
        ->and((float) $preview['summary']['total_proposed'])->toBe(535254000.0)
        ->and((float) $preview['summary']['total_approved'])->toBe(346464000.0)
        ->and((float) $preview['summary']['pagu_awal_unit'])->toBe(728338000.0)
        ->and($preview['summary']['pagu_awal_source'])->toBe('RINGKASAN!C6')
        ->and($preview['summary']['pagu_validation_status'])->toBe('VALID')
        ->and((float) $preview['identitas']['pagu_anggaran'])->toBe(346500000.0)
        ->and($preview['identitas']['tanggal_review'])->toBe('2026-09-29');

    foreach ($preview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED') {
            $preview = $importService->assignUnitOnPreview(
                previewToken: $preview['token'],
                adjRefId: $row['adj_ref_id'],
                departmentId: $ctx['ictDept']->id,
                user: $ctx['tifUser'],
            );
        }
    }

    $batch = $importService->executeImport($preview['token'], $ctx['tifUser']);
    $header = RkaHeader::findOrFail($batch->rka_header_id);

    expect($header->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($header->is_locked)->toBeTrue()
        ->and((float) $header->pagu_budget)->toBe(728338000.0)
        ->and((float) $header->pagu_budget)->not->toBe((float) $preview['summary']['total_proposed'])
        ->and((float) $header->pagu_budget)->not->toBe((float) $preview['summary']['total_approved'])
        ->and(RkaItem::where('rka_header_id', $header->id)->count())->toBe(69)
        ->and(RkaAdjustment::where('rka_header_id', $header->id)->count())->toBe(37);
});

it('blocks native import when Usulan awal unit in RINGKASAN is missing without guessing pagu_budget', function () {
    $ctx = makeNativeImportTestContext();
    $importService = app(ApprovedRkaImportService::class);
    $sourcePath = base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx');
    $tempPath = storage_path('app/test_missing_pagu_ict_'.uniqid().'.xlsx');

    $spreadsheet = IOFactory::load($sourcePath);
    $spreadsheet->getSheetByName('RINGKASAN')->setCellValue('C6', null);
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempPath);

    try {
        $preview = $importService->preview(
            filePath: $tempPath,
            originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
            user: $ctx['ictUser'],
            departmentId: $ctx['ictDept']->id,
            scheduleId: $ctx['schedule']->id,
        );

        expect($preview['has_fatal_errors'])->toBeTrue()
            ->and($preview['summary']['pagu_awal_unit'])->toBeNull()
            ->and($preview['summary']['pagu_validation_status'])->toBe('TIDAK DITEMUKAN')
            ->and(implode(' ', $preview['warnings']))->toContain('Usulan awal unit')
            ->and(fn () => $importService->executeImport($preview['token'], $ctx['ictUser']))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        @unlink($tempPath);
    }
});

it('resolves all master departments and Excel unit aliases deterministically without false matches', function () {
    $resolver = app(DepartmentResolver::class);
    $departments = Department::query()->get();

    foreach ($departments as $dept) {
        $byName = $resolver->resolve($dept->name, $departments);
        $byCode = $resolver->resolve($dept->code, $departments);

        expect($byName['status'])->toBe('RESOLVED')
            ->and((int) $byName['department']?->id)->toBe((int) $dept->id)
            ->and($byCode['status'])->toBe('RESOLVED')
            ->and((int) $byCode['department']?->id)->toBe((int) $dept->id);
    }

    $masterCases = [
        'UPT ICT' => 'UPT-ICT',
        'UPT-ICT' => 'UPT-ICT',
        'P3M' => 'P3M',
        'Prodi D3 Teknik Informatika (TIF)' => 'PRODI-TIF',
        'Prodi D4 Administrasi Bisnis Internasional (ABI)' => 'PRODI-ABI',
        'Prodi D3 TPS' => 'PRODI-TPS',
        'Prodi D3 PPM' => 'PRODI-PPM',
        'Prodi D4 TPKS' => 'PRODI-TPKS',
        'Prodi D3 PP' => 'PRODI-PP',
        'Prodi D4 MAB' => 'PRODI-MAB',
        'Prodi D4 TRL' => 'PRODI-TRL',
        'Bagian Umum' => 'BAG-UMUM',
        'BAKKU' => 'BAKKU',
        'UPT-PPKPK' => 'UPT-PPKPK',
    ];

    foreach ($masterCases as $rawName => $expectedCode) {
        $res = $resolver->resolve($rawName, $departments);
        expect($res['status'])->toBe('RESOLVED')
            ->and($res['department']?->code)->toBe($expectedCode);
    }

    expect($resolver->resolve('', $departments)['status'])->toBe(DepartmentResolver::STATUS_INVALID_INPUT)
        ->and($resolver->resolve('Unit Fiktif Tidak Terdaftar', $departments)['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND);
});
