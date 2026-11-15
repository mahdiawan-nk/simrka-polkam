<?php

use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaApproval;
use App\Models\RkaGenerateConfig;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\RkaActivityLogService;
use App\Services\RkaApprovalService;
use App\Services\RkaGenerateService;
use App\Services\RkaItemService;
use App\Services\RkaReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

function makeRkaLogContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));
    $user = User::factory()->create(['name' => 'Pengusul '.$suffix]);
    $reviewer = User::factory()->create(['name' => 'Wadir '.$suffix]);
    $direktur = User::factory()->create(['name' => 'Direktur '.$suffix]);

    $originDept = Department::create([
        'type' => 'PRODI',
        'code' => 'LG1-'.$suffix,
        'level_code' => '3',
        'name' => 'Prodi Log '.$suffix,
    ]);

    $targetDept = Department::create([
        'type' => 'UPT',
        'code' => 'LG2-'.$suffix,
        'level_code' => '2',
        'name' => 'UPT Log '.$suffix,
    ]);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Log Phase '.$suffix,
        'start_at' => now()->subDay(),
        'end_at' => now()->addDays(3),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Log '.$suffix,
        'director_user_id' => $direktur->id,
        'director_name' => $direktur->name,
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);

    $level1 = Account::firstOrCreate(
        ['code' => '5.2'],
        [
            'code_clean' => '52',
            'name' => 'Belanja Barang dan Jasa',
            'level' => 1,
            'level_label' => 'Kelompok',
            'is_active' => true,
        ]
    );

    $detailCode = '5.2.88.'.$suffix;
    $detailAccount = Account::create([
        'code' => $detailCode,
        'code_clean' => str_replace('.', '', $detailCode),
        'name' => 'Belanja Uji Log '.$suffix,
        'parent_id' => $level1->id,
        'parent_code' => $level1->code,
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);

    $originHeader = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $originDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $user->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'pagu_budget' => 0,
    ]);

    $targetHeader = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $targetDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $user->id,
        'status' => RkaHeader::STATUS_IN_REVIEW,
        'pagu_budget' => 0,
    ]);

    return compact(
        'suffix',
        'user',
        'reviewer',
        'direktur',
        'originDept',
        'targetDept',
        'schedule',
        'setting',
        'level1',
        'detailAccount',
        'originHeader',
        'targetHeader'
    );
}

test('full RKA lifecycle records accurate non-duplicated RkaLog entries per RkaHeader and actor', function () {
    $ctx = makeRkaLogContext();
    $itemService = app(RkaItemService::class);
    $reviewService = app(RkaReviewService::class);
    $approvalService = app(RkaApprovalService::class);

    // 1. Pemohon menambah program, item, update item, hapus item, dan submit
    $this->actingAs($ctx['user']);

    $indicator = $itemService->addProgram($ctx['originHeader'], [
        'account_id' => $ctx['level1']->id,
        'program_name' => 'Program Log',
        'activity_name' => 'Kegiatan Log',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $itemA = $itemService->addItem($ctx['originHeader'], [
        'account_id' => $ctx['detailAccount']->id,
        'form_type' => '5.2',
        'description' => 'Item A',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
    ], $indicator->id);

    $itemB = $itemService->addItem($ctx['originHeader'], [
        'account_id' => $ctx['detailAccount']->id,
        'form_type' => '5.2',
        'description' => 'Item B',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 50000,
    ], $indicator->id);

    $itemService->updateItem($ctx['originHeader'], $itemA->id, [
        'account_id' => $ctx['detailAccount']->id,
        'description' => 'Item A Updated',
        'proposed_volume' => 3,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
    ]);

    $itemService->deleteItem($ctx['originHeader'], $itemB->id);

    $approvalService->submitByPemohon($ctx['originHeader'], $ctx['user']);

    // 2. Wadir mulai review, review item, move item, tambah adjustment, teruskan ke Direktur
    $this->actingAs($ctx['reviewer']);

    $approvalService->startReviewByWadir($ctx['originHeader'], $ctx['reviewer']);

    $reviewService->reviewItem($ctx['originHeader'], [
        'item_id' => $itemA->id,
        'action_type' => RkaAdjustment::ACTION_MOVED,
        'approved_volume' => 3,
        'approved_price' => 100000,
        'approved_total' => 300000,
        'unit_symbol' => 'Unit',
        'destination_unit_id' => $ctx['targetDept']->id,
        'review_notes' => 'Dipindah ke UPT Log',
    ], ['item_id' => $itemA->id]);

    $adj = $reviewService->addAdjustment($ctx['originHeader'], (string) $indicator->id, [
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_ADDED_BY_REVIEWER,
        'account_id' => $ctx['detailAccount']->id,
        'description' => 'Tambahan Reviewer',
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 200000,
        'proposed_amount' => 200000,
        'approved_amount' => 200000,
    ]);

    $approvalService->forwardToDirektur($ctx['originHeader'], $ctx['reviewer']);

    // 3. Direktur minta revisi -> Pemohon ajukan ulang -> Wadir teruskan -> Direktur sahkan
    $this->actingAs($ctx['direktur']);
    $approvalService->requireRevisionByDirektur($ctx['originHeader'], $ctx['direktur'], 'Revisi catatan');

    $this->actingAs($ctx['user']);
    $approvalService->submitByPemohon($ctx['originHeader'], $ctx['user']);

    $this->actingAs($ctx['reviewer']);
    $approvalService->forwardToDirektur($ctx['originHeader'], $ctx['reviewer']);

    $this->actingAs($ctx['direktur']);
    $approvalService->approveByDirektur($ctx['originHeader'], $ctx['direktur']);

    $originLogs = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->orderBy('id')->get();
    $targetLogs = RkaLog::where('rka_header_id', $ctx['targetHeader']->id)->orderBy('id')->get();

    expect($originLogs->pluck('action')->all())->toBe([
        RkaLog::ACTION_ADD_INDICATOR,
        RkaLog::ACTION_ADD_ITEM,
        RkaLog::ACTION_ADD_ITEM,
        RkaLog::ACTION_UPDATE_ITEM,
        RkaLog::ACTION_DELETE_ITEM,
        RkaLog::ACTION_SUBMIT_RKA,
        RkaLog::ACTION_START_REVIEW,
        RkaLog::ACTION_ADJUSTMENT_MOVE,
        RkaLog::ACTION_ADD_ADJUSTMENT,
        RkaLog::ACTION_FORWARD_DIREKTUR,
        RkaLog::ACTION_REQUEST_REVISION,
        RkaLog::ACTION_RESUBMIT_RKA,
        RkaLog::ACTION_FORWARD_DIREKTUR,
        RkaLog::ACTION_APPROVE_RKA,
    ]);

    // Cek pelaku tercatat sesuai peran
    expect($originLogs->firstWhere('action', RkaLog::ACTION_SUBMIT_RKA)->user_id)->toBe($ctx['user']->id)
        ->and($originLogs->firstWhere('action', RkaLog::ACTION_START_REVIEW)->user_id)->toBe($ctx['reviewer']->id)
        ->and($originLogs->firstWhere('action', RkaLog::ACTION_APPROVE_RKA)->user_id)->toBe($ctx['direktur']->id);

    // Cek pemindahan item juga tercatat pada RKA target
    expect($targetLogs)->toHaveCount(1)
        ->and($targetLogs->first()->action)->toBe(RkaLog::ACTION_ADJUSTMENT_MOVE)
        ->and($targetLogs->first()->user_id)->toBe($ctx['reviewer']->id)
        ->and($targetLogs->first()->subject_type)->toBe(RkaItem::class)
        ->and($targetLogs->first()->subject_id)->toBe($itemA->id);

    // Cek subject_type, subject_id, dan before/after diff pada berbagai entitas
    $indicatorLog = $originLogs->firstWhere('action', RkaLog::ACTION_ADD_INDICATOR);
    $updateItemLog = $originLogs->firstWhere('action', RkaLog::ACTION_UPDATE_ITEM);
    $adjustmentLog = $originLogs->firstWhere('action', RkaLog::ACTION_ADD_ADJUSTMENT);
    $approveLog = $originLogs->firstWhere('action', RkaLog::ACTION_APPROVE_RKA);

    expect($indicatorLog->subject_type)->toBe(RkaIndicator::class)
        ->and($indicatorLog->subject_id)->toBe($indicator->id)
        ->and($updateItemLog->subject_type)->toBe(RkaItem::class)
        ->and($updateItemLog->subject_id)->toBe($itemA->id)
        ->and((float) $updateItemLog->old_values['proposed_volume'])->toBe(2.0)
        ->and((float) $updateItemLog->new_values['proposed_volume'])->toBe(3.0)
        ->and($adjustmentLog->subject_type)->toBe(RkaAdjustment::class)
        ->and($adjustmentLog->subject_id)->toBe($adj->id)
        ->and($approveLog->subject_type)->toBe(RkaApproval::class);

    // Cek scopeForSubject mengembalikan seluruh log untuk itemA (add, update, move origin, move target)
    expect(RkaLog::forSubject($itemA)->count())->toBe(4);
});

test('RkaGenerateService records GENERATE_RKA log when creating new header', function () {
    $ctx = makeRkaLogContext();
    $this->actingAs($ctx['user']);

    $newDept = Department::create([
        'type' => 'PRODI',
        'code' => 'GEN-'.$ctx['suffix'],
        'level_code' => '3',
        'name' => 'Prodi Generate '.$ctx['suffix'],
    ]);

    $config = RkaGenerateConfig::create([
        'rka_setting_id' => $ctx['setting']->id,
        'config_name' => 'Config Log '.$ctx['suffix'],
        'is_active' => true,
        'selected_department_ids' => [$newDept->id],
        'template_indicators' => [],
        'created_by' => $ctx['user']->id,
    ]);

    app(RkaGenerateService::class)->generate($ctx['schedule'], $config);

    $generatedHeader = RkaHeader::where('schedule_id', $ctx['schedule']->id)
        ->where('department_id', $newDept->id)
        ->first();

    expect($generatedHeader)->not->toBeNull();

    $log = RkaLog::where('rka_header_id', $generatedHeader->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->action)->toBe(RkaLog::ACTION_GENERATE_RKA)
        ->and($log->user_id)->toBe($ctx['user']->id);
});

test('failed transaction does not leave invalid RkaLog entry', function () {
    $ctx = makeRkaLogContext();
    $this->actingAs($ctx['user']);

    $beforeCount = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();

    expect(fn () => app(RkaReviewService::class)->addAdjustment($ctx['originHeader'], null, [
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        'account_id' => $ctx['detailAccount']->id,
        'description' => 'Gagal tanpa unit_symbol',
        'proposed_amount' => 100000,
        'approved_amount' => 100000,
    ]))->toThrow(InvalidArgumentException::class);

    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count())->toBe($beforeCount);
});

test('ReviewRkaPage activityLogs only returns logs belonging to the opened RkaHeader', function () {
    $ctx = makeRkaLogContext();
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $viewPerm = Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);
    $ctx['reviewer']->assignRole($keuanganRole);
    $ctx['reviewer']->givePermissionTo($viewPerm);

    $this->actingAs($ctx['reviewer']);

    RkaLog::record(RkaLog::ACTION_SUBMIT_RKA, $ctx['originHeader']->id, newValues: ['description' => 'Log Origin']);
    RkaLog::record(RkaLog::ACTION_SUBMIT_RKA, $ctx['targetHeader']->id, newValues: ['description' => 'Log Target']);

    $page = new ReviewRkaPage;
    $page->mount($ctx['originHeader']->id);

    $logs = $page->activityLogs;
    expect($logs->total())->toBe(1)
        ->and($logs->first()->rka_header_id)->toBe($ctx['originHeader']->id)
        ->and($logs->first()->description)->toBe('Log Origin');
});

test('RkaActivityLogService sanitizes sensitive keys from old and new values', function () {
    $ctx = makeRkaLogContext();
    $this->actingAs($ctx['user']);

    $log = app(RkaActivityLogService::class)->log(
        action: RkaLog::ACTION_UPDATE_RKA,
        headerId: $ctx['originHeader']->id,
        subject: $ctx['originHeader'],
        description: 'Uji sanitasi payload',
        oldValues: ['status' => 'DRAFT', 'password' => 'secret123'],
        newValues: ['status' => 'SUBMITTED', 'remember_token' => 'abc', 'nested' => ['api_token' => 'xyz', 'safe' => true]],
    );

    expect($log->old_values)->toBe(['status' => 'DRAFT'])
        ->and($log->new_values)->toBe(['status' => 'SUBMITTED', 'nested' => ['safe' => true]])
        ->and($log->subject_label)->toBe('RKA Header');
});

test('bulkMoveItems records single log on origin and target headers without duplicates', function () {
    $ctx = makeRkaLogContext();
    $this->actingAs($ctx['reviewer']);

    $indicator = RkaIndicator::create([
        'rka_header_id' => $ctx['originHeader']->id,
        'account_id' => $ctx['level1']->id,
        'program_name' => 'Program Bulk',
        'activity_name' => 'Kegiatan Bulk',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $item = RkaItem::create([
        'rka_header_id' => $ctx['originHeader']->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $ctx['detailAccount']->id,
        'form_type' => '5.2',
        'description' => 'Item Bulk Move',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 50000,
        'total_proposed' => 100000,
        'status' => RkaItem::STATUS_PENDING,
    ]);

    app(RkaReviewService::class)->bulkMoveItems(
        $ctx['originHeader'],
        [$item->id],
        $ctx['targetDept']->id,
        'Pindah massal',
    );

    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->where('action', RkaLog::ACTION_ADJUSTMENT_MOVE)->count())->toBe(1)
        ->and(RkaLog::where('rka_header_id', $ctx['targetHeader']->id)->where('action', RkaLog::ACTION_ADJUSTMENT_MOVE)->count())->toBe(1);
});

test('ReviewRkaPage activityLogs supports action and user filtering and enforces view policy', function () {
    $ctx = makeRkaLogContext();
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $viewPerm = Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);
    $ctx['reviewer']->assignRole($keuanganRole);
    $ctx['reviewer']->givePermissionTo($viewPerm);

    // User tanpa permission ditolak 403
    $this->actingAs($ctx['user']);
    $unauthorizedPage = new ReviewRkaPage;
    expect(fn () => $unauthorizedPage->mount($ctx['originHeader']->id))->toThrow(HttpException::class);

    // User berwenang dapat memfilter log berdasarkan action dan user
    $this->actingAs($ctx['reviewer']);
    app(RkaActivityLogService::class)->log(RkaLog::ACTION_ADD_ITEM, $ctx['originHeader']->id, $ctx['originHeader'], 'Tambah item', userId: $ctx['user']->id);
    app(RkaActivityLogService::class)->log(RkaLog::ACTION_REVIEW_UPDATE, $ctx['originHeader']->id, $ctx['originHeader'], 'Review item', userId: $ctx['reviewer']->id);

    $page = new ReviewRkaPage;
    $page->mount($ctx['originHeader']->id);

    expect($page->activityLogs->total())->toBe(2);

    $page->logActionFilter = RkaLog::ACTION_REVIEW_UPDATE;
    unset($page->activityLogs);
    expect($page->activityLogs->total())->toBe(1)
        ->and($page->activityLogs->first()->action)->toBe(RkaLog::ACTION_REVIEW_UPDATE);

    $page->logActionFilter = null;
    $page->logUserFilter = (string) $ctx['user']->id;
    unset($page->activityLogs);
    expect($page->activityLogs->total())->toBe(1)
        ->and($page->activityLogs->first()->user_id)->toBe($ctx['user']->id);

    // Pastikan view activity-history bersifat read-only (tanpa tombol edit/hapus)
    $html = view('filament.resources.rka-headers.components.activity-history', [
        'logs' => $page->activityLogs,
        'actionOptions' => $page->activityActionOptions,
        'userOptions' => $page->activityUserOptions,
    ])->render();

    expect($html)->toContain('read-only')
        ->and($html)->not->toContain('wire:click="delete')
        ->and($html)->not->toContain('wire:click="edit');
});

test('every RKA business activity produces exactly one RkaLog per affected header and no duplicates', function () {
    $ctx = makeRkaLogContext();
    $itemService = app(RkaItemService::class);
    $reviewService = app(RkaReviewService::class);
    $approvalService = app(RkaApprovalService::class);

    $this->actingAs($ctx['user']);

    // 1. Add Program/Indicator -> +1 log
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $indicator = $itemService->addProgram($ctx['originHeader'], [
        'account_id' => $ctx['level1']->id,
        'program_name' => 'Program Single Log',
        'activity_name' => 'Kegiatan Single Log',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    // 2. Add Item -> +1 log
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $item = $itemService->addItem($ctx['originHeader'], [
        'account_id' => $ctx['detailAccount']->id,
        'form_type' => '5.2',
        'description' => 'Item Single Log',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
    ], $indicator->id);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    // 3. Update Item twice with distinct changes -> +1 log each (+2 total)
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $itemService->updateItem($ctx['originHeader'], $item->id, [
        'account_id' => $ctx['detailAccount']->id,
        'description' => 'Item Single Log Rev 1',
        'proposed_volume' => 3,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
    ]);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    $itemService->updateItem($ctx['originHeader'], $item->id, [
        'account_id' => $ctx['detailAccount']->id,
        'description' => 'Item Single Log Rev 2',
        'proposed_volume' => 4,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
    ]);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(2);

    // 4. Submit RKA -> +1 log
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $approvalService->submitByPemohon($ctx['originHeader'], $ctx['user']);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    // 5. Start review -> +1 log
    $this->actingAs($ctx['reviewer']);
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $approvalService->startReviewByWadir($ctx['originHeader'], $ctx['reviewer']);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    // 6. Review Item (PENDING) -> +1 log (reproduces & verifies fix for id 108/109 bug)
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $reviewService->reviewItem($ctx['originHeader'], [
        'item_id' => $item->id,
        'action_type' => 'PENDING',
        'approved_volume' => 0,
        'approved_price' => 0,
        'approved_total' => 0,
        'unit_symbol' => 'Unit',
        'review_notes' => 'Ditunda',
    ], ['item_id' => $item->id]);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    // 7. Add Adjustment -> +1 log, Delete Adjustment -> +1 log
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $adj = $reviewService->addAdjustment($ctx['originHeader'], (string) $indicator->id, [
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_ADDED_BY_REVIEWER,
        'account_id' => $ctx['detailAccount']->id,
        'description' => 'Adjustment Single Log',
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 50000,
        'proposed_amount' => 50000,
        'approved_amount' => 50000,
    ]);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $reviewService->deleteAdjustment($adj->id);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);

    // 8. Approve RKA -> +1 log
    $this->actingAs($ctx['direktur']);
    $before = RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count();
    $approvalService->approveByDirektur($ctx['originHeader'], $ctx['direktur']);
    expect(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count() - $before)->toBe(1);
});

test('RkaActivityLogService prevents duplicate logs when same idempotencyKey is retried', function () {
    $ctx = makeRkaLogContext();
    $this->actingAs($ctx['user']);

    $log1 = app(RkaActivityLogService::class)->log(
        action: RkaLog::ACTION_UPDATE_RKA,
        headerId: $ctx['originHeader']->id,
        subject: $ctx['originHeader'],
        description: 'Retryable update',
        idempotencyKey: 'evt-retry-001',
    );

    $log2 = app(RkaActivityLogService::class)->log(
        action: RkaLog::ACTION_UPDATE_RKA,
        headerId: $ctx['originHeader']->id,
        subject: $ctx['originHeader'],
        description: 'Retryable update',
        idempotencyKey: 'evt-retry-001',
    );

    expect($log2->id)->toBe($log1->id)
        ->and(RkaLog::where('rka_header_id', $ctx['originHeader']->id)->count())->toBe(1);
});
