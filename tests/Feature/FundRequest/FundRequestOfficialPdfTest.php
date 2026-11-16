<?php

use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequestApproval;
use App\Models\FundRequestVerification;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeOfficialPdfContext(): array
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

    $ictUser = User::factory()->create(['name' => 'Official Pemohon ICT '.$suffix]);
    $p3mUser = User::factory()->create(['name' => 'Official Pemohon P3M '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Official Keuangan '.$suffix]);

    $ictUser->assignRole($pemohonRole);
    $p3mUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);
    $ictDept->users()->attach($ictUser->id);
    $p3mDept->users()->attach($p3mUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2039/2040',
        'period_range' => 'September 2039 – Agustus 2040',
        'phase_name' => 'Official PDF Test '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $rkaFormatSetting = RkaSetting::query()->where('setting_name', 'like', '%Format RKA%')->first()
        ?? RkaSetting::create([
            'setting_name' => 'Konfigurasi Format RKA',
            'director_user_id' => $keuangan->id,
            'director_name' => 'Direktur Format RKA',
            'director_nrp' => '999999',
            'foundation_head_name' => 'Ketua Format RKA',
            'signing_place' => 'Bangkinang',
        ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    $detailAccount = Account::query()->where('code', 'like', '5.2.%')->orderByRaw('LENGTH(code) DESC')->firstOrFail();

    $rkaHeader = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $rkaFormatSetting->id,
        'unit_head_user_id' => $ictUser->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 113883000.0,
    ]);

    $indicator = RkaIndicator::create([
        'rka_header_id' => $rkaHeader->id,
        'account_id' => $account52->id,
        'program_code' => 'PRG-01',
        'program_name' => 'Belanja Modal',
        'indicator_code' => 'IND-01',
        'indicator_name' => 'Peningkatan Sarana',
    ]);

    $rkaItem = RkaItem::create([
        'rka_indicator_id' => $indicator->id,
        'rka_header_id' => $rkaHeader->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Server Enterprise',
        'proposed_volume' => 10,
        'unit_symbol' => 'Unit',
        'unit_price' => 10000000,
        'approved_volume' => 10,
        'approved_total' => 100000000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    DepartmentBudget::create([
        'department_id' => $ictDept->id,
        'account_id' => $detailAccount->id,
        'fiscal_year' => '2039/2040',
        'schedule_id' => $schedule->id,
        'allocated_amount' => 113883000.0,
    ]);

    $fundRequest = app(FundRequestBudgetService::class)->createFundRequest(
        $ictUser,
        [
            'document_number' => '013/PK.3/NOT/ICT/'.$suffix,
            'request_date' => '2026-10-05',
            'department_id' => $ictDept->id,
            'activity_name' => 'Belanja Modal Peningkatan Sarana dan Prasarana',
            'execution_date' => '2026-10-05',
            'responsible_person' => 'Mahdiawan Nurkholifah, S.Kom',
            'disbursement_type' => 'Uang muka (panjar) – dipertanggungjawabkan kemudian',
            'recipient_name' => 'Mahdiawan Nurkholifah, S.Kom',
            'bank_account_info' => null,
            'attachments' => [],
        ],
        [
            [
                'rka_item_id' => $rkaItem->id,
                'volume' => 2,
            ],
        ],
    );

    return compact('ictUser', 'p3mUser', 'keuangan', 'ictDept', 'detailAccount', 'rkaItem', 'fundRequest');
}

it('resolves active Form Permohonan Pencairan Dana config (not Format RKA) and renders official PDF with full_image kop', function () {
    $ctx = makeOfficialPdfContext();

    // Ensure a valid Form Permohonan Pencairan Dana setting exists (using existing or fresh)
    $pencairanSetting = app(FundRequestDetailService::class)->resolvePdfConfiguration();
    expect(strtolower($pencairanSetting->setting_name))->toContain('pencairan');
    expect(strtolower($pencairanSetting->setting_name))->not->toContain('format rka');

    $pdfData = app(FundRequestDetailService::class)->buildForPdf($ctx['fundRequest']);
    expect($pdfData['pdf']['terbilang'])->toBe('Dua Puluh Juta Rupiah');
    expect($pdfData['pdf']['section_c_rows'][0]['pagu_unit'])->toBe(113883000.0);
    expect($pdfData['pdf']['section_c_rows'][0]['status'])->toBe('Sesuai pagu');
    expect($pdfData['pdf']['signatures']['kabakku']['is_signed'])->toBeFalse();
    expect($pdfData['pdf']['signatures']['kabakku']['status_text'])->toBe('Menunggu Verifikasi');

    $html = view('pdf.fund-request', $pdfData)->render();
    expect($html)
        ->toContain('FORMULIR PERMOHONAN PENCAIRAN DANA')
        ->toContain('A. KEGIATAN')
        ->toContain('B. RINCIAN KEBUTUHAN')
        ->toContain('C. REKAP PER KODE AKUN')
        ->toContain('D. PEMBAYARAN KEPADA')
        ->toContain('E. LAMPIRAN')
        ->toContain('F. VERIFIKASI DAN PERSETUJUAN PER KODE')
        ->toContain('Dua Puluh Juta Rupiah')
        ->toContain($pencairanSetting->director_nrp)
        ->not->toContain('sig-status')
        ->not->toContain('#FAF3DD')
        ->not->toContain('#D9E2F3');

    $response = $this->actingAs($ctx['ictUser'])
        ->get(route('fund-requests.pdf-preview', $ctx['fundRequest']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

it('supports manual_two_logos kop configuration from RKA Settings', function () {
    $ctx = makeOfficialPdfContext();

    Storage::disk('local')->put('rka-settings/logos/test-left.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    Storage::disk('local')->put('rka-settings/logos/test-right.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

    $setting = app(FundRequestDetailService::class)->resolvePdfConfiguration();
    $setting->update([
        'pdf_header_config' => [
            'header_type' => 'manual_two_logos',
            'left_logo' => 'rka-settings/logos/test-left.png',
            'right_logo' => 'rka-settings/logos/test-right.png',
            'foundation_name' => 'YAYASAN DATUK TABANO TEST',
            'institution_name' => 'POLITEKNIK KAMPAR TEST',
            'address_line' => 'Jl. Tengku Muhammad KM.2',
            'contact_line' => 'www.poltek-kampar.ac.id',
            'show_divider_line' => true,
        ],
    ]);

    $pdfData = app(FundRequestDetailService::class)->buildForPdf($ctx['fundRequest']);
    expect($pdfData['pdf']['header']['type'])->toBe('manual_two_logos');
    expect($pdfData['pdf']['header']['left_logo_data_uri'])->toStartWith('data:image/png;base64,');
    expect($pdfData['pdf']['header']['right_logo_data_uri'])->toStartWith('data:image/png;base64,');

    $html = view('pdf.fund-request', $pdfData)->render();
    expect($html)
        ->toContain('YAYASAN DATUK TABANO TEST')
        ->toContain('POLITEKNIK KAMPAR TEST');

    $response = $this->actingAs($ctx['ictUser'])
        ->get(route('fund-requests.pdf-preview', $ctx['fundRequest']));

    $response->assertOk();
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

it('returns informative 422 error when Konfigurasi Form Permohonan Pencairan Dana is missing', function () {
    $ctx = makeOfficialPdfContext();

    // Remove all pencairan settings inside this DB transaction
    RkaSetting::query()
        ->whereRaw('LOWER(setting_name) LIKE ?', ['%pencairan%'])
        ->delete();

    $response = $this->actingAs($ctx['ictUser'])
        ->get(route('fund-requests.pdf-preview', $ctx['fundRequest']));

    $response->assertStatus(422);
    expect($response->getContent())->toContain('Konfigurasi Form Permohonan Pencairan Dana belum tersedia');
});

it('renders Section F verification, register numbers only when approved, and real approvals', function () {
    $ctx = makeOfficialPdfContext();
    $fundRequest = $ctx['fundRequest'];

    $item = $fundRequest->items()->firstOrFail();
    $item->update([
        'verification_status' => 'partial',
        'approved_volume' => 1,
        'approved_price' => 10000000,
    ]);

    FundRequestVerification::create([
        'fund_request_id' => $fundRequest->id,
        'account_id' => $ctx['detailAccount']->id,
        'final_account_id' => $ctx['detailAccount']->id,
        'remaining_budget' => 93883000.0,
        'amount_approved' => 10000000.0,
        'status' => 'Disetujui Sebagian',
        'register_proof_number' => 'REG-2026-001',
    ]);

    FundRequestApproval::create([
        'fund_request_id' => $fundRequest->id,
        'stage' => 'kabakku',
        'user_id' => $ctx['keuangan']->id,
        'action' => 'approved',
        'note' => 'OK',
    ]);

    $pdfData = app(FundRequestDetailService::class)->buildForPdf($fundRequest->fresh());
    expect($pdfData['pdf']['section_f_rows'][0]['register_proof_number'])->toBe('REG-2026-001');
    expect($pdfData['pdf']['section_f_total'])->toBe(10000000.0);
    expect($pdfData['pdf']['signatures']['kabakku']['is_signed'])->toBeTrue();
    expect($pdfData['pdf']['signatures']['wadir']['is_signed'])->toBeFalse();

    $html = view('pdf.fund-request', $pdfData)->render();
    expect($html)
        ->toContain('REG-2026-001')
        ->toContain('Disetujui Sebagian')
        ->not->toContain('sig-status-approved')
        ->not->toContain('Menunggu Persetujuan');
});

it('paginates cleanly for large item counts and enforces department authorization', function () {
    $ctx = makeOfficialPdfContext();

    // Add 20 more items to test multi-page PDF generation
    for ($i = 1; $i <= 20; $i++) {
        $ctx['fundRequest']->items()->create([
            'rka_item_id' => $ctx['rkaItem']->id,
            'account_id' => $ctx['detailAccount']->id,
            'rka_account_code_snapshot' => $ctx['detailAccount']->code,
            'rka_account_name_snapshot' => $ctx['detailAccount']->name,
            'rka_approved_volume_snapshot' => 10,
            'rka_approved_total_snapshot' => 100000000,
            'description' => 'Perangkat Tambahan '.$i,
            'volume' => 1,
            'unit' => 'Unit',
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);
    }

    $response = $this->actingAs($ctx['ictUser'])
        ->get(route('fund-requests.pdf-preview', $ctx['fundRequest']));
    $response->assertOk();
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();

    // Different department user cannot preview
    $this->actingAs($ctx['p3mUser'])
        ->get(route('fund-requests.pdf-preview', $ctx['fundRequest']))
        ->assertForbidden();
});
