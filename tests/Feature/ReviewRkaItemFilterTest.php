<?php

use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use App\Services\RkaReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

it('displays APPROVED, partially-approved TRIMMED, and REJECTED in Item Final Disahkan while hiding zero-approved TRIMMED and PENDING', function () {
    $ctx = makeReviewFilterContext();
    $this->actingAs($ctx['pemohon']);

    // Pemohon default: showAllItems = false (Item Final Disahkan)
    Livewire::test(ReviewRkaPage::class, ['record' => $ctx['header']->id])
        ->set('activeTab', 'sheet_rka')
        ->assertSet('showAllItems', false)
        ->assertOk()
        // APPROVED, TRIMMED > 0, dan REJECTED tampil pada Item Final Disahkan
        ->assertSee('Server Rackmount')
        ->assertSee('PC Medium')
        ->assertSee('TRIMMED')
        ->assertSee('273.000.000')
        ->assertSee('117.000.000')
        ->assertSee('35')
        ->assertSee('15')
        ->assertSee('Kursi Gaming')
        ->assertSee('Ditolak')
        // TRIMMED 0 dan PENDING tidak tampil pada Item Final Disahkan
        ->assertDontSee('Printer Dot Matrix')
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

it('calculates Item Final Disahkan, Tampilkan Semua Item, and Rekapitulasi subtotals accurately including REJECTED proposed total with zero approved', function () {
    $ctx = makeReviewFilterContext();
    $service = app(RkaReviewService::class);

    // Item Final Disahkan: Server Rackmount (50jt / 50jt) + PC Medium (273jt / 117jt) + Kursi Gaming REJECTED (12.5jt / 0)
    $finalHierarchy = $service->calculateBudgetHierarchy((string) $ctx['indicator']->id, false);
    expect((float) $finalHierarchy['totalUsulan'])->toBe(335500000.0)
        ->and((float) $finalHierarchy['totalApproved'])->toBe(167000000.0);

    // Tampilkan Semua Item: totalUsulan mencakup semua 5 item (50 + 273 + 6 + 12.5 + 12 = 353.5jt), totalApproved tetap 167jt
    $allHierarchy = $service->calculateBudgetHierarchy((string) $ctx['indicator']->id, true);
    expect((float) $allHierarchy['totalUsulan'])->toBe(353500000.0)
        ->and((float) $allHierarchy['totalApproved'])->toBe(167000000.0);

    // Rekapitulasi (calculateRekapByKelompok & calculateAccountHierarchy) menghitung APPROVED + TRIMMED > 0 + REJECTED (usulan=335.5jt, approved=167jt)
    $rekap = $service->calculateRekapByKelompok($ctx['header']);
    $accHierarchy = $service->calculateAccountHierarchy($ctx['header']);
    expect((float) $rekap['total_approved'])->toBe(167000000.0)
        ->and((float) $rekap['total_usulan_review'])->toBe(335500000.0)
        ->and((float) $accHierarchy['grandTotalUsulan'])->toBe(335500000.0)
        ->and((float) $accHierarchy['grandTotalApproved'])->toBe(167000000.0);
});

it('automatically marks zero-approved BELANJA items as REJECTED, creates idempotent Section A history, and reconciles totals without duplication', function () {
    $ctx = makeReviewFilterContext();
    $importService = app(ApprovedRkaImportService::class);

    $draftSchedule = RkaSchedule::create([
        'fiscal_year' => '2030/2031',
        'period_range' => 'September 2030 – Agustus 2031',
        'phase_name' => 'Zero Approved '.uniqid(),
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDays(20),
        'is_active' => true,
        'created_by' => $ctx['pemohon']->id,
    ]);

    $draftHeader = RkaHeader::create([
        'schedule_id' => $draftSchedule->id,
        'department_id' => $ctx['header']->department_id,
        'setting_id' => $ctx['header']->setting_id,
        'unit_head_user_id' => $ctx['pemohon']->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'is_locked' => false,
        'pagu_budget' => 50000000.0,
    ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    $detailAccount = Account::query()->where('code', 'like', '5.2.%')->orderByRaw('LENGTH(code) DESC')->firstOrFail();
    RkaIndicator::create([
        'rka_header_id' => $draftHeader->id,
        'account_id' => $account52->id,
        'program_name' => 'Program Operasional',
        'activity_name' => 'Operasional Layanan',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    // Buat workbook native dengan 1 item APPROVED, 1 item TRIMMED, dan 1 item volume disetujui = 0 (tanpa entri di DAFTAR PENYESUAIAN)
    $spreadsheet = new Spreadsheet;
    $identitas = $spreadsheet->getActiveSheet();
    $identitas->setTitle('IDENTITAS');
    $identitas->setCellValue('B3', 'Unit Kerja');
    $identitas->setCellValue('C3', 'UPT ICT');

    $ringkasan = $spreadsheet->createSheet();
    $ringkasan->setTitle('RINGKASAN');
    $ringkasan->setCellValue('B6', 'Usulan Awal Unit');
    $ringkasan->setCellValue('C6', 40000000);

    $belanja = $spreadsheet->createSheet();
    $belanja->setTitle('Belanja Barang dan Jasa');
    $belanja->setCellValue('A1', 'Program');
    $belanja->setCellValue('B1', 'Program Operasional');
    $belanja->setCellValue('A3', 'Kode Rekening');
    $belanja->setCellValue('B3', 'Uraian');
    $belanja->setCellValue('C3', 'Vol');
    $belanja->setCellValue('D3', 'Satuan');
    $belanja->setCellValue('E3', 'Harga Satuan');
    $belanja->setCellValue('F3', 'Jumlah Usulan');
    $belanja->setCellValue('G3', 'Vol Disetujui');
    $belanja->setCellValue('H3', 'Jumlah Disetujui');

    // Baris 4: APPROVED
    $belanja->setCellValue('A4', $detailAccount->code);
    $belanja->setCellValue('B4', 'Sewa Cloud Server');
    $belanja->setCellValue('C4', 1);
    $belanja->setCellValue('D4', 'Tahun');
    $belanja->setCellValue('E4', 20000000);
    $belanja->setCellValue('F4', 20000000);
    $belanja->setCellValue('G4', 1);
    $belanja->setCellValue('H4', 20000000);

    // Baris 5: TRIMMED
    $belanja->setCellValue('A5', $detailAccount->code);
    $belanja->setCellValue('B5', 'Lisensi Antivirus');
    $belanja->setCellValue('C5', 10);
    $belanja->setCellValue('D5', 'Lisensi');
    $belanja->setCellValue('E5', 1000000);
    $belanja->setCellValue('F5', 10000000);
    $belanja->setCellValue('G5', 5);
    $belanja->setCellValue('H5', 5000000);

    // Baris 6: Volume disetujui 0, nilai disetujui 0 (TIDAK ada di DAFTAR PENYESUAIAN)
    $belanja->setCellValue('A6', $detailAccount->code);
    $belanja->setCellValue('B6', 'Pengadaan Drone Dokumentasi');
    $belanja->setCellValue('C6', 2);
    $belanja->setCellValue('D6', 'Unit');
    $belanja->setCellValue('E6', 5000000);
    $belanja->setCellValue('F6', 10000000);
    $belanja->setCellValue('G6', 0);
    $belanja->setCellValue('H6', 0);

    $adj = $spreadsheet->createSheet();
    $adj->setTitle('DAFTAR PENYESUAIAN');
    $adj->setCellValue('A2', 'A. USULAN UNIT YANG DIPINDAH ATAU TIDAK DIANGGARKAN DI RKA UNIT INI');
    $adj->setCellValue('A3', 'No');
    $adj->setCellValue('B3', 'Kode Rekening');
    $adj->setCellValue('C3', 'Uraian Usulan');
    $adj->setCellValue('D3', 'Volume');
    $adj->setCellValue('E3', 'Unit Tujuan');
    $adj->setCellValue('F3', 'Jumlah Usulan (Rp)');
    $adj->setCellValue('G3', 'Status');
    $adj->setCellValue('H3', 'Keterangan');

    $ba = $spreadsheet->createSheet();
    $ba->setTitle('BERITA ACARA');
    $rekap = $spreadsheet->createSheet();
    $rekap->setTitle('REKAP');

    $tmpPath = sys_get_temp_dir().'/zero_approved_'.uniqid().'.xlsx';
    (new Xlsx($spreadsheet))->save($tmpPath);

    try {
        $preview = $importService->preview(
            filePath: $tmpPath,
            originalFilename: 'zero_approved.xlsx',
            user: $ctx['pemohon'],
            departmentId: $draftHeader->department_id,
            scheduleId: $draftHeader->schedule_id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $draftHeader,
        );

        $importService->executeDraftImport($preview['token'], $draftHeader, $ctx['pemohon']);

        // 3 item di rka_items, item ke-3 berstatus REJECTED dengan proposed_total = 10jt & approved_total = 0
        $drone = RkaItem::where('rka_header_id', $draftHeader->id)
            ->where('description', 'Pengadaan Drone Dokumentasi')
            ->firstOrFail();

        expect($drone->status)->toBe(RkaItem::STATUS_REJECTED)
            ->and((float) $drone->proposed_volume)->toBe(2.0)
            ->and((float) $drone->proposed_total)->toBe(10000000.0)
            ->and((float) $drone->approved_volume)->toBe(0.0)
            ->and((float) $drone->approved_total)->toBe(0.0);

        // Otomatis tercipta 1 histori Section A tanpa destination_unit_id palsu
        $autoAdj = RkaAdjustment::where('rka_header_id', $draftHeader->id)
            ->where('rka_item_id', $drone->id)
            ->where('section_type', RkaAdjustment::SECTION_A)
            ->firstOrFail();

        expect($autoAdj->action_type)->toBe(RkaAdjustment::ACTION_REJECTED)
            ->and($autoAdj->destination_unit_id)->toBeNull()
            ->and((float) $autoAdj->proposed_amount)->toBe(10000000.0)
            ->and((float) $autoAdj->approved_amount)->toBe(0.0)
            ->and($autoAdj->notes)->toContain('volume disetujui 0');

        // Idempotency: panggil ulang sinkronisasi tidak membuat histori ganda
        $refSync = new ReflectionMethod($importService, 'syncImplicitZeroApprovedSectionAAdjustments');
        $refSync->setAccessible(true);
        $refSync->invoke($importService, $draftHeader->fresh(), $ctx['pemohon']->id);

        expect(RkaAdjustment::where('rka_header_id', $draftHeader->id)->count())->toBe(1)
            ->and(RkaItem::where('rka_header_id', $draftHeader->id)->count())->toBe(3);

        // Total Usulan = 20jt + 10jt + 10jt = 40jt; Total Disetujui = 20jt + 5jt + 0 = 25jt
        $reviewService = app(RkaReviewService::class);
        $accSummary = $reviewService->calculateAccountHierarchy($draftHeader->fresh());
        expect((float) $accSummary['grandTotalUsulan'])->toBe(40000000.0)
            ->and((float) $accSummary['grandTotalApproved'])->toBe(25000000.0);
    } finally {
        @unlink($tmpPath);
    }
});
