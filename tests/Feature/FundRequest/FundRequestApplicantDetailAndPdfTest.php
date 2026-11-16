<?php

use App\Filament\Resources\FundRequests\Pages\ViewFundRequest;
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
use App\Services\FundRequest\FundRequestDetailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeDetailPdfContext(): array
{
    RkaSchedule::query()->where('is_active', true)->update(['is_active' => false]);
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    foreach (['ViewAny:FundRequest', 'View:FundRequest', 'Create:FundRequest', 'Update:FundRequest'] as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        $pemohonRole->givePermissionTo($perm);
        $keuanganRole->givePermissionTo($perm);
    }

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $p3mDept = Department::where('code', 'P3M')->firstOrFail();

    $ictUser = User::factory()->create(['name' => 'Detail Pemohon ICT '.$suffix]);
    $p3mUser = User::factory()->create(['name' => 'Detail Pemohon P3M '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Detail Keuangan '.$suffix]);

    $ictUser->assignRole($pemohonRole);
    $p3mUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);
    $ictDept->users()->attach($ictUser->id);
    $p3mDept->users()->attach($p3mUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2037/2038',
        'period_range' => 'September 2037 – Agustus 2038',
        'phase_name' => 'Detail Test '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Detail Setting '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Direktur Uji',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    $detailAccount = Account::query()->where('code', 'like', '5.2.%')->orderByRaw('LENGTH(code) DESC')->firstOrFail();

    $rkaHeader = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $ictUser->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 50000000.0,
    ]);

    $indicator = RkaIndicator::create([
        'rka_header_id' => $rkaHeader->id,
        'account_id' => $account52->id,
        'program_name' => 'Program Detail',
        'activity_name' => 'Kegiatan Detail',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $rkaItem1 = RkaItem::create([
        'rka_header_id' => $rkaHeader->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Item Disetujui Penuh',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 1000000,
        'approved_volume' => 2,
        'approved_total' => 2000000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    $rkaItem2 = RkaItem::create([
        'rka_header_id' => $rkaHeader->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Item Ditolak Keuangan',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 1500000,
        'approved_volume' => 1,
        'approved_total' => 1500000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    $rkaItem3 = RkaItem::create([
        'rka_header_id' => $rkaHeader->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Item Masih Pending',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 500000,
        'approved_volume' => 1,
        'approved_total' => 500000,
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
            'allocated_amount' => 10000000.0,
        ]
    );

    Storage::disk('public')->put('fund-requests/test-lampiran.txt', 'lampiran-uji');

    $fundRequest = app(FundRequestBudgetService::class)->createFundRequest(
        $ictUser,
        [
            'document_number' => 'FR-DET-'.$suffix,
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ictDept->id,
            'activity_name' => 'Workshop Keamanan Jaringan',
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Bendahara ICT',
            'attachments' => ['fund-requests/test-lampiran.txt'],
        ],
        [
            ['rka_item_id' => $rkaItem1->id, 'volume' => 2],
            ['rka_item_id' => $rkaItem2->id, 'volume' => 1],
            ['rka_item_id' => $rkaItem3->id, 'volume' => 1],
        ]
    );

    $items = $fundRequest->items()->orderBy('id')->get();
    $items[0]->update(['approved_volume' => 2, 'approved_price' => 2000000, 'verification_status' => 'approved', 'finance_note' => 'Sesuai RKA']);
    $items[1]->update(['approved_volume' => 0, 'approved_price' => 0, 'verification_status' => 'rejected', 'finance_note' => 'Tidak mendesak']);
    // items[2] tetap pending (verification_status = null)

    $fundRequest->approvals()->create([
        'stage' => 'kabakku',
        'action' => 'approved',
        'approved_amount' => 2000000,
        'note' => 'Verifikasi sebagian selesai',
        'user_id' => $keuangan->id,
    ]);
    $fundRequest->update(['status' => 'verified_kabakku', 'total_approved' => 2000000]);

    return compact('ictUser', 'p3mUser', 'keuangan', 'fundRequest', 'detailAccount');
}

it('renders applicant detail page with all items (approved, rejected, pending), account recap, approvals, and enforces unit scope', function () {
    $ctx = makeDetailPdfContext();

    // Pemohon unit lain (P3M) ditolak 403
    $this->actingAs($ctx['p3mUser']);
    Livewire::test(ViewFundRequest::class, ['record' => $ctx['fundRequest']->id])
        ->assertForbidden();

    // Pemohon pemilik pengajuan dapat mengakses halaman detail dengan seluruh data konsisten
    $this->actingAs($ctx['ictUser']);
    Livewire::test(ViewFundRequest::class, ['record' => $ctx['fundRequest']->id])
        ->assertOk()
        ->assertSee($ctx['fundRequest']->document_number)
        ->assertSee('Workshop Keamanan Jaringan')
        ->assertSee('Item Disetujui Penuh')
        ->assertSee('Item Ditolak Keuangan')
        ->assertSee('Item Masih Pending')
        ->assertSee('4.000.000')
        ->assertSee('2.000.000')
        ->assertSee('1.500.000')
        ->assertSee('Verifikasi sebagian selesai')
        ->assertSee('test-lampiran.txt')
        ->assertSee('Cetak PDF');
});

it('generates consistent PDF for authorized users, marks non-approved status clearly, and protects PDF & attachments from cross-unit access', function () {
    $ctx = makeDetailPdfContext();

    // Cross-unit pemohon ditolak pada route PDF & attachment
    $this->actingAs($ctx['p3mUser']);
    $this->get(route('fund-requests.pdf-preview', ['record' => $ctx['fundRequest']->id]))
        ->assertForbidden();
    $this->get(route('fund-requests.attachment', ['record' => $ctx['fundRequest']->id, 'index' => 0]))
        ->assertForbidden();

    // Pemilik pengajuan dapat mencetak PDF & mengunduh lampiran
    $this->actingAs($ctx['ictUser']);
    $pdfResponse = $this->get(route('fund-requests.pdf-preview', ['record' => $ctx['fundRequest']->id]));
    $pdfResponse->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($pdfResponse->headers->get('Content-Disposition'))->toContain('Fund-Request-')
        ->and(str_starts_with($pdfResponse->getContent(), '%PDF-'))->toBeTrue();

    // Pastikan HTML PDF yang dirender menggunakan data yang sama persis dengan FundRequestDetailService
    $detail = app(FundRequestDetailService::class)->build($ctx['fundRequest']);
    $html = view('pdf.fund-request', ['detail' => $detail, 'record' => $detail['record']])->render();
    expect($html)->toContain('Item Disetujui Penuh')
        ->toContain('Item Ditolak Keuangan')
        ->toContain('Item Masih Pending')
        ->toContain('4.000.000')
        ->toContain('2.000.000')
        ->toContain('Belum Disahkan')
        ->toContain('belum memperoleh pengesahan akhir Direktur');

    // Setelah status menjadi approved, PDF menampilkan status Disetujui Final
    $ctx['fundRequest']->update(['status' => 'approved']);
    $approvedDetail = app(FundRequestDetailService::class)->build($ctx['fundRequest']->fresh());
    $approvedHtml = view('pdf.fund-request', ['detail' => $approvedDetail, 'record' => $approvedDetail['record']])->render();
    expect($approvedHtml)->toContain('Disetujui Final')
        ->not->toContain('belum memperoleh pengesahan akhir Direktur');

    // Attachment download berhasil
    $this->get(route('fund-requests.attachment', ['record' => $ctx['fundRequest']->id, 'index' => 0]))
        ->assertOk();
});
