<?php

use App\Exports\ApprovedRkaTemplateExport;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\RkaApprovedTemplateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(DatabaseTransactions::class);

function makeApprovedExportContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $direktur = User::factory()->create(['name' => 'Direktur '.$suffix]);
    $reviewer = User::factory()->create(['name' => 'Wadir '.$suffix]);
    $pemohon = User::factory()->create(['name' => 'Pemohon '.$suffix]);
    $otherPemohon = User::factory()->create(['name' => 'Other '.$suffix]);

    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $viewPerm = Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);

    $reviewer->assignRole($keuanganRole);
    $reviewer->givePermissionTo($viewPerm);

    $pemohon->assignRole($pemohonRole);
    $pemohon->givePermissionTo($viewPerm);

    $otherPemohon->assignRole($pemohonRole);
    $otherPemohon->givePermissionTo($viewPerm);

    $dept = Department::create([
        'type' => 'UPT',
        'code' => 'EXP1-'.$suffix,
        'level_code' => '2',
        'name' => 'UPT Export '.$suffix,
    ]);

    $otherDept = Department::create([
        'type' => 'PRODI',
        'code' => 'EXP2-'.$suffix,
        'level_code' => '3',
        'name' => 'Prodi Lain '.$suffix,
    ]);

    $pemohon->departments()->attach($dept->id);
    $otherPemohon->departments()->attach($otherDept->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Export Phase '.$suffix,
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDays(5),
        'is_active' => true,
        'created_by' => $direktur->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Export '.$suffix,
        'director_user_id' => $direktur->id,
        'director_name' => $direktur->name,
        'director_nrp' => 'NRP-999',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);

    $header = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $dept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $pemohon->id,
        'reviewer_user_id' => $reviewer->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'approved_at' => now(),
        'pagu_budget' => 500000,
    ]);

    $acc52 = Account::firstOrCreate(
        ['code' => '5.2'],
        ['code_clean' => '52', 'name' => 'Belanja Barang dan Jasa', 'level' => 1, 'level_label' => 'Kelompok', 'is_active' => true]
    );
    $acc521 = Account::firstOrCreate(
        ['code' => '5.2.1'],
        ['code_clean' => '521', 'name' => 'Belanja Bahan Habis Dipakai', 'parent_id' => $acc52->id, 'parent_code' => '5.2', 'level' => 2, 'level_label' => 'Sub Kelompok', 'is_active' => true]
    );
    $detail52 = Account::create([
        'code' => '5.2.1.'.$suffix,
        'code_clean' => '521'.$suffix,
        'name' => 'Belanja ATK '.$suffix,
        'parent_id' => $acc521->id,
        'parent_code' => '5.2.1',
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);

    $acc53 = Account::firstOrCreate(
        ['code' => '5.3'],
        ['code_clean' => '53', 'name' => 'Belanja Modal', 'level' => 1, 'level_label' => 'Kelompok', 'is_active' => true]
    );
    $detail53 = Account::create([
        'code' => '5.3.1.'.$suffix,
        'code_clean' => '531'.$suffix,
        'name' => 'Belanja Peralatan '.$suffix,
        'parent_id' => $acc53->id,
        'parent_code' => '5.3',
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);

    $ind52 = RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $acc52->id,
        'program_name' => 'Program Operasional',
        'activity_name' => 'Pengadaan ATK',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $ind53 = RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $acc53->id,
        'program_name' => 'Program Sarpras',
        'activity_name' => 'Pengadaan Alat',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    // Item 1: APPROVED
    $itemApproved = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $ind52->id,
        'account_id' => $detail52->id,
        'form_type' => '5.2',
        'description' => 'Kertas A4',
        'proposed_volume' => 10,
        'unit_symbol' => 'Rim',
        'unit_price' => 50000,
        'approved_volume' => 8,
        'approved_total' => 400000,
        'status' => 'TRIMMED',
        'is_imported' => false,
        'review_notes' => 'Dipangkas 2 rim',
    ]);

    // Item 2: PENDING
    $itemPending = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $ind52->id,
        'account_id' => $detail52->id,
        'form_type' => '5.2',
        'description' => 'Printer Cadangan',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 2000000,
        'approved_volume' => 0,
        'approved_total' => 0,
        'status' => RkaItem::STATUS_PENDING,
        'is_imported' => false,
    ]);

    // Item 3: Imported from Section B (5.3)
    $itemImported = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $ind53->id,
        'account_id' => $detail53->id,
        'form_type' => '5.3',
        'description' => 'Server Rack [P3M]',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 5000000,
        'approved_volume' => 1,
        'approved_total' => 5000000,
        'status' => 'APPROVED',
        'is_imported' => true,
        'review_notes' => 'Impor dari Section B',
    ]);

    // All 8 adjustment statuses
    $statusesA = [
        RkaAdjustment::ACTION_MOVED,
        RkaAdjustment::ACTION_BUDGETED_ELSEWHERE,
        RkaAdjustment::ACTION_REJECTED_WITH_ADJUSTMENT,
        RkaAdjustment::ACTION_REJECTED,
        RkaAdjustment::ACTION_REMOVED,
        RkaAdjustment::ACTION_PENDING,
    ];
    foreach ($statusesA as $st) {
        RkaAdjustment::create([
            'rka_header_id' => $header->id,
            'rka_indicator_id' => $ind52->id,
            'rka_item_id' => $itemPending->id,
            'section_type' => RkaAdjustment::SECTION_A,
            'action_type' => $st,
            'account_id' => $detail52->id,
            'description' => "Adj {$st}",
            'destination_unit_id' => $st === RkaAdjustment::ACTION_MOVED ? $otherDept->id : null,
            'proposed_volume' => 1,
            'unit_symbol' => 'Unit',
            'unit_price' => 100000,
            'proposed_amount' => 100000,
            'approved_amount' => 0,
            'adjusted_by' => $reviewer->id,
        ]);
    }

    RkaAdjustment::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $ind53->id,
        'rka_item_id' => $itemPending->id,
        'imported_rka_item_id' => $itemImported->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_SHARED_COST,
        'account_id' => $detail53->id,
        'description' => 'Adj SHARED_COST',
        'target_unit_id' => $otherDept->id,
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 5000000,
        'proposed_amount' => 5000000,
        'approved_amount' => 5000000,
        'is_imported' => true,
        'adjusted_by' => $reviewer->id,
    ]);

    RkaAdjustment::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $ind52->id,
        'section_type' => RkaAdjustment::SECTION_B,
        'action_type' => RkaAdjustment::ACTION_ADDED_BY_REVIEWER,
        'account_id' => $detail52->id,
        'description' => 'Adj ADDED_BY_REVIEWER',
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 0,
        'proposed_amount' => 0,
        'approved_amount' => 0,
        'adjusted_by' => $reviewer->id,
    ]);

    return compact('header', 'reviewer', 'pemohon', 'otherPemohon', 'direktur', 'dept', 'otherDept', 'itemApproved', 'itemPending', 'itemImported');
}

test('export enforces APPROVED status, RBAC permission, and organizational scope', function () {
    $ctx = makeApprovedExportContext();
    $service = app(RkaApprovedTemplateService::class);

    // 1. User tanpa permission ditolak
    $noPermUser = User::factory()->create();
    expect(fn () => $service->export($ctx['header'], $noPermUser))->toThrow(AuthorizationException::class);

    // 2. User di luar organizational scope ditolak
    expect(fn () => $service->export($ctx['header'], $ctx['otherPemohon']))->toThrow(AuthorizationException::class);

    // 3. Status selain APPROVED ditolak dan tidak menghasilkan log export
    $logsBefore = RkaLog::where('rka_header_id', $ctx['header']->id)->where('action', RkaLog::ACTION_EXPORT_RKA)->count();
    $ctx['header']->update(['status' => RkaHeader::STATUS_IN_APPROVED]);
    expect(fn () => $service->export($ctx['header'], $ctx['reviewer']))->toThrow(InvalidArgumentException::class);
    expect(RkaLog::where('rka_header_id', $ctx['header']->id)->where('action', RkaLog::ACTION_EXPORT_RKA)->count())->toBe($logsBefore);
});

test('export generates valid 2-sheet xlsx matching reference layout, formulas, technical IDs, and logs once', function () {
    $ctx = makeApprovedExportContext();
    $service = app(RkaApprovedTemplateService::class);

    $this->actingAs($ctx['reviewer']);

    $beforeItems = RkaItem::where('rka_header_id', $ctx['header']->id)->count();
    $beforeAdj = RkaAdjustment::where('rka_header_id', $ctx['header']->id)->count();
    $beforeBudgets = DepartmentBudget::count();

    $response = $service->export($ctx['header'], $ctx['reviewer'], 'export-test-key-1');
    expect($response)->toBeInstanceOf(BinaryFileResponse::class);
    expect($response->getFile()->getFilename())->toEndWith('.xlsx');

    // Retry dengan idempotencyKey sama tidak menggandakan log
    $service->export($ctx['header'], $ctx['reviewer'], 'export-test-key-1');
    expect(RkaLog::where('rka_header_id', $ctx['header']->id)->where('action', RkaLog::ACTION_EXPORT_RKA)->count())->toBe(1);

    // Read-only integrity check
    expect($ctx['header']->fresh()->status)->toBe(RkaHeader::STATUS_APPROVED)
        ->and(RkaItem::where('rka_header_id', $ctx['header']->id)->count())->toBe($beforeItems)
        ->and(RkaAdjustment::where('rka_header_id', $ctx['header']->id)->count())->toBe($beforeAdj)
        ->and(DepartmentBudget::count())->toBe($beforeBudgets);

    // Parse generated xlsx using PhpSpreadsheet
    $data = $service->prepareWorkbookData($ctx['header']);
    $rawXlsx = Excel::raw(new ApprovedRkaTemplateExport($data), Maatwebsite\Excel\Excel::XLSX);
    $tmpPath = tempnam(sys_get_temp_dir(), 'rka_exp_').'.xlsx';
    file_put_contents($tmpPath, $rawXlsx);

    $spreadsheet = IOFactory::load($tmpPath);
    @unlink($tmpPath);

    // Tepat 2 worksheet sesuai spesifikasi
    expect($spreadsheet->getSheetCount())->toBe(2)
        ->and($spreadsheet->getSheet(0)->getTitle())->toBe('DAFTAR PENYESUAIAN')
        ->and($spreadsheet->getSheet(1)->getTitle())->toBe('BELANJA');

    $adjSheet = $spreadsheet->getSheet(0);
    $belanjaSheet = $spreadsheet->getSheet(1);

    // Page setup sesuai referensi
    expect($adjSheet->getPageSetup()->getOrientation())->toBe(PageSetup::ORIENTATION_LANDSCAPE)
        ->and($adjSheet->getPageSetup()->getPaperSize())->toBe(PageSetup::PAPERSIZE_A4)
        ->and($adjSheet->getPageSetup()->getFitToWidth())->toBe(1)
        ->and($belanjaSheet->getPageSetup()->getOrientation())->toBe(PageSetup::ORIENTATION_LANDSCAPE)
        ->and($belanjaSheet->getPageSetup()->getPaperSize())->toBe(PageSetup::PAPERSIZE_A4)
        ->and($belanjaSheet->getPageSetup()->getFitToWidth())->toBe(1);

    // Verifikasi 8 status adjustment & PENDING di Section A
    expect(count($data['daftar_penyesuaian']['section_a']))->toBe(6)
        ->and(count($data['daftar_penyesuaian']['section_b']))->toBe(2)
        ->and(collect($data['daftar_penyesuaian']['section_a'])->pluck('STATUS_PENYESUAIAN')->all())->toContain('PENDING')
        ->and($data['daftar_penyesuaian']['section_b'][0]['IMPORTED_ITEM_ID'])->toBe($ctx['itemImported']->id)
        ->and($data['daftar_penyesuaian']['section_b'][0]['ORIGIN_ITEM_ID'])->toBe($ctx['itemPending']->id);

    // Verifikasi BELANJA: tidak ada duplikasi item, usulan != disetujui, kelompok 5.2 & 5.3 terpetakan
    expect($data['belanja']['grand_total_proposed'])->toBe(7500000.0)
        ->and($data['belanja']['grand_total_approved'])->toBe(5400000.0)
        ->and(count($data['belanja']['groups']))->toBe(2);

    // Verifikasi formula tidak menghasilkan error pada worksheet BELANJA
    expect($belanjaSheet->getCell('A1')->getValue())->toBe('RENCANA KERJA DAN ANGGARAN')
        ->and((string) $belanjaSheet->getCell('C6')->getValue())->toStartWith('=')
        ->and((float) $belanjaSheet->getCell('C6')->getCalculatedValue())->toBe(7500000.0)
        ->and((float) $belanjaSheet->getCell('C7')->getCalculatedValue())->toBe(5400000.0);
});
