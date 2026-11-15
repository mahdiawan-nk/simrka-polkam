<?php

use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\RkaApprovalService;
use App\Services\RkaItemService;
use App\Services\RkaReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeAuditFixContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));
    $user = User::factory()->create();

    $originDept = Department::create([
        'type' => 'PRODI',
        'code' => 'ORG-'.$suffix,
        'level_code' => '3',
        'name' => 'Prodi Asal '.$suffix,
    ]);

    $targetDept = Department::create([
        'type' => 'UPT',
        'code' => 'TGT-'.$suffix,
        'level_code' => '2',
        'name' => 'UPT Tujuan '.$suffix,
    ]);

    $schedule1 = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Murni '.$suffix,
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDays(2),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $schedule2 = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Perubahan '.$suffix,
        'start_at' => now()->subDay(),
        'end_at' => now()->addDays(5),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Audit '.$suffix,
        'director_user_id' => $user->id,
        'director_name' => 'Direktur Test',
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);

    $originHeader = RkaHeader::create([
        'schedule_id' => $schedule1->id,
        'department_id' => $originDept->id,
        'setting_id' => $setting->id,
        'status' => RkaHeader::STATUS_IN_REVIEW,
        'pagu_budget' => 0,
    ]);

    $targetHeader = RkaHeader::create([
        'schedule_id' => $schedule1->id,
        'department_id' => $targetDept->id,
        'setting_id' => $setting->id,
        'status' => RkaHeader::STATUS_IN_REVIEW,
        'pagu_budget' => 0,
    ]);

    return compact('suffix', 'user', 'originDept', 'targetDept', 'schedule1', 'schedule2', 'setting', 'originHeader', 'targetHeader');
}

function createAccountHierarchyForGroup(string $groupCode, string $suffix): array
{
    $level1 = Account::firstOrCreate(
        ['code' => $groupCode],
        [
            'code_clean' => str_replace('.', '', $groupCode),
            'name' => 'Kelompok '.$groupCode,
            'level' => 1,
            'level_label' => 'Kelompok',
            'is_active' => true,
        ]
    );

    $detailCode = $groupCode.'.99.'.$suffix;
    $detail = Account::create([
        'code' => $detailCode,
        'code_clean' => str_replace('.', '', $detailCode),
        'name' => 'Rekening '.$detailCode,
        'parent_id' => $level1->id,
        'parent_code' => $level1->code,
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);

    return [$level1, $detail];
}

test('addAdjustment requires valid unit_symbol and persists SHARED_COST and ADDED_BY_REVIEWER without overwriting each other', function () {
    $ctx = makeAuditFixContext();
    $this->actingAs($ctx['user']);

    [$level1, $detailAccount] = createAccountHierarchyForGroup('5.2', $ctx['suffix']);
    $indicator = RkaIndicator::create([
        'rka_header_id' => $ctx['targetHeader']->id,
        'account_id' => $level1->id,
        'program_name' => 'Program Operasional',
        'activity_name' => 'Kegiatan Operasional',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $service = app(RkaReviewService::class);

    expect(fn () => $service->addAdjustment($ctx['targetHeader'], (string) $indicator->id, [
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        'account_id' => $detailAccount->id,
        'description' => 'Lisensi tanpa satuan',
        'target_unit_id' => $ctx['originDept']->id,
        'proposed_amount' => 1000000,
        'approved_amount' => 1000000,
    ]))->toThrow(InvalidArgumentException::class);

    $adjShared = $service->addAdjustment($ctx['targetHeader'], (string) $indicator->id, [
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        'account_id' => $detailAccount->id,
        'description' => 'Lisensi Turnitin Bersama',
        'target_unit_id' => $ctx['originDept']->id,
        'proposed_volume' => 2,
        'unit_symbol' => 'Paket',
        'unit_price' => 500000,
        'proposed_amount' => 1000000,
        'approved_amount' => 1000000,
    ]);

    $adjAdded = $service->addAdjustment($ctx['targetHeader'], (string) $indicator->id, [
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_ADDED_BY_REVIEWER,
        'account_id' => $detailAccount->id,
        'description' => 'Tambahan Pemeliharaan Server',
        'proposed_volume' => 1,
        'unit_symbol' => 'Tahun',
        'unit_price' => 2500000,
        'proposed_amount' => 2500000,
        'approved_amount' => 2500000,
    ]);

    expect($adjShared->id)->not->toBe($adjAdded->id)
        ->and($adjShared->unit_symbol)->toBe('Paket')
        ->and((float) $adjShared->proposed_volume)->toBe(2.0)
        ->and($adjAdded->unit_symbol)->toBe('Tahun')
        ->and(RkaAdjustment::where('rka_header_id', $ctx['targetHeader']->id)->count())->toBe(2);
});

test('importAdjustmentToItem populates valid form_type for all six spending groups', function (string $groupCode) {
    $ctx = makeAuditFixContext();
    $this->actingAs($ctx['user']);

    [$level1, $detailAccount] = createAccountHierarchyForGroup($groupCode, $ctx['suffix']);
    $indicator = RkaIndicator::create([
        'rka_header_id' => $ctx['targetHeader']->id,
        'account_id' => $level1->id,
        'program_name' => 'Program '.$groupCode,
        'activity_name' => 'Kegiatan '.$groupCode,
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $service = app(RkaReviewService::class);

    $adjustment = $service->addAdjustment($ctx['targetHeader'], null, [
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_ADDED_BY_REVIEWER,
        'account_id' => $detailAccount->id,
        'description' => 'Item Impor '.$groupCode,
        'proposed_volume' => 3,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
        'proposed_amount' => 300000,
        'approved_amount' => 300000,
    ]);

    $result = $service->importAdjustmentToItem($ctx['targetHeader'], $adjustment->id);

    $adjustment->refresh();
    $importedItem = RkaItem::find($adjustment->imported_rka_item_id);

    expect($result)->toBeTrue()
        ->and($adjustment->is_imported)->toBeTrue()
        ->and($importedItem)->not->toBeNull()
        ->and($importedItem->form_type)->toBe($groupCode)
        ->and($importedItem->rka_indicator_id)->toBe($indicator->id)
        ->and($importedItem->is_imported)->toBeTrue()
        ->and((float) $importedItem->approved_total)->toBe(300000.0);
})->with(['5.1', '5.2', '5.3', '5.4', '5.5', '2.1']);

test('reviewItem handles all item adjustment statuses and cleans up stale adjustments on status transition', function () {
    $ctx = makeAuditFixContext();
    $this->actingAs($ctx['user']);

    [$level1, $detailAccount] = createAccountHierarchyForGroup('5.2', $ctx['suffix']);
    $indicator = RkaIndicator::create([
        'rka_header_id' => $ctx['originHeader']->id,
        'account_id' => $level1->id,
        'program_name' => 'Program Review',
        'activity_name' => 'Kegiatan Review',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $service = app(RkaReviewService::class);

    $statuses = [
        RkaAdjustment::ACTION_BUDGETED_ELSEWHERE,
        RkaAdjustment::ACTION_REJECTED_WITH_ADJUSTMENT,
        RkaAdjustment::ACTION_REJECTED,
        RkaAdjustment::ACTION_REMOVED,
        RkaAdjustment::ACTION_PENDING,
    ];

    foreach ($statuses as $index => $status) {
        $item = RkaItem::create([
            'rka_header_id' => $ctx['originHeader']->id,
            'rka_indicator_id' => $indicator->id,
            'account_id' => $detailAccount->id,
            'form_type' => '5.2',
            'description' => 'Item '.$status.' '.$index,
            'proposed_volume' => 2,
            'unit_symbol' => 'Unit',
            'unit_price' => 100000,
            'status' => RkaItem::STATUS_PENDING,
        ]);

        $service->reviewItem($ctx['originHeader'], [
            'item_id' => $item->id,
            'action_type' => $status,
            'approved_volume' => 0,
            'approved_price' => 90000,
            'approved_total' => 0,
            'unit_symbol' => 'Unit',
            'review_notes' => 'Uji '.$status,
        ], ['item_id' => $item->id]);

        $item->refresh();
        $adj = RkaAdjustment::where('rka_item_id', $item->id)->first();

        expect($item->status)->toBe($status)
            ->and((float) $item->approved_total)->toBe(0.0)
            ->and($adj)->not->toBeNull()
            ->and($adj->action_type)->toBe($status);
    }

    // Uji transisi status agar tidak menyisakan adjustment ganda: MOVED -> REJECTED -> PENDING
    $transitionItem = RkaItem::create([
        'rka_header_id' => $ctx['originHeader']->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Item Transisi',
        'proposed_volume' => 5,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
        'status' => RkaItem::STATUS_PENDING,
    ]);

    $service->reviewItem($ctx['originHeader'], [
        'item_id' => $transitionItem->id,
        'action_type' => RkaAdjustment::ACTION_MOVED,
        'approved_volume' => 5,
        'approved_price' => 100000,
        'approved_total' => 500000,
        'unit_symbol' => 'Unit',
        'destination_unit_id' => $ctx['targetDept']->id,
        'review_notes' => 'Dipindah dulu',
    ], ['item_id' => $transitionItem->id]);

    expect(RkaAdjustment::where('rka_item_id', $transitionItem->id)->count())->toBe(2);

    $service->reviewItem($ctx['originHeader'], [
        'item_id' => $transitionItem->id,
        'action_type' => RkaAdjustment::ACTION_PENDING,
        'approved_volume' => 0,
        'approved_price' => 100000,
        'approved_total' => 0,
        'unit_symbol' => 'Unit',
        'review_notes' => 'Ditunda',
    ], ['item_id' => $transitionItem->id]);

    $remaining = RkaAdjustment::where('rka_item_id', $transitionItem->id)->get();
    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->rka_header_id)->toBe($ctx['originHeader']->id)
        ->and($remaining->first()->action_type)->toBe(RkaAdjustment::ACTION_PENDING)
        ->and($remaining->first()->section_type)->toBe(RkaAdjustment::SECTION_A)
        ->and((float) $remaining->first()->approved_amount)->toBe(0.0)
        ->and($remaining->first()->destination_unit_id)->toBeNull()
        ->and($remaining->first()->target_unit_id)->toBeNull();

    // Pastikan PENDING tidak masuk Section B (tambahan_pusat) dan tidak masuk DepartmentBudget
    $summary = $service->calculateSummaryData($ctx['originHeader']->fresh());
    expect((float) $summary['tambahan_pusat'])->toBe(0.0);

    app(RkaApprovalService::class)->approveByDirektur($ctx['originHeader'], $ctx['user']);
    expect(DepartmentBudget::where('department_id', $ctx['originDept']->id)->exists())->toBeFalse()
        ->and(RkaItem::where('rka_header_id', $ctx['targetHeader']->id)->exists())->toBeFalse();
});

test('full RKA approval workflow and DepartmentBudget sync across two schedules in same fiscal year', function () {
    $ctx = makeAuditFixContext();
    $this->actingAs($ctx['user']);

    $dept = Department::create([
        'type' => 'PRODI',
        'code' => 'FY-'.$ctx['suffix'],
        'level_code' => '3',
        'name' => 'Prodi Multi Schedule '.$ctx['suffix'],
    ]);

    [$level1, $detailAccount] = createAccountHierarchyForGroup('5.3', $ctx['suffix']);

    $rka1 = RkaHeader::create([
        'schedule_id' => $ctx['schedule1']->id,
        'department_id' => $dept->id,
        'setting_id' => $ctx['setting']->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'pagu_budget' => 0,
    ]);

    $ind1 = RkaIndicator::create([
        'rka_header_id' => $rka1->id,
        'account_id' => $level1->id,
        'program_name' => 'Program Sarpras',
        'activity_name' => 'Pengadaan Alat',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $itemService = app(RkaItemService::class);
    $item1 = $itemService->addItem($rka1, [
        'account_id' => $detailAccount->id,
        'form_type' => '5.3',
        'description' => 'Switch Core',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 5000000,
    ], $ind1->id);

    $approvalService = app(RkaApprovalService::class);
    $approvalService->submitByPemohon($rka1, $ctx['user']);
    $approvalService->startReviewByWadir($rka1, $ctx['user']);

    $reviewService = app(RkaReviewService::class);
    $reviewService->bulkApproveItems($rka1, [$item1->id]);

    $approvalService->forwardToDirektur($rka1, $ctx['user']);
    $approvalService->approveByDirektur($rka1, $ctx['user']);

    $budget1 = DepartmentBudget::where('department_id', $dept->id)
        ->where('account_id', $detailAccount->id)
        ->where('fiscal_year', '2026/2027')
        ->first();

    expect($budget1)->not->toBeNull()
        ->and((float) $budget1->allocated_amount)->toBe(10000000.0)
        ->and($budget1->schedule_id)->toBe($ctx['schedule1']->id);

    // Schedule 2 di fiscal_year yang sama (2026/2027) tidak boleh melempar Duplicate entry
    $rka2 = RkaHeader::create([
        'schedule_id' => $ctx['schedule2']->id,
        'department_id' => $dept->id,
        'setting_id' => $ctx['setting']->id,
        'status' => RkaHeader::STATUS_IN_APPROVED,
        'pagu_budget' => 15000000,
    ]);

    $ind2 = RkaIndicator::create([
        'rka_header_id' => $rka2->id,
        'account_id' => $level1->id,
        'program_name' => 'Program Perubahan',
        'activity_name' => 'Pengadaan Alat Perubahan',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    RkaItem::create([
        'rka_header_id' => $rka2->id,
        'rka_indicator_id' => $ind2->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.3',
        'description' => 'Switch Core Perubahan',
        'proposed_volume' => 3,
        'unit_symbol' => 'Unit',
        'unit_price' => 5000000,
        'approved_volume' => 3,
        'approved_total' => 15000000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    $approvalService->approveByDirektur($rka2, $ctx['user']);

    $budget2 = DepartmentBudget::where('department_id', $dept->id)
        ->where('account_id', $detailAccount->id)
        ->where('fiscal_year', '2026/2027')
        ->first();

    expect($budget2)->not->toBeNull()
        ->and($budget2->id)->toBe($budget1->id)
        ->and((float) $budget2->allocated_amount)->toBe(15000000.0)
        ->and($budget2->schedule_id)->toBe($ctx['schedule2']->id);
});
