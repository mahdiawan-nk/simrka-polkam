<?php

use App\Filament\Resources\FundRequests\Pages\VerifyFundRequest;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\FundRequest\FundRequestBudgetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeVerifyWorkflowContext(): array
{
    RkaSchedule::query()->where('is_active', true)->update(['is_active' => false]);
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $wadirRole = Role::firstOrCreate(['name' => 'wadir_keuangan', 'guard_name' => 'web']);
    $direkturRole = Role::firstOrCreate(['name' => 'direktur', 'guard_name' => 'web']);

    foreach (['ViewAny:FundRequest', 'View:FundRequest', 'Create:FundRequest', 'Update:FundRequest'] as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        foreach ([$pemohonRole, $keuanganRole, $wadirRole, $direkturRole] as $role) {
            $role->givePermissionTo($perm);
        }
    }

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();

    $pemohon = User::factory()->create(['name' => 'Verify Pemohon '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Verify Keuangan '.$suffix]);
    $wadir = User::factory()->create(['name' => 'Verify Wadir '.$suffix]);
    $direktur = User::factory()->create(['name' => 'Verify Direktur '.$suffix]);

    $pemohon->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);
    $wadir->assignRole($wadirRole);
    $direktur->assignRole($direkturRole);
    $ictDept->users()->attach($pemohon->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2036/2037',
        'period_range' => 'September 2036 – Agustus 2037',
        'phase_name' => 'Verify Workflow '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Verify Setting '.$suffix,
        'director_user_id' => $direktur->id,
        'director_name' => $direktur->name,
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    $detailAccount = Account::query()->where('code', 'like', '5.2.%')->orderByRaw('LENGTH(code) DESC')->firstOrFail();

    $rkaHeader = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $pemohon->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 50000000.0,
    ]);

    $indicator = RkaIndicator::create([
        'rka_header_id' => $rkaHeader->id,
        'account_id' => $account52->id,
        'program_name' => 'Program Layanan TI',
        'activity_name' => 'Pemeliharaan Server',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $rkaItem1 = RkaItem::create([
        'rka_header_id' => $rkaHeader->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Sewa VPS Cloud',
        'proposed_volume' => 10,
        'unit_symbol' => 'Bulan',
        'unit_price' => 1000000,
        'approved_volume' => 10,
        'approved_total' => 10000000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    $rkaItem2 = RkaItem::create([
        'rka_header_id' => $rkaHeader->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Lisensi Firewall',
        'proposed_volume' => 4,
        'unit_symbol' => 'Unit',
        'unit_price' => 2000000,
        'approved_volume' => 4,
        'approved_total' => 8000000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    DepartmentBudget::updateOrCreate(
        [
            'department_id' => $ictDept->id,
            'account_id' => $detailAccount->id,
            'fiscal_year' => $schedule->fiscal_year,
        ],
        [
            'schedule_id' => $schedule->id,
            'allocated_amount' => 18000000.0,
        ]
    );

    $fundRequest = app(FundRequestBudgetService::class)->createFundRequest(
        $pemohon,
        [
            'document_number' => 'FR-VER-'.$suffix,
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ictDept->id,
            'activity_name' => 'Pengadaan Layanan TI',
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Vendor Cloud',
        ],
        [
            ['rka_item_id' => $rkaItem1->id, 'volume' => 5], // 5.000.000
            ['rka_item_id' => $rkaItem2->id, 'volume' => 2], // 4.000.000
        ]
    );

    return compact('pemohon', 'keuangan', 'wadir', 'direktur', 'detailAccount', 'rkaItem1', 'rkaItem2', 'fundRequest');
}

it('verifies items via right-side slide-over with RKA budget context, validates limits, and aggregates Bagian F accurately', function () {
    $ctx = makeVerifyWorkflowContext();
    $this->actingAs($ctx['keuangan']);

    $frItems = $ctx['fundRequest']->items()->orderBy('id')->get();
    $item1 = $frItems[0]; // 5 x 1jt = 5jt
    $item2 = $frItems[1]; // 2 x 2jt = 4jt

    $lw = Livewire::test(VerifyFundRequest::class, ['record' => $ctx['fundRequest']->id])
        ->assertOk()
        ->call('openApprovalModal', $item1->id)
        ->assertSet('isModalOpen', true)
        ->assertSet('selectedItemRkaApprovedTotal', 10000000.0)
        ->assertSet('selectedItemRemainingBefore', 10000000.0)
        ->assertSee('sm:max-w-[680px]', false)
        ->assertSee('Ringkasan Anggaran Item RKA');

    // Validasi: tidak boleh melebihi volume dimohonkan (5)
    $lw->set('modal_approved_volume', 8)
        ->call('saveItemApproval')
        ->assertHasErrors(['modal_approved_volume']);

    // Verifikasi item 1 disetujui sebagian: 3 x 1jt = 3jt
    $lw->set('modal_approved_volume', 3)
        ->assertSet('modal_approved_price', 3000000.0)
        ->assertSet('modal_verification_status', 'partial')
        ->assertSet('hasUnsavedModalChanges', true)
        ->set('modal_finance_note', 'Disesuaikan 3 bulan')
        ->call('saveItemApproval')
        ->assertHasNoErrors()
        ->assertSet('isModalOpen', false);

    // Verifikasi item 2 ditolak (0)
    $lw->call('openApprovalModal', $item2->id)
        ->set('modal_verification_status', 'rejected')
        ->assertSet('modal_approved_price', 0.0)
        ->set('modal_finance_note', 'Ditunda periode depan')
        ->call('saveItemApproval')
        ->assertHasNoErrors();

    $verifications = $lw->get('verifications');
    expect($verifications)->toHaveCount(1)
        ->and((float) $verifications[0]['total_requested'])->toBe(9000000.0)
        ->and((float) $verifications[0]['amount_approved'])->toBe(3000000.0)
        ->and((float) $verifications[0]['selisih'])->toBe(6000000.0)
        ->and((float) $verifications[0]['remaining_budget'])->toBe(18000000.0)
        ->and($verifications[0]['status'])->toBe('Disetujui Sebagian');

    // Buka Lihat Rincian Bagian F
    $lw->call('openAccountDetail', $ctx['detailAccount']->id)
        ->assertSet('detailAccountId', $ctx['detailAccount']->id)
        ->assertSee('Sewa VPS Cloud')
        ->assertSee('Lisensi Firewall');
});

it('requires rejection reason and enforces sequential approval stages from keuangan to wadir to direktur', function () {
    $ctx = makeVerifyWorkflowContext();

    // 1. Keuangan menolak tanpa alasan -> gagal validasi
    $this->actingAs($ctx['keuangan']);
    Livewire::test(VerifyFundRequest::class, ['record' => $ctx['fundRequest']->id])
        ->set('action_type', 'reject')
        ->set('verification_note', '')
        ->call('saveVerification')
        ->assertHasErrors(['verification_note']);

    // Keuangan menyetujui item 1 (5jt) dan meneruskan ke Wadir
    $item1 = $ctx['fundRequest']->items()->first();
    Livewire::test(VerifyFundRequest::class, ['record' => $ctx['fundRequest']->id])
        ->call('openApprovalModal', $item1->id)
        ->call('saveItemApproval')
        ->set('action_type', 'approve')
        ->set('verification_note', 'Dokumen lengkap')
        ->call('saveVerification')
        ->assertRedirect();

    expect($ctx['fundRequest']->fresh()->status)->toBe('verified_kabakku')
        ->and((float) $ctx['fundRequest']->fresh()->total_approved)->toBe(5000000.0);

    // 2. Wadir Keuangan meneruskan ke Direktur
    $this->actingAs($ctx['wadir']);
    Livewire::test(VerifyFundRequest::class, ['record' => $ctx['fundRequest']->id])
        ->set('action_type', 'approve')
        ->set('verification_note', 'Disetujui Wadir')
        ->call('saveVerification')
        ->assertRedirect();

    expect($ctx['fundRequest']->fresh()->status)->toBe('verified_wadir_bakku');

    // 3. Direktur mengesahkan final
    $this->actingAs($ctx['direktur']);
    Livewire::test(VerifyFundRequest::class, ['record' => $ctx['fundRequest']->id])
        ->set('action_type', 'approve')
        ->set('verification_note', 'Disetujui Direktur')
        ->call('saveVerification')
        ->assertRedirect();

    $final = $ctx['fundRequest']->fresh();
    expect($final->status)->toBe('approved')
        ->and((float) $final->total_approved)->toBe(5000000.0)
        ->and($final->approvals()->count())->toBe(3);
});
