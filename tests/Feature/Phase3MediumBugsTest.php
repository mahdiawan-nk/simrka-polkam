<?php

use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\RkaReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('scopes bulkApproveItems and bulkMoveItems to the current RkaHeader (BUG-012)', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $user = User::factory()->create();
    $deptA = Department::create([
        'type' => 'PRODI',
        'code' => 'BA-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept Bulk A',
    ]);
    $deptB = Department::create([
        'type' => 'PRODI',
        'code' => 'BB-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept Bulk B',
    ]);
    $deptC = Department::create([
        'type' => 'UPT',
        'code' => 'BC-'.$suffix,
        'level_code' => '2',
        'name' => 'Dept Bulk C',
    ]);
    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Pengusulan',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'is_active' => true,
        'created_by' => $user->id,
    ]);
    $setting = RkaSetting::create([
        'setting_name' => 'Setting Bulk '.$suffix,
        'director_user_id' => $user->id,
        'director_name' => 'Direktur',
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);
    $account = Account::create([
        'code' => '9.6.'.$suffix,
        'code_clean' => '96'.$suffix,
        'name' => 'Akun Bulk '.$suffix,
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);

    $headerA = RkaHeader::create([
        'department_id' => $deptA->id,
        'schedule_id' => $schedule->id,
        'setting_id' => $setting->id,
        'status' => RkaHeader::STATUS_IN_REVIEW,
    ]);
    $headerB = RkaHeader::create([
        'department_id' => $deptB->id,
        'schedule_id' => $schedule->id,
        'setting_id' => $setting->id,
        'status' => RkaHeader::STATUS_IN_REVIEW,
    ]);
    $indicatorA = RkaIndicator::create([
        'rka_header_id' => $headerA->id,
        'account_id' => $account->id,
        'program_name' => 'Program A',
        'activity_name' => 'Kegiatan A',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);
    $indicatorB = RkaIndicator::create([
        'rka_header_id' => $headerB->id,
        'account_id' => $account->id,
        'program_name' => 'Program B',
        'activity_name' => 'Kegiatan B',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $itemA = RkaItem::create([
        'rka_header_id' => $headerA->id,
        'rka_indicator_id' => $indicatorA->id,
        'account_id' => $account->id,
        'description' => 'Item A',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 10000,
        'status' => RkaItem::STATUS_PENDING,
    ]);
    $itemB = RkaItem::create([
        'rka_header_id' => $headerB->id,
        'rka_indicator_id' => $indicatorB->id,
        'account_id' => $account->id,
        'description' => 'Item B',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 10000,
        'status' => RkaItem::STATUS_PENDING,
    ]);

    $service = app(RkaReviewService::class);
    $service->bulkApproveItems($headerA, [$itemA->id, $itemB->id]);

    $itemA->refresh();
    $itemB->refresh();
    expect($itemA->status)->toBe('APPROVED')
        ->and($itemB->status)->toBe(RkaItem::STATUS_PENDING);

    $service->bulkMoveItems($headerA, [$itemA->id, $itemB->id], $deptC->id, 'Pindah unit');

    $itemA->refresh();
    $itemB->refresh();
    expect($itemA->status)->toBe('MOVED')
        ->and($itemB->status)->toBe(RkaItem::STATUS_PENDING);
});

it('resolves active fiscal year dynamically via RkaSchedule::currentFiscalYear() (BUG-013)', function () {
    $user = User::factory()->create();
    RkaSchedule::query()->update(['is_active' => false]);

    RkaSchedule::create([
        'fiscal_year' => '2028/2029',
        'period_range' => 'Sep 2028 - Agu 2029',
        'phase_name' => 'Pengusulan',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    expect(RkaSchedule::currentFiscalYear())->toBe('2028/2029');
});

it('excludes imported items in calculatePaguRka() and groups incomingAdjustments() safely (BUG-014, BUG-015)', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $user = User::factory()->create();
    $dept = Department::create([
        'type' => 'PRODI',
        'code' => 'PG-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept Pagu Test',
    ]);
    $otherDept = Department::create([
        'type' => 'UPT',
        'code' => 'OT-'.$suffix,
        'level_code' => '2',
        'name' => 'Dept Other Test',
    ]);
    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Pengusulan',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'is_active' => true,
        'created_by' => $user->id,
    ]);
    $setting = RkaSetting::create([
        'setting_name' => 'Setting PG '.$suffix,
        'director_user_id' => $user->id,
        'director_name' => 'Direktur',
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);
    $account = Account::create([
        'code' => '9.5.'.$suffix,
        'code_clean' => '95'.$suffix,
        'name' => 'Akun PG '.$suffix,
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);
    $header = RkaHeader::create([
        'department_id' => $dept->id,
        'schedule_id' => $schedule->id,
        'setting_id' => $setting->id,
        'status' => RkaHeader::STATUS_DRAFT,
    ]);
    $indicator = RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $account->id,
        'program_name' => 'Program PG',
        'activity_name' => 'Kegiatan PG',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $account->id,
        'description' => 'Item Normal',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 50000,
        'is_imported' => false,
    ]);
    RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $account->id,
        'description' => 'Item Imported',
        'proposed_volume' => 3,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
        'is_imported' => true,
    ]);

    $pagu = $header->calculatePaguRka();
    expect($pagu)->toBe(100000.0)
        ->and($header->calculateProposedTotal())->toBe(100000.0);

    $otherHeader = RkaHeader::create([
        'department_id' => $otherDept->id,
        'schedule_id' => $schedule->id,
        'setting_id' => $setting->id,
        'status' => RkaHeader::STATUS_IN_REVIEW,
    ]);
    $sharedAdj = RkaAdjustment::create([
        'rka_header_id' => $otherHeader->id,
        'account_id' => $account->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'section_name' => 'Shared Cost',
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        'description' => 'Shared ke dept',
        'target_unit_id' => $dept->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 50000,
        'proposed_amount' => 50000,
        'approved_amount' => 50000,
        'adjusted_by' => $user->id,
    ]);
    $unrelatedAdj = RkaAdjustment::create([
        'rka_header_id' => $otherHeader->id,
        'account_id' => $account->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'section_name' => 'Shared Cost',
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        'description' => 'Shared ke unit lain',
        'target_unit_id' => $otherDept->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 25000,
        'proposed_amount' => 25000,
        'approved_amount' => 25000,
        'adjusted_by' => $user->id,
    ]);

    $results = $header->incomingAdjustments()
        ->where('action_type', RkaAdjustment::ACTION_SHARED_COST)
        ->pluck('id')
        ->all();

    expect($results)->toContain($sharedAdj->id)
        ->and($results)->not->toContain($unrelatedAdj->id);
});

it('enables super_admin gate interception in filament-shield config (BUG-016)', function () {
    expect(config('filament-shield.super_admin.define_via_gate'))->toBeTrue();

    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    expect($superAdmin->can('AnyArbitraryUnseededPermission'))->toBeTrue();
});
