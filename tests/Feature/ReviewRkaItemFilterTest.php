<?php

use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\RkaReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeReviewFilterContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $viewPerm = Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);
    $pemohonRole->givePermissionTo($viewPerm);
    $keuanganRole->givePermissionTo($viewPerm);

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();

    $pemohon = User::factory()->create(['name' => 'Filter Pemohon '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Filter Finance '.$suffix]);
    $pemohon->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);
    $ictDept->users()->attach($pemohon->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2029/2030',
        'period_range' => 'September 2029 – Agustus 2030',
        'phase_name' => 'Filter Test '.$suffix,
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDays(20),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Filter '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    $header = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $pemohon->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 400000000.0,
    ]);

    $account53 = Account::where('code', '5.3')->first()
        ?? Account::where('code', '5.2')->firstOrFail();
    $detailAccount = Account::query()->where('code', 'like', $account53->code.'.%')->orderByRaw('LENGTH(code) DESC')->firstOrFail();

    $indicator = RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $account53->id,
        'program_name' => 'Program Pengadaan TIK',
        'activity_name' => 'Pengadaan Perangkat Komputer',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    // 1. APPROVED item
    $approvedItem = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => $account53->code,
        'description' => 'Server Rackmount',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 50000000,
        'approved_volume' => 1,
        'approved_total' => 50000000,
        'status' => RkaItem::STATUS_APPROVED,
        'is_trimmed' => false,
    ]);

    // 2. TRIMMED item dengan persetujuan sebagian (PC Medium: 35 -> 15, Rp273.000.000 -> Rp117.000.000)
    $trimmedPartial = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => $account53->code,
        'description' => 'PC Medium',
        'proposed_volume' => 35,
        'unit_symbol' => 'Unit',
        'unit_price' => 7800000,
        'approved_volume' => 15,
        'approved_total' => 117000000,
        'status' => 'TRIMMED',
        'is_trimmed' => true,
    ]);

    // 3. TRIMMED item dengan persetujuan nol
    $trimmedZero = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => $account53->code,
        'description' => 'Printer Dot Matrix',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 3000000,
        'approved_volume' => 0,
        'approved_total' => 0,
        'status' => 'TRIMMED',
        'is_trimmed' => true,
    ]);

    // 4. REJECTED item
    $rejectedItem = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => $account53->code,
        'description' => 'Kursi Gaming',
        'proposed_volume' => 5,
        'unit_symbol' => 'Unit',
        'unit_price' => 2500000,
        'approved_volume' => 0,
        'approved_total' => 0,
        'status' => RkaItem::STATUS_REJECTED,
        'is_trimmed' => true,
    ]);

    // 5. PENDING item
    $pendingItem = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => $account53->code,
        'description' => 'Switch Cadangan',
        'proposed_volume' => 3,
        'unit_symbol' => 'Unit',
        'unit_price' => 4000000,
        'approved_volume' => 0,
        'approved_total' => 0,
        'status' => RkaItem::STATUS_PENDING,
        'is_trimmed' => true,
    ]);

    return compact(
        'pemohon',
        'keuangan',
        'header',
        'indicator',
        'approvedItem',
        'trimmedPartial',
        'trimmedZero',
        'rejectedItem',
        'pendingItem',
    );
}

it('displays APPROVED and partially-approved TRIMMED in Item Final Disahkan while hiding zero-approved TRIMMED, REJECTED, and PENDING', function () {
    $ctx = makeReviewFilterContext();
    $this->actingAs($ctx['pemohon']);

    // Pemohon default: showAllItems = false (Item Final Disahkan)
    Livewire::test(ReviewRkaPage::class, ['record' => $ctx['header']->id])
        ->set('activeTab', 'sheet_rka')
        ->assertSet('showAllItems', false)
        ->assertOk()
        // APPROVED & TRIMMED > 0 tampil
        ->assertSee('Server Rackmount')
        ->assertSee('PC Medium')
        ->assertSee('TRIMMED')
        ->assertSee('273.000.000')
        ->assertSee('117.000.000')
        ->assertSee('35')
        ->assertSee('15')
        // TRIMMED 0, REJECTED, dan PENDING tidak tampil pada Item Final Disahkan
        ->assertDontSee('Printer Dot Matrix')
        ->assertDontSee('Kursi Gaming')
        ->assertDontSee('Switch Cadangan');

    // Toggle ke Tampilkan Semua Item -> semua item tampil
    Livewire::test(ReviewRkaPage::class, ['record' => $ctx['header']->id])
        ->set('activeTab', 'sheet_rka')
        ->call('toggleShowAllItems', true)
        ->assertSet('showAllItems', true)
        ->assertOk()
        ->assertSee('Server Rackmount')
        ->assertSee('PC Medium')
        ->assertSee('TRIMMED')
        ->assertSee('Printer Dot Matrix')
        ->assertSee('Kursi Gaming')
        ->assertSee('Switch Cadangan');

    // Database tidak berubah akibat filter
    expect($ctx['trimmedPartial']->fresh()->status)->toBe('TRIMMED')
        ->and((float) $ctx['trimmedPartial']->fresh()->proposed_volume)->toBe(35.0)
        ->and((float) $ctx['trimmedPartial']->fresh()->approved_volume)->toBe(15.0)
        ->and((float) $ctx['trimmedPartial']->fresh()->approved_total)->toBe(117000000.0);
});

it('calculates Item Final Disahkan, Tampilkan Semua Item, and Rekapitulasi subtotals accurately without double-counting TRIMMED', function () {
    $ctx = makeReviewFilterContext();
    $service = app(RkaReviewService::class);

    // Item Final Disahkan: Server Rackmount (50jt / 50jt) + PC Medium (273jt / 117jt)
    $finalHierarchy = $service->calculateBudgetHierarchy((string) $ctx['indicator']->id, false);
    expect((float) $finalHierarchy['totalUsulan'])->toBe(323000000.0)
        ->and((float) $finalHierarchy['totalApproved'])->toBe(167000000.0);

    // Tampilkan Semua Item: totalUsulan mencakup semua 5 item (50 + 273 + 6 + 12.5 + 12 = 353.5jt), totalApproved tetap 167jt
    $allHierarchy = $service->calculateBudgetHierarchy((string) $ctx['indicator']->id, true);
    expect((float) $allHierarchy['totalUsulan'])->toBe(353500000.0)
        ->and((float) $allHierarchy['totalApproved'])->toBe(167000000.0);

    // Rekapitulasi (calculateRekapByKelompok & calculateAccountHierarchy) menghitung APPROVED + TRIMMED > 0 = 167jt tanpa double counting
    $rekap = $service->calculateRekapByKelompok($ctx['header']);
    $accHierarchy = $service->calculateAccountHierarchy($ctx['header']);
    expect((float) $rekap['total_approved'])->toBe(167000000.0)
        ->and((float) $rekap['total_usulan_review'])->toBe(335500000.0)
        ->and((float) $accHierarchy['grandTotalUsulan'])->toBe(323000000.0)
        ->and((float) $accHierarchy['grandTotalApproved'])->toBe(167000000.0);
});
