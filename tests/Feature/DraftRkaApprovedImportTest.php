<?php

use App\Filament\Resources\RkaHeaders\Pages\ImportApprovedRkaPage;
use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Filament\Resources\RkaHeaders\Pages\RkaFormIndicatorAndItem;
use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaApproval;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeDraftImportContext(float $draftPagu = 250000000.0): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $viewPerm = Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);
    $pemohonRole->givePermissionTo($viewPerm);

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $p3mDept = Department::where('code', 'P3M')->firstOrFail();

    $ictUser = User::factory()->create(['name' => 'Draft Pemohon ICT '.$suffix]);
    $p3mUser = User::factory()->create(['name' => 'Draft Pemohon P3M '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Draft Finance '.$suffix]);

    $ictUser->assignRole($pemohonRole);
    $p3mUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);

    $ictDept->users()->attach($ictUser->id);
    $p3mDept->users()->attach($p3mUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2027/2028',
        'period_range' => 'September 2027 – Agustus 2028',
        'phase_name' => 'Draft Import '.$suffix,
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDays(20),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Draft '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    $draftHeader = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $ictUser->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'is_locked' => false,
        'pagu_budget' => $draftPagu,
    ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    $indicator = RkaIndicator::create([
        'rka_header_id' => $draftHeader->id,
        'account_id' => $account52->id,
        'program_name' => 'Program Layanan TI Finance',
        'activity_name' => 'Pengelolaan Infrastruktur TI',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [
            ['indikator' => 'Uptime layanan', 'target' => '99%', 'satuan' => '%'],
        ],
    ]);

    return compact(
        'ictDept',
        'p3mDept',
        'ictUser',
        'p3mUser',
        'keuangan',
        'schedule',
        'setting',
        'draftHeader',
        'indicator',
    );
}

it('imports approved excel into Finance DRAFT RkaHeader, sets pagu_budget from Usulan Awal Unit, syncs DepartmentBudget, and transitions to APPROVED without fake RkaApproval', function () {
    $ctx = makeDraftImportContext(250000000.0);
    $service = app(ApprovedRkaImportService::class);

    $headersBefore = RkaHeader::count();
    $approvalsBefore = RkaApproval::count();
    $indicatorsBefore = RkaIndicator::where('rka_header_id', $ctx['draftHeader']->id)->count();

    $preview = $service->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['draftHeader'],
    );

    expect($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['target_draft']['rka_header_id'])->toBe($ctx['draftHeader']->id)
        ->and($preview['target_draft']['indicator_count'])->toBe(1)
        ->and((float) $preview['target_draft']['pagu_budget'])->toBe(250000000.0)
        ->and((float) $preview['target_draft']['new_pagu_budget'])->toBe(156583000.0)
        ->and($preview['target_draft']['pagu_awal_source'])->toBe('RINGKASAN!C6')
        ->and($preview['target_draft']['final_status'])->toBe(RkaHeader::STATUS_APPROVED);

    $batch = $service->executeDraftImport($preview['token'], $ctx['draftHeader'], $ctx['ictUser']);
    $freshHeader = $ctx['draftHeader']->fresh();

    // Tidak ada header baru, tidak ada RkaApproval palsu, indikator Finance utuh
    expect(RkaHeader::count())->toBe($headersBefore)
        ->and(RkaApproval::count())->toBe($approvalsBefore)
        ->and($batch->rka_header_id)->toBe($ctx['draftHeader']->id)
        ->and($batch->import_type)->toBe(RkaApprovedImportBatch::TYPE_DRAFT)
        ->and($batch->summary['provenance'] ?? null)->toBe('APPROVED_DOCUMENT_IMPORT')
        ->and($freshHeader->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($freshHeader->is_locked)->toBeTrue()
        // Pagu Budget diambil dari Usulan Awal Unit (RINGKASAN!C6 = 156.583.000), BUKAN SUM item belanja
        ->and((float) $freshHeader->pagu_budget)->toBe(156583000.0)
        ->and($freshHeader->department_id)->toBe($ctx['ictDept']->id)
        ->and($freshHeader->schedule_id)->toBe($ctx['schedule']->id)
        ->and(RkaIndicator::where('rka_header_id', $freshHeader->id)->count())->toBe($indicatorsBefore);

    $freshIndicator = $ctx['indicator']->fresh();
    expect($freshIndicator->program_name)->toBe('Program Layanan TI Finance')
        ->and($freshIndicator->activity_name)->toBe('Pengelolaan Infrastruktur TI');

    // Item & adjustment masuk ke Draft Header yang dibuka
    $itemsSum = (float) RkaItem::where('rka_header_id', $freshHeader->id)->sum('approved_total');
    expect(RkaItem::where('rka_header_id', $freshHeader->id)->count())->toBe(33)
        ->and($itemsSum)->not->toBe(156583000.0)
        ->and(RkaAdjustment::where('rka_header_id', $freshHeader->id)->count())->toBe(10);

    // DepartmentBudget tersinkronisasi untuk unit & tahun anggaran target
    $syncedBudgetTotal = (float) DepartmentBudget::where('department_id', $ctx['ictDept']->id)
        ->where('fiscal_year', $ctx['schedule']->fiscal_year)
        ->sum('allocated_amount');
    expect($syncedBudgetTotal)->toBe($itemsSum);

    // Section B tersimpan ke target_unit_id dengan destination_unit_id = NULL
    $sectionB = RkaAdjustment::where('rka_header_id', $freshHeader->id)
        ->where('section_type', RkaAdjustment::SECTION_B)
        ->get();
    expect($sectionB)->toHaveCount(10);
    foreach ($sectionB as $adj) {
        expect($adj->target_unit_id)->not->toBeNull()
            ->and($adj->destination_unit_id)->toBeNull();
    }

    // Activity Log tercatat 1 kali dengan provenance
    $logs = RkaLog::where('rka_header_id', $freshHeader->id)
        ->where('action', RkaLog::ACTION_APPROVED_IMPORT_EXECUTED)
        ->get();
    expect($logs)->toHaveCount(1)
        ->and($logs->first()->new_values['provenance'] ?? null)->toBe('APPROVED_DOCUMENT_IMPORT')
        ->and($logs->first()->new_values['status'] ?? null)->toBe(RkaHeader::STATUS_APPROVED)
        ->and((float) ($logs->first()->new_values['pagu_budget'] ?? 0))->toBe(156583000.0);
});

it('supports importing approved excel from another unit/period into own DRAFT RkaHeader and sets Usulan Awal Unit pagu & APPROVED status', function () {
    $ctx = makeDraftImportContext(500000000.0);
    $service = app(ApprovedRkaImportService::class);

    $tifDept = Department::where('code', 'PRODI-TIF')->firstOrFail();
    $tifUser = User::factory()->create(['name' => 'Draft Pemohon TIF']);
    $tifUser->assignRole('pemohon');
    $tifDept->users()->attach($tifUser->id);

    $tifDraft = RkaHeader::create([
        'schedule_id' => $ctx['schedule']->id,
        'department_id' => $tifDept->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $tifUser->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'is_locked' => false,
        'pagu_budget' => 500000000.0,
    ]);

    RkaIndicator::create([
        'rka_header_id' => $tifDraft->id,
        'account_id' => Account::where('code', '5.2')->firstOrFail()->id,
        'program_name' => 'Program Prodi TIF Finance',
        'activity_name' => 'Pembelajaran TIF',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    // Pemohon TIF mengimpor workbook P3M ke RKA Draft TIF
    $preview = $service->preview(
        filePath: base_path('data-rka/10_RKA_2026-2027_P3M.xlsx'),
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $tifUser,
        departmentId: $tifDept->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $tifDraft,
    );

    expect($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['department_id'])->toBe($tifDept->id)
        ->and((float) $preview['target_draft']['new_pagu_budget'])->toBe(496090000.0);

    $batch = $service->executeDraftImport($preview['token'], $tifDraft, $tifUser);
    $freshHeader = $tifDraft->fresh();

    expect($freshHeader->department_id)->toBe($tifDept->id)
        ->and($freshHeader->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and($freshHeader->is_locked)->toBeTrue()
        ->and((float) $freshHeader->pagu_budget)->toBe(496090000.0)
        ->and($batch->belanja_count)->toBe(30)
        ->and($batch->adjustment_count)->toBe(23);

    $sectionA = RkaAdjustment::where('rka_header_id', $freshHeader->id)
        ->where('section_type', RkaAdjustment::SECTION_A)
        ->get();
    expect($sectionA)->toHaveCount(23);
    foreach ($sectionA as $adj) {
        expect($adj->destination_unit_id)->not->toBeNull()
            ->and($adj->target_unit_id)->toBeNull();
    }
});

it('enforces authorization, DRAFT status, and prevents replay/duplicate token imports', function () {
    $ctx = makeDraftImportContext();
    $service = app(ApprovedRkaImportService::class);

    // Pemohon unit lain & role keuangan ditolak
    expect(fn () => $service->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['draftHeader'],
    ))->toThrow(AuthorizationException::class);

    expect(fn () => $service->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['keuangan'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['draftHeader'],
    ))->toThrow(AuthorizationException::class);

    // Preview valid lalu status RKA berubah menjadi SUBMITTED sebelum finalisasi -> ditolak
    $preview = $service->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['draftHeader'],
    );

    $ctx['draftHeader']->update(['status' => RkaHeader::STATUS_SUBMITTED]);
    expect(fn () => $service->executeDraftImport($preview['token'], $ctx['draftHeader']->fresh(), $ctx['ictUser']))
        ->toThrow(InvalidArgumentException::class, 'bukan DRAFT');

    // Kembalikan status ke DRAFT lalu jalankan import berhasil -> status menjadi APPROVED
    $ctx['draftHeader']->update(['status' => RkaHeader::STATUS_DRAFT]);
    $service->executeDraftImport($preview['token'], $ctx['draftHeader']->fresh(), $ctx['ictUser']);
    expect($ctx['draftHeader']->fresh()->status)->toBe(RkaHeader::STATUS_APPROVED);

    // Retry dengan token yang sama ditolak
    expect(fn () => $service->executeDraftImport($preview['token'], $ctx['draftHeader']->fresh(), $ctx['ictUser']))
        ->toThrow(InvalidArgumentException::class, 'tidak ditemukan atau telah kedaluwarsa');

    // Preview kedua pada header yang sudah APPROVED ditolak
    expect(fn () => $service->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['draftHeader']->fresh(),
    ))->toThrow(InvalidArgumentException::class, 'bukan DRAFT');

    expect(RkaItem::where('rka_header_id', $ctx['draftHeader']->id)->count())->toBe(33);
});

it('rejects missing Usulan Awal Unit and rolls back transaction cleanly when import fails', function () {
    $ctx = makeDraftImportContext(250000000.0);
    $service = app(ApprovedRkaImportService::class);

    // Buat salinan workbook dengan RINGKASAN!C6 (Usulan Awal Unit) dikosongkan
    $tmpPath = sys_get_temp_dir().'/invalid_pagu_'.uniqid().'.xlsx';
    $spreadsheet = IOFactory::load(base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'));
    $spreadsheet->getSheetByName('RINGKASAN')->setCellValue('C6', null);
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmpPath);

    try {
        $preview = $service->preview(
            filePath: $tmpPath,
            originalFilename: 'invalid_pagu.xlsx',
            user: $ctx['ictUser'],
            departmentId: $ctx['ictDept']->id,
            scheduleId: $ctx['schedule']->id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $ctx['draftHeader'],
        );

        expect($preview['has_fatal_errors'])->toBeTrue();

        expect(fn () => $service->executeDraftImport($preview['token'], $ctx['draftHeader'], $ctx['ictUser']))
            ->toThrow(InvalidArgumentException::class);

        $fresh = $ctx['draftHeader']->fresh();
        expect($fresh->status)->toBe(RkaHeader::STATUS_DRAFT)
            ->and($fresh->is_locked)->toBeFalse()
            ->and((float) $fresh->pagu_budget)->toBe(250000000.0)
            ->and(RkaItem::where('rka_header_id', $fresh->id)->count())->toBe(0)
            ->and(RkaAdjustment::where('rka_header_id', $fresh->id)->count())->toBe(0)
            ->and(RkaLog::where('rka_header_id', $fresh->id)->where('action', RkaLog::ACTION_APPROVED_IMPORT_EXECUTED)->count())->toBe(0);
    } finally {
        @unlink($tmpPath);
    }
});

it('shows Import Approved RKA action only for DRAFT, redirects confirmImport to ReviewRkaPage, and renders ReviewRkaPage from database', function () {
    $ctx = makeDraftImportContext(250000000.0);
    $this->actingAs($ctx['ictUser']);

    Livewire::test(RkaFormIndicatorAndItem::class, ['record' => $ctx['draftHeader']->id])
        ->assertActionVisible('importApprovedRka');

    // Akses global tanpa parameter draft atau reimport diarahkan kembali ke index
    Livewire::test(ImportApprovedRkaPage::class)
        ->assertRedirect();

    // Confirm lewat Livewire ImportApprovedRkaPage -> redirect ke ReviewRkaPage
    $preview = app(ApprovedRkaImportService::class)->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['ictUser'],
        departmentId: $ctx['ictDept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['draftHeader'],
    );

    Livewire::withQueryParams(['draft' => $ctx['draftHeader']->id])
        ->test(ImportApprovedRkaPage::class)
        ->set('previewToken', $preview['token'])
        ->set('previewResult', $preview)
        ->call('confirmImport')
        ->assertRedirect(RkaHeaderResource::getUrl('review', ['record' => $ctx['draftHeader']->id]));

    $freshHeader = $ctx['draftHeader']->fresh();
    expect($freshHeader->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and((float) $freshHeader->pagu_budget)->toBe(156583000.0);

    // ReviewRkaPage merender data hasil import dari database tanpa error
    Livewire::test(ReviewRkaPage::class, ['record' => $ctx['draftHeader']->id])
        ->assertOk()
        ->assertSee($ctx['ictDept']->name);
});
