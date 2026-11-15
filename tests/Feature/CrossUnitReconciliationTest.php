<?php

use App\Filament\Pages\Rka\ManageReconciliationsPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaAdjustmentMatch;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaReconciliationHistory;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use App\Services\ApprovedRkaImport\RkaUnlockRequestService;
use App\Services\Rka\Reconciliation\RkaReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeReconciliationTestContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $p3mDept = Department::where('code', 'P3M')->firstOrFail();
    $tifDept = Department::where('code', 'PRODI-TIF')->firstOrFail();
    $abiDept = Department::where('code', 'PRODI-ABI')->firstOrFail();

    $ictUser = User::factory()->create(['name' => 'Pemohon ICT Rec '.$suffix]);
    $p3mUser = User::factory()->create(['name' => 'Pemohon P3M Rec '.$suffix]);
    $tifUser = User::factory()->create(['name' => 'Pemohon TIF Rec '.$suffix]);
    $abiUser = User::factory()->create(['name' => 'Pemohon ABI Rec '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Keuangan Rec '.$suffix]);

    $ictUser->assignRole($pemohonRole);
    $p3mUser->assignRole($pemohonRole);
    $tifUser->assignRole($pemohonRole);
    $abiUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);

    $ictDept->users()->attach($ictUser->id);
    $p3mDept->users()->attach($p3mUser->id);
    $tifDept->users()->attach($tifUser->id);
    $abiDept->users()->attach($abiUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'September 2026 – Agustus 2027',
        'phase_name' => 'Reconcile Test '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $ictUser->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Rec '.$suffix,
        'director_name' => 'Direktur',
        'director_nrp' => '110306009',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);

    return compact(
        'ictDept',
        'p3mDept',
        'tifDept',
        'abiDept',
        'ictUser',
        'p3mUser',
        'tifUser',
        'abiUser',
        'keuangan',
        'schedule',
        'setting',
    );
}

it('queues WAITING_COUNTERPART when P3M imports first and automatically reconciles when UPT ICT imports', function () {
    $ctx = makeReconciliationTestContext();
    $importService = app(ApprovedRkaImportService::class);
    $reconcileService = app(RkaReconciliationService::class);

    // 1. P3M import lebih dahulu
    $p3mPreview = $importService->preview(
        filePath: base_path('data-rka/10_RKA_2026-2027_P3M.xlsx'),
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['p3mDept']->id,
        scheduleId: $ctx['schedule']->id,
    );
    foreach ($p3mPreview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED') {
            $p3mPreview = $importService->assignUnitOnPreview(
                previewToken: $p3mPreview['token'],
                adjRefId: $row['adj_ref_id'],
                departmentId: $ctx['ictDept']->id,
                user: $ctx['p3mUser'],
            );
        }
    }
    $p3mBatch = $importService->executeImport($p3mPreview['token'], $ctx['p3mUser']);
    $p3mHeader = RkaHeader::findOrFail($p3mBatch->rka_header_id);

    $p3mMatches = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->get();
    expect($p3mMatches->count())->toBe(23)
        ->and($p3mMatches->where('reconciliation_status', RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART)->count())->toBe(23);

    // 2. UPT ICT import setelah P3M
    $ictPreview = $importService->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
    );
    $ictBatch = $importService->executeImport($ictPreview['token'], $ctx['ictUser']);
    $ictHeader = RkaHeader::findOrFail($ictBatch->rka_header_id);

    // Turnitin (1 Paket x 1.000.000 di P3M & ICT) otomatis MATCHED dan terbentuk satu kali
    $turnitinMatches = RkaAdjustmentMatch::with(['sourceAdjustment', 'counterpartAdjustment'])
        ->where('schedule_id', $ctx['schedule']->id)
        ->whereHas('sourceAdjustment', fn ($q) => $q->where('description', 'like', '%Turnitin%'))
        ->get();

    expect($turnitinMatches->count())->toBe(1);
    $turnitinMatch = $turnitinMatches->first();
    expect($turnitinMatch->reconciliation_status)->toBe(RkaAdjustmentMatch::STATUS_MATCHED)
        ->and($turnitinMatch->matching_level)->toBe(RkaAdjustmentMatch::LEVEL_MATCH_UNIQUE)
        ->and((int) $turnitinMatch->source_department_id)->toBe((int) $ctx['p3mDept']->id)
        ->and((int) $turnitinMatch->target_department_id)->toBe((int) $ctx['ictDept']->id)
        ->and((float) $turnitinMatch->matched_amount)->toBe(1000000.0)
        ->and($turnitinMatch->counterpart_item_id)->not->toBeNull()
        ->and($turnitinMatch->counterpart_adjustment_id)->not->toBeNull();

    // Tidak ada duplikat mirror match untuk counterpart_adjustment_id Turnitin
    expect(
        RkaAdjustmentMatch::where('source_adjustment_id', $turnitinMatch->counterpart_adjustment_id)->count()
    )->toBe(0);

    // Zoom (12 Bulan x 50.000 di P3M vs 1 Paket x 600.000 di ICT) terdeteksi CONFLICT karena volume & satuan berbeda
    $zoomMatch = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)
        ->whereHas('sourceAdjustment', fn ($q) => $q->where('description', 'like', '%Zoom%'))
        ->firstOrFail();
    expect($zoomMatch->reconciliation_status)->toBe(RkaAdjustmentMatch::STATUS_CONFLICT)
        ->and($zoomMatch->matching_level)->toBe(RkaAdjustmentMatch::LEVEL_MATCH_CONFLICT)
        ->and($zoomMatch->match_details['differing_attributes'])->toContain('proposed_volume', 'unit_symbol');

    // Status APPROVED & lock kedua RKA tidak berubah
    expect($p3mHeader->fresh()->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($p3mHeader->fresh()->is_locked)->toBeTrue()
        ->and($ictHeader->fresh()->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($ictHeader->fresh()->is_locked)->toBeTrue();

    // Idempotency: rekonsiliasi ulang tidak menggandakan record atau histori
    $countBefore = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->count();
    $historyBefore = RkaReconciliationHistory::whereIn(
        'rka_adjustment_match_id',
        RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->pluck('id')
    )->count();

    $reconcileService->reconcileForHeader($ictHeader, $ictBatch, $ctx['ictUser']->id, 'IDEMPOTENCY_CHECK');

    expect(RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->count())->toBe($countBefore)
        ->and(RkaReconciliationHistory::whereIn(
            'rka_adjustment_match_id',
            RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->pluck('id')
        )->count())->toBe($historyBefore);

    // Finance adjust Zoom CONFLICT -> MATCHED disertai alasan & histori sebelum/sesudah
    $adjustedZoom = $reconcileService->financeAdjust(
        match: $zoomMatch,
        actor: $ctx['keuangan'],
        newStatus: RkaAdjustmentMatch::STATUS_MATCHED,
        counterpartAdjustmentId: $zoomMatch->counterpart_adjustment_id,
        targetDepartmentId: $zoomMatch->target_department_id,
        matchedAmount: 600000.0,
        reason: 'Ekuivalen 1 Paket = 12 Bulan x Rp 50.000',
    );

    expect($adjustedZoom->reconciliation_status)->toBe(RkaAdjustmentMatch::STATUS_MATCHED)
        ->and((int) $adjustedZoom->reviewed_by)->toBe((int) $ctx['keuangan']->id);

    $latestHistory = $adjustedZoom->histories()->first();
    expect($latestHistory->action)->toBe('FINANCE_ADJUSTED')
        ->and($latestHistory->before_state['reconciliation_status'])->toBe(RkaAdjustmentMatch::STATUS_CONFLICT)
        ->and($latestHistory->after_state['reconciliation_status'])->toBe(RkaAdjustmentMatch::STATUS_MATCHED)
        ->and($latestHistory->reason)->toBe('Ekuivalen 1 Paket = 12 Bulan x Rp 50.000');

    // Finance cancel
    $cancelledZoom = $reconcileService->financeCancel($adjustedZoom, $ctx['keuangan'], 'Dibatalkan untuk uji audit');
    expect($cancelledZoom->reconciliation_status)->toBe(RkaAdjustmentMatch::STATUS_CANCELLED)
        ->and(RkaLog::where('action', RkaLog::ACTION_RECONCILIATION_ADJUSTED)->count())->toBeGreaterThanOrEqual(1)
        ->and(RkaLog::where('action', RkaLog::ACTION_RECONCILIATION_CANCELLED)->count())->toBeGreaterThanOrEqual(1);
});

it('handles negative matching scenarios without false matches', function () {
    $ctx = makeReconciliationTestContext();
    $reconcileService = app(RkaReconciliationService::class);
    $account = Account::where('code', '5.2.2.04')->firstOrFail();

    $otherSchedule = RkaSchedule::create([
        'fiscal_year' => '2027/2028',
        'period_range' => 'September 2027 – Agustus 2028',
        'phase_name' => 'Different Period',
        'start_at' => now()->addYear(),
        'end_at' => now()->addYear()->addMonth(),
        'is_active' => false,
        'created_by' => $ctx['ictUser']->id,
    ]);

    $p3mHeader = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $ctx['p3mDept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['p3mUser']->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 5000000,
    ]);

    $ictHeader = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $ctx['ictDept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['ictUser']->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 5000000,
    ]);

    $otherScheduleIctHeader = RkaHeader::create([
        'schedule_id' => $otherSchedule->id,
        'department_id' => $ctx['ictDept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['ictUser']->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 5000000,
    ]);

    // 1. Kode rekening sama tetapi uraian berbeda -> UNMATCHED
    $p3mDiffDesc = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'A',
        'account_id' => $account->id,
        'description' => 'Langganan Software Statistik SPSS',
        'destination_unit_id' => $ctx['ictDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 1000000,
        'proposed_amount' => 1000000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_MOVED,
    ]);

    // 2. Uraian sama tetapi target_unit_id berbeda (untuk ABI, bukan P3M) -> UNMATCHED
    $ictWrongUnit = RkaAdjustment::create([
        'rka_header_id' => $ictHeader->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'section_name' => 'B',
        'account_id' => $account->id,
        'description' => 'Langganan Software Statistik SPSS',
        'target_unit_id' => $ctx['abiDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 1000000,
        'proposed_amount' => 1000000,
        'approved_amount' => 1000000,
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
    ]);

    // 3. Periode berbeda -> tidak boleh cocok lintas schedule
    RkaAdjustment::create([
        'rka_header_id' => $otherScheduleIctHeader->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'section_name' => 'B',
        'account_id' => $account->id,
        'description' => 'Langganan Software Statistik SPSS',
        'target_unit_id' => $ctx['p3mDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 1000000,
        'proposed_amount' => 1000000,
        'approved_amount' => 1000000,
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
    ]);

    // 4. Beberapa kandidat identik -> REVIEW_REQUIRED
    $p3mAmbiguous = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'A',
        'account_id' => $account->id,
        'description' => 'Lisensi Antivirus',
        'destination_unit_id' => $ctx['ictDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 500000,
        'proposed_amount' => 500000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_MOVED,
    ]);

    foreach ([1, 2] as $idx) {
        RkaAdjustment::create([
            'rka_header_id' => $ictHeader->id,
            'section_type' => RkaAdjustment::SECTION_B,
            'section_name' => 'B',
            'account_id' => $account->id,
            'description' => 'Lisensi Antivirus',
            'target_unit_id' => $ctx['p3mDept']->id,
            'proposed_volume' => 1,
            'unit_symbol' => 'Paket',
            'unit_price' => 500000,
            'proposed_amount' => 500000,
            'approved_amount' => 500000,
            'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        ]);
    }

    // 5. Nilai tidak cocok -> CONFLICT
    $p3mDiffAmount = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'A',
        'account_id' => $account->id,
        'description' => 'Sewa Domain Jurnal',
        'destination_unit_id' => $ctx['ictDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Tahun',
        'unit_price' => 300000,
        'proposed_amount' => 300000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_MOVED,
    ]);
    RkaAdjustment::create([
        'rka_header_id' => $ictHeader->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'section_name' => 'B',
        'account_id' => $account->id,
        'description' => 'Sewa Domain Jurnal',
        'target_unit_id' => $ctx['p3mDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Tahun',
        'unit_price' => 450000,
        'proposed_amount' => 450000,
        'approved_amount' => 450000,
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
    ]);

    $reconcileService->reconcileForHeader($p3mHeader, null, $ctx['p3mUser']->id, 'TEST_NEGATIVE');

    expect(RkaAdjustmentMatch::where('source_adjustment_id', $p3mDiffDesc->id)->value('reconciliation_status'))
        ->toBe(RkaAdjustmentMatch::STATUS_UNMATCHED)
        ->and(RkaAdjustmentMatch::where('source_adjustment_id', $p3mAmbiguous->id)->value('reconciliation_status'))
        ->toBe(RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED)
        ->and(RkaAdjustmentMatch::where('source_adjustment_id', $p3mDiffAmount->id)->value('reconciliation_status'))
        ->toBe(RkaAdjustmentMatch::STATUS_CONFLICT);
});

it('enforces RBAC and cross-unit visibility on reconciliation matches and monitoring page', function () {
    $ctx = makeReconciliationTestContext();
    $importService = app(ApprovedRkaImportService::class);

    $p3mPreview = $importService->preview(
        filePath: base_path('data-rka/10_RKA_2026-2027_P3M.xlsx'),
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['p3mDept']->id,
        scheduleId: $ctx['schedule']->id,
    );
    foreach ($p3mPreview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED') {
            $p3mPreview = $importService->assignUnitOnPreview(
                previewToken: $p3mPreview['token'],
                adjRefId: $row['adj_ref_id'],
                departmentId: $ctx['ictDept']->id,
                user: $ctx['p3mUser'],
            );
        }
    }
    $importService->executeImport($p3mPreview['token'], $ctx['p3mUser']);

    $ictPreview = $importService->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
    );
    $importService->executeImport($ictPreview['token'], $ctx['ictUser']);

    $turnitinMatch = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)
        ->where('reconciliation_status', RkaAdjustmentMatch::STATUS_MATCHED)
        ->firstOrFail();

    // Finance monitoring access
    $this->actingAs($ctx['keuangan']);
    expect(ManageReconciliationsPage::canAccess())->toBeTrue()
        ->and($ctx['keuangan']->can('adjust', $turnitinMatch))->toBeTrue()
        ->and($ctx['keuangan']->can('cancel', $turnitinMatch))->toBeTrue()
        ->and($ctx['keuangan']->can('rerun', RkaAdjustmentMatch::class))->toBeTrue();

    // Pemohon cannot access Finance monitoring or adjust/cancel/rerun
    $this->actingAs($ctx['p3mUser']);
    expect(ManageReconciliationsPage::canAccess())->toBeFalse()
        ->and($ctx['p3mUser']->can('view', $turnitinMatch))->toBeTrue()
        ->and($ctx['p3mUser']->can('adjust', $turnitinMatch))->toBeFalse()
        ->and($ctx['p3mUser']->can('cancel', $turnitinMatch))->toBeFalse()
        ->and($ctx['p3mUser']->can('rerun', RkaAdjustmentMatch::class))->toBeFalse();

    // Target unit (ICT) can view approved match, unrelated unit (TIF) cannot
    expect($ctx['ictUser']->can('view', $turnitinMatch))->toBeTrue()
        ->and($ctx['tifUser']->can('view', $turnitinMatch))->toBeFalse();
});

it('reconciles identically when UPT ICT imports first and P3M imports second, and handles reimport cleanly', function () {
    $ctx = makeReconciliationTestContext();
    $importService = app(ApprovedRkaImportService::class);
    $unlockService = app(RkaUnlockRequestService::class);

    // 1. UPT ICT import lebih dahulu -> 10 Section B masuk WAITING_COUNTERPART
    $ictPreview = $importService->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
    );
    $ictBatch = $importService->executeImport($ictPreview['token'], $ctx['ictUser']);
    $ictHeader = RkaHeader::findOrFail($ictBatch->rka_header_id);

    $ictWaiting = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->get();
    expect($ictWaiting->count())->toBe(10)
        ->and($ictWaiting->where('reconciliation_status', RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART)->count())->toBe(10);

    // 2. P3M import kedua -> Turnitin langsung MATCHED, Zoom CONFLICT, tanpa duplikasi
    $p3mPreview = $importService->preview(
        filePath: base_path('data-rka/10_RKA_2026-2027_P3M.xlsx'),
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['p3mDept']->id,
        scheduleId: $ctx['schedule']->id,
    );
    foreach ($p3mPreview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED') {
            $p3mPreview = $importService->assignUnitOnPreview(
                previewToken: $p3mPreview['token'],
                adjRefId: $row['adj_ref_id'],
                departmentId: $ctx['ictDept']->id,
                user: $ctx['p3mUser'],
            );
        }
    }
    $p3mBatch = $importService->executeImport($p3mPreview['token'], $ctx['p3mUser']);
    $p3mHeader = RkaHeader::findOrFail($p3mBatch->rka_header_id);

    $turnitinMatches = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)
        ->whereHas('sourceAdjustment', fn ($q) => $q->where('description', 'like', '%Turnitin%'))
        ->get();

    expect($turnitinMatches->count())->toBe(1)
        ->and($turnitinMatches->first()->reconciliation_status)->toBe(RkaAdjustmentMatch::STATUS_MATCHED)
        ->and($turnitinMatches->first()->counterpart_adjustment_id)->not->toBeNull();

    // 3. Reimport P3M setelah unlock -> pasangan Turnitin tetap 1 MATCHED (tidak null/hilang dan tidak duplikat)
    $unlock = $unlockService->submitRequest($p3mHeader, $ctx['p3mUser'], 'Uji reimport rekonsiliasi');
    $approvedUnlock = $unlockService->approveRequest($unlock, $ctx['keuangan'], 'Disetujui untuk reimport');

    $reimportPreview = $importService->preview(
        filePath: base_path('data-rka/10_RKA_2026-2027_P3M.xlsx'),
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['p3mDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_REIMPORT,
        targetHeader: $p3mHeader,
        unlockRequestId: $approvedUnlock->id,
    );
    foreach ($reimportPreview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED') {
            $reimportPreview = $importService->assignUnitOnPreview(
                previewToken: $reimportPreview['token'],
                adjRefId: $row['adj_ref_id'],
                departmentId: $ctx['ictDept']->id,
                user: $ctx['p3mUser'],
            );
        }
    }
    $importService->executeReimport($reimportPreview['token'], $p3mHeader, $approvedUnlock, $ctx['p3mUser']);

    $afterReimportTurnitin = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)
        ->whereHas('sourceAdjustment', fn ($q) => $q->where('description', 'like', '%Turnitin%'))
        ->get();

    expect($afterReimportTurnitin->count())->toBe(1)
        ->and($afterReimportTurnitin->first()->reconciliation_status)->toBe(RkaAdjustmentMatch::STATUS_MATCHED)
        ->and($afterReimportTurnitin->first()->counterpart_adjustment_id)->not->toBeNull();
});

it('never uses BELANJA RkaItem records for cross-unit matching without DAFTAR PENYESUAIAN', function () {
    $ctx = makeReconciliationTestContext();
    $reconcileService = app(RkaReconciliationService::class);
    $account = Account::where('code', '5.2.2.04')->firstOrFail();

    $p3mHeader = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $ctx['p3mDept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['p3mUser']->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 1000000,
    ]);

    $ictHeader = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $ctx['ictDept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['ictUser']->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 1000000,
    ]);

    foreach ([$p3mHeader, $ictHeader] as $hdr) {
        $ind = RkaIndicator::create([
            'rka_header_id' => $hdr->id,
            'account_id' => $account->id,
            'location' => 'Politeknik Kampar',
            'indicator_matrix' => [],
        ]);
        RkaItem::create([
            'rka_header_id' => $hdr->id,
            'rka_indicator_id' => $ind->id,
            'form_type' => '5.2',
            'account_id' => $account->id,
            'description' => 'Pembelian Akun Turnitin',
            'proposed_volume' => 1,
            'unit_symbol' => 'Paket',
            'unit_price' => 1000000,
            'proposed_total' => 1000000,
            'approved_volume' => 1,
            'approved_unit_price' => 1000000,
            'approved_total' => 1000000,
            'status' => 'APPROVED',
        ]);
    }

    $reconcileService->reconcileForHeader($p3mHeader, null, $ctx['p3mUser']->id, 'BELANJA_ONLY_TEST');
    $reconcileService->reconcileForHeader($ictHeader, null, $ctx['ictUser']->id, 'BELANJA_ONLY_TEST');

    expect(RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->count())->toBe(0);
});

it('follows manual CSV workflow: only MOVED requires Section B pair while PENDING, REJECTED, BUDGETED_ELSEWHERE, REMOVED stay Section A only', function () {
    $ctx = makeReconciliationTestContext();
    $reconcileService = app(RkaReconciliationService::class);
    $account = Account::where('code', '5.2.2.04')->firstOrFail();
    $budgetCountBefore = DepartmentBudget::count();

    $p3mHeader = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $ctx['p3mDept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['p3mUser']->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 10000000,
    ]);

    $ictHeader = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $ctx['ictDept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['ictUser']->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 10000000,
    ]);

    // Section A non-MOVED actions (matching manual CSV pattern)
    $pendingAdj = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'Penyesuaian Unit Asal',
        'account_id' => $account->id,
        'description' => 'Item Ditunda Unit Asal',
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 1000000,
        'proposed_amount' => 1000000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_PENDING,
    ]);

    $rejectedAdj = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'Penyesuaian Unit Asal',
        'account_id' => $account->id,
        'description' => 'Publikasi Proceeding Nasional',
        'proposed_volume' => 5,
        'unit_symbol' => 'Paper',
        'unit_price' => 300000,
        'proposed_amount' => 1500000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_REJECTED,
    ]);

    $budgetedElsewhereAdj = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'Penyesuaian Unit Asal',
        'account_id' => $account->id,
        'description' => 'Belanja Jasa Konsultan Akreditasi',
        'proposed_volume' => 1,
        'unit_symbol' => 'OK',
        'unit_price' => 5000000,
        'proposed_amount' => 5000000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_BUDGETED_ELSEWHERE,
    ]);

    $removedAdj = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'Penyesuaian Unit Asal',
        'account_id' => $account->id,
        'description' => 'Item Dihapus Saat Review',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 500000,
        'proposed_amount' => 500000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_REMOVED,
    ]);

    // Section A MOVED + Section B MOVED (matching manual CSV pattern)
    $movedSectionA = RkaAdjustment::create([
        'rka_header_id' => $p3mHeader->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'section_name' => 'Penyesuaian Unit Asal',
        'account_id' => $account->id,
        'description' => 'License OS',
        'destination_unit_id' => $ctx['ictDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Pkt',
        'unit_price' => 4450000,
        'proposed_amount' => 4450000,
        'approved_amount' => 0,
        'action_type' => RkaAdjustment::ACTION_MOVED,
    ]);

    $movedSectionB = RkaAdjustment::create([
        'rka_header_id' => $ictHeader->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'section_name' => 'Penyesuaian Unit Pengelola',
        'account_id' => $account->id,
        'description' => 'License OS',
        'target_unit_id' => $ctx['p3mDept']->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Pkt',
        'unit_price' => 4450000,
        'proposed_amount' => 4450000,
        'approved_amount' => 4450000,
        'action_type' => RkaAdjustment::ACTION_MOVED,
    ]);

    $reconcileService->reconcileForHeader($p3mHeader, null, $ctx['p3mUser']->id, 'CSV_WORKFLOW_TEST');

    // Hanya MOVED yang membentuk RkaAdjustmentMatch (1 pasangan MATCHED), non-MOVED tidak membuat antrean atau Section B palsu
    $matches = RkaAdjustmentMatch::where('schedule_id', $ctx['schedule']->id)->get();
    expect($matches->count())->toBe(1)
        ->and($matches->first()->reconciliation_status)->toBe(RkaAdjustmentMatch::STATUS_MATCHED)
        ->and((int) $matches->first()->source_adjustment_id)->toBe((int) $movedSectionA->id)
        ->and((int) $matches->first()->counterpart_adjustment_id)->toBe((int) $movedSectionB->id)
        ->and(RkaAdjustmentMatch::whereIn('source_adjustment_id', [$pendingAdj->id, $rejectedAdj->id, $budgetedElsewhereAdj->id, $removedAdj->id])->count())->toBe(0)
        ->and(RkaAdjustment::where('rka_header_id', $ictHeader->id)->count())->toBe(1)
        ->and(DepartmentBudget::count())->toBe($budgetCountBefore);
});
