<?php

use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\RkaApprovalService;
use App\Services\RkaItemService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeRkaHeaderFixture(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));
    $user = User::factory()->create();
    $dept = Department::create([
        'type' => 'PRODI',
        'code' => 'RS-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept RKA Schema Test',
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
        'setting_name' => 'Setting RS '.$suffix,
        'director_user_id' => $user->id,
        'director_name' => 'Direktur',
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);
    $account = Account::create([
        'code' => '9.8.'.$suffix,
        'code_clean' => '98'.$suffix,
        'name' => 'Akun Uji '.$suffix,
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);
    $header = RkaHeader::create([
        'department_id' => $dept->id,
        'schedule_id' => $schedule->id,
        'setting_id' => $setting->id,
        'status' => 'DRAFT',
        'is_locked' => false,
    ]);
    $indicator = RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $account->id,
        'program_name' => 'Program Uji',
        'activity_name' => 'Kegiatan Uji',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    return compact('user', 'dept', 'schedule', 'setting', 'account', 'header', 'indicator');
}

it('submits RKA using status column and prevents double submission (BUG-006)', function () {
    $ctx = makeRkaHeaderFixture();
    $service = app(RkaItemService::class);

    $service->submitRka($ctx['header']);

    $ctx['header']->refresh();
    expect($ctx['header']->status)->toBe('submitted')
        ->and((bool) $ctx['header']->is_locked)->toBeTrue();

    expect(fn () => $service->submitRka($ctx['header']))
        ->toThrow(InvalidArgumentException::class);
});

it('requires revision without writing non-existent review_notes column and aggregates APPROVED + ACCEPTED items on approval (BUG-006, BUG-011)', function () {
    $ctx = makeRkaHeaderFixture();
    $approvalService = app(RkaApprovalService::class);

    RkaItem::create([
        'rka_header_id' => $ctx['header']->id,
        'rka_indicator_id' => $ctx['indicator']->id,
        'account_id' => $ctx['account']->id,
        'description' => 'Item Approved',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
        'approved_volume' => 2,
        'approved_total' => 200000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    RkaItem::create([
        'rka_header_id' => $ctx['header']->id,
        'rka_indicator_id' => $ctx['indicator']->id,
        'account_id' => $ctx['account']->id,
        'description' => 'Item Accepted',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 50000,
        'approved_volume' => 1,
        'approved_total' => 50000,
        'status' => RkaItem::STATUS_ACCEPTED,
    ]);

    $approvalService->requireRevisionByDirektur($ctx['header'], $ctx['user'], 'Perbaiki rincian');
    $ctx['header']->refresh();
    expect($ctx['header']->status)->toBe(RkaHeader::STATUS_REVISION_REQUIRED);

    $approvalService->approveByDirektur($ctx['header'], $ctx['user'], 'Disetujui');

    $budget = DepartmentBudget::where('department_id', $ctx['dept']->id)
        ->where('account_id', $ctx['account']->id)
        ->where('schedule_id', $ctx['schedule']->id)
        ->first();

    expect($budget)->not->toBeNull()
        ->and((float) $budget->allocated_amount)->toBe(250000.0);
});

it('does not auto-mutate submitted RKA to IN_REVIEW when loaded by pemohon (BUG-009)', function () {
    $ctx = makeRkaHeaderFixture();
    $ctx['header']->update(['status' => 'submitted']);

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);

    $pemohon = User::factory()->create();
    $pemohon->assignRole($pemohonRole);

    $keuangan = User::factory()->create();
    $keuangan->assignRole($keuanganRole);

    $this->actingAs($pemohon);
    $page = new ReviewRkaPage;
    $page->record = $ctx['header'];
    $page->selectedDepartmentId = $ctx['dept']->id;
    $page->loadRkaRecord();

    $ctx['header']->refresh();
    expect($ctx['header']->status)->toBe('submitted');

    $this->actingAs($keuangan);
    $page->loadRkaRecord();

    $ctx['header']->refresh();
    expect($ctx['header']->status)->toBe('IN_REVIEW');
});

it('resolves indicator relationship on RkaItem (BUG-010)', function () {
    $ctx = makeRkaHeaderFixture();
    $item = RkaItem::create([
        'rka_header_id' => $ctx['header']->id,
        'rka_indicator_id' => $ctx['indicator']->id,
        'account_id' => $ctx['account']->id,
        'description' => 'Item Relasi',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 10000,
    ]);

    expect($item->indicator)->not->toBeNull()
        ->and($item->indicator->id)->toBe($ctx['indicator']->id);
});
