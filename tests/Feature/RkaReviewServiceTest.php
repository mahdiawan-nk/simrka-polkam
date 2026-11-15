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

uses(DatabaseTransactions::class);

function createRkaReviewContext(): array
{
    $user = User::factory()->create();
    $suffix = strtoupper(substr(uniqid(), -6));
    $dept = Department::create([
        'type' => 'PRODI',
        'code' => 'T1-'.$suffix,
        'level_code' => '3',
        'name' => 'Prodi TIF Test',
    ]);
    $targetDept = Department::create([
        'type' => 'UPT',
        'code' => 'T2-'.$suffix,
        'level_code' => '2',
        'name' => 'UPT ICT Test',
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
        'setting_name' => 'Default 2026/2027',
        'director_user_id' => $user->id,
        'director_name' => 'Direktur',
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);
    $account = Account::create([
        'code' => '9.9.'.$suffix,
        'code_clean' => '99'.$suffix,
        'name' => 'Belanja ATK Test',
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);
    $header = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $dept->id,
        'setting_id' => $setting->id,
        'status' => RkaHeader::STATUS_IN_REVIEW,
        'pagu_budget' => 500000,
    ]);
    $indicator = RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $account->id,
        'program_name' => 'Program A',
        'activity_name' => 'Kegiatan A',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);
    $item = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $account->id,
        'form_type' => '5.2',
        'description' => 'Kertas A4',
        'proposed_volume' => 10,
        'unit_symbol' => 'Rim',
        'unit_price' => 50000,
        'status' => RkaItem::STATUS_PENDING,
    ]);

    return compact('user', 'dept', 'targetDept', 'header', 'indicator', 'item', 'account');
}

test('reviewItem preserves original proposed_volume and calculates moved adjustment amount using multiplication', function () {
    $ctx = createRkaReviewContext();
    $this->actingAs($ctx['user']);

    $service = app(RkaReviewService::class);
    $service->reviewItem(
        $ctx['header'],
        [
            'item_id' => $ctx['item']->id,
            'action_type' => 'MOVED',
            'approved_volume' => 8,
            'approved_price' => 50000,
            'approved_total' => 400000,
            'unit_symbol' => 'Rim',
            'destination_unit_id' => $ctx['targetDept']->id,
            'review_notes' => 'Dialihkan ke ICT',
        ],
        ['item_id' => $ctx['item']->id]
    );

    $ctx['item']->refresh();
    expect((float) $ctx['item']->proposed_volume)->toBe(10.0)
        ->and((float) $ctx['item']->approved_volume)->toBe(0.0)
        ->and((float) $ctx['item']->approved_total)->toBe(0.0)
        ->and($ctx['item']->is_trimmed)->toBeTrue();

    $adjustment = RkaAdjustment::where('rka_item_id', $ctx['item']->id)->first();
    expect($adjustment)->not->toBeNull()
        ->and((float) $adjustment->proposed_amount)->toBe(500000.0);
});

test('reviewItem handles APPROVED with partial volume trim accurately', function () {
    $ctx = createRkaReviewContext();
    $this->actingAs($ctx['user']);

    $service = app(RkaReviewService::class);
    $service->reviewItem(
        $ctx['header'],
        [
            'item_id' => $ctx['item']->id,
            'action_type' => 'APPROVED',
            'approved_volume' => 6,
            'approved_price' => 50000,
            'approved_total' => 300000,
            'unit_symbol' => 'Rim',
            'review_notes' => 'Dipangkas 4 rim',
        ],
        ['item_id' => $ctx['item']->id]
    );

    $ctx['item']->refresh();
    expect((float) $ctx['item']->proposed_volume)->toBe(10.0)
        ->and((float) $ctx['item']->approved_volume)->toBe(6.0)
        ->and((float) $ctx['item']->approved_total)->toBe(300000.0)
        ->and($ctx['item']->is_trimmed)->toBeTrue();
});
