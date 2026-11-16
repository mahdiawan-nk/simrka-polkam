<?php

use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeConsistencyContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $viewPerm = Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);
    $pemohonRole->givePermissionTo($viewPerm);
    $keuanganRole->givePermissionTo($viewPerm);

    $p3mDept = Department::where('code', 'P3M')->firstOrFail();
    $tifDept = Department::where('code', 'PRODI-TIF')->firstOrFail();
    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();

    $p3mUser = User::factory()->create(['name' => 'Pemohon P3M '.$suffix]);
    $tifUser = User::factory()->create(['name' => 'Pemohon TIF '.$suffix]);
    $ictUser = User::factory()->create(['name' => 'Pemohon ICT '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Finance '.$suffix]);

    $p3mUser->assignRole($pemohonRole);
    $tifUser->assignRole($pemohonRole);
    $ictUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);

    $p3mDept->users()->attach($p3mUser->id);
    $tifDept->users()->attach($tifUser->id);
    $ictDept->users()->attach($ictUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2028/2029',
        'period_range' => 'September 2028 – Agustus 2029',
        'phase_name' => 'Consistency Test '.$suffix,
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDays(20),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Consistency '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    $detailAccount = Account::query()->where('code', 'like', '5.2.%')->orderByRaw('LENGTH(code) DESC')->firstOrFail();

    $createDraftWithIndicator = function (Department $dept, User $head) use ($schedule, $setting, $account52): RkaHeader {
        $header = RkaHeader::create([
            'schedule_id' => $schedule->id,
            'department_id' => $dept->id,
            'setting_id' => $setting->id,
            'unit_head_user_id' => $head->id,
            'status' => RkaHeader::STATUS_DRAFT,
            'is_locked' => false,
            'pagu_budget' => 100000000.0,
        ]);

        RkaIndicator::create([
            'rka_header_id' => $header->id,
            'account_id' => $account52->id,
            'program_name' => 'Program '.$dept->code,
            'activity_name' => 'Kegiatan '.$dept->code,
            'location' => 'Politeknik Kampar',
            'indicator_matrix' => [],
        ]);

        return $header;
    };

    $p3mDraft = $createDraftWithIndicator($p3mDept, $p3mUser);
    $tifDraft = $createDraftWithIndicator($tifDept, $tifUser);
    $ictDraft = $createDraftWithIndicator($ictDept, $ictUser);

    return compact(
        'p3mDept',
        'tifDept',
        'ictDept',
        'p3mUser',
        'tifUser',
        'ictUser',
        'keuangan',
        'schedule',
        'setting',
        'detailAccount',
        'p3mDraft',
        'tifDraft',
        'ictDraft',
    );
}

/**
 * Helper membuat workbook native minimal dengan BELANJA, RINGKASAN, IDENTITAS, BERITA ACARA, dan DAFTAR PENYESUAIAN.
 *
 * @param  array<int, array<int, mixed>>  $sectionARows
 * @param  array<int, array<int, mixed>>  $sectionBRows
 */
function buildNativeWorkbookForScenario(
    string $unitName,
    string $accountCode,
    array $belanjaItems,
    array $sectionARows,
    array $sectionBRows,
    float $paguAwal = 50000000.0,
): string {
    $spreadsheet = new Spreadsheet;
    $identitas = $spreadsheet->getActiveSheet();
    $identitas->setTitle('IDENTITAS');
    $identitas->setCellValue('B3', 'Unit Kerja');
    $identitas->setCellValue('C3', $unitName);
    $identitas->setCellValue('B4', 'Tahun Anggaran');
    $identitas->setCellValue('C4', '2028/2029');
    $identitas->setCellValue('B5', 'Pagu Anggaran');
    $identitas->setCellValue('C5', $paguAwal);

    $ringkasan = $spreadsheet->createSheet();
    $ringkasan->setTitle('RINGKASAN');
    $ringkasan->setCellValue('B6', 'Usulan Awal Unit');
    $ringkasan->setCellValue('C6', $paguAwal);

    $belanja = $spreadsheet->createSheet();
    $belanja->setTitle('Belanja Barang dan Jasa');
    $belanja->setCellValue('A1', 'Program');
    $belanja->setCellValue('B1', 'Program '.$unitName);
    $belanja->setCellValue('A3', 'Kode Rekening');
    $belanja->setCellValue('B3', 'Uraian');
    $belanja->setCellValue('C3', 'Vol');
    $belanja->setCellValue('D3', 'Satuan');
    $belanja->setCellValue('E3', 'Harga Satuan');
    $belanja->setCellValue('F3', 'Jumlah Usulan');
    $belanja->setCellValue('G3', 'Vol Disetujui');
    $belanja->setCellValue('H3', 'Jumlah Disetujui');
    $belanja->setCellValue('I3', 'Status');
    $belanja->setCellValue('J3', 'Keterangan');

    $r = 4;
    foreach ($belanjaItems as $item) {
        $belanja->setCellValue("A{$r}", $item['code'] ?? $accountCode);
        $belanja->setCellValue("B{$r}", $item['desc']);
        $belanja->setCellValue("C{$r}", $item['vol']);
        $belanja->setCellValue("D{$r}", $item['unit']);
        $belanja->setCellValue("E{$r}", $item['price']);
        $belanja->setCellValue("F{$r}", $item['vol'] * $item['price']);
        $belanja->setCellValue("G{$r}", $item['approved_vol'] ?? $item['vol']);
        $belanja->setCellValue("H{$r}", $item['approved_total'] ?? ($item['vol'] * $item['price']));
        $belanja->setCellValue("I{$r}", $item['status'] ?? 'APPROVED');
        $belanja->setCellValue("J{$r}", $item['notes'] ?? '');
        $r++;
    }

    $adj = $spreadsheet->createSheet();
    $adj->setTitle('DAFTAR PENYESUAIAN');
    $row = 2;
    $adj->setCellValue("A{$row}", 'A. USULAN UNIT YANG DIPINDAH ATAU TIDAK DIANGGARKAN DI RKA UNIT INI');
    $row++;
    $adj->setCellValue("A{$row}", 'No');
    $adj->setCellValue("B{$row}", 'Kode Rekening');
    $adj->setCellValue("C{$row}", 'Uraian Usulan');
    $adj->setCellValue("D{$row}", 'Volume');
    $adj->setCellValue("E{$row}", 'Unit Tujuan');
    $adj->setCellValue("F{$row}", 'Jumlah Usulan (Rp)');
    $adj->setCellValue("G{$row}", 'Status');
    $adj->setCellValue("H{$row}", 'Keterangan');
    $row++;

    foreach ($sectionARows as $sRow) {
        $adj->setCellValue("A{$row}", $sRow[0]);
        $adj->setCellValue("B{$row}", $sRow[1]);
        $adj->setCellValue("C{$row}", $sRow[2]);
        $adj->setCellValue("D{$row}", $sRow[3]);
        $adj->setCellValue("E{$row}", $sRow[4]);
        $adj->setCellValue("F{$row}", $sRow[5]);
        $adj->setCellValue("G{$row}", $sRow[6]);
        $adj->setCellValue("H{$row}", $sRow[7]);
        $row++;
    }

    $row += 1;
    $adj->setCellValue("A{$row}", 'B. POS YANG DIKELOLA UNIT INI UNTUK UNIT LAIN / TAMBAHAN SAAT REVIEW');
    $row++;
    $adj->setCellValue("A{$row}", 'No');
    $adj->setCellValue("B{$row}", 'Kode Rekening');
    $adj->setCellValue("C{$row}", 'Uraian');
    $adj->setCellValue("D{$row}", 'Unit Asal');
    $adj->setCellValue("E{$row}", 'Volume');
    $adj->setCellValue("F{$row}", 'Jumlah Usulan (Rp)');
    $adj->setCellValue("G{$row}", 'Jumlah Disetujui (Rp)');
    $adj->setCellValue("H{$row}", 'Status');
    $adj->setCellValue("I{$row}", 'Keterangan');
    $row++;

    foreach ($sectionBRows as $bRow) {
        $adj->setCellValue("A{$row}", $bRow[0]);
        $adj->setCellValue("B{$row}", $bRow[1]);
        $adj->setCellValue("C{$row}", $bRow[2]);
        $adj->setCellValue("D{$row}", $bRow[3]);
        $adj->setCellValue("E{$row}", $bRow[4]);
        $adj->setCellValue("F{$row}", $bRow[5]);
        $adj->setCellValue("G{$row}", $bRow[6]);
        $adj->setCellValue("H{$row}", $bRow[7] ?? 'SHARED_COST');
        $adj->setCellValue("I{$row}", $bRow[8] ?? '');
        $row++;
    }

    $ba = $spreadsheet->createSheet();
    $ba->setTitle('BERITA ACARA');
    $ba->setCellValue('A1', 'BERITA ACARA REVIEW RKA');

    $rekap = $spreadsheet->createSheet();
    $rekap->setTitle('REKAP');
    $rekap->setCellValue('A1', 'REKAP');

    $path = sys_get_temp_dir().'/scenario_'.uniqid().'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

it('covers Scenarios 1, 2, 3, 4: Section A MOVED, PENDING, REJECTED preserve origin item ownership, sync status, set zero approved_amount, and never duplicate to destination', function () {
    $ctx = makeConsistencyContext();
    $service = app(ApprovedRkaImportService::class);
    $code = $ctx['detailAccount']->code;

    // Skenario 1, 3, 4 pada P3M: Akun Turnitin (MOVED ke UPT ICT), Item Ditunda (PENDING), Item Ditolak (REJECTED)
    $p3mIndicatorId = $ctx['p3mDraft']->indicators()->first()->id;
    $existingTurnitin = RkaItem::create([
        'rka_header_id' => $ctx['p3mDraft']->id,
        'rka_indicator_id' => $p3mIndicatorId,
        'account_id' => $ctx['detailAccount']->id,
        'form_type' => '5.2',
        'description' => 'Akun Turnitin',
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 15000000,
        'approved_volume' => 1,
        'approved_total' => 15000000,
        'status' => 'PENDING',
    ]);

    $p3mFile = buildNativeWorkbookForScenario(
        unitName: 'P3M',
        accountCode: $code,
        belanjaItems: [
            ['desc' => 'Seminar Nasional', 'vol' => 1, 'unit' => 'Kegiatan', 'price' => 10000000, 'status' => 'APPROVED'],
            ['desc' => 'Item Ditunda', 'vol' => 1, 'unit' => 'Unit', 'price' => 5000000, 'approved_vol' => 0, 'approved_total' => 0, 'status' => 'PENDING'],
            ['desc' => 'Item Ditolak', 'vol' => 2, 'unit' => 'Unit', 'price' => 2000000, 'approved_vol' => 0, 'approved_total' => 0, 'status' => 'REJECTED'],
        ],
        sectionARows: [
            [1, $code, 'Akun Turnitin', '1 Paket', 'UPT ICT', 15000000, 'Dipindahkan ke UPT ICT', 'Dikelola terpusat oleh ICT'],
            [2, $code, 'Item Ditunda', '1 Unit', '-', 5000000, 'Ditunda', 'Menunggu evaluasi'],
            [3, $code, 'Item Ditolak', '2 Unit', '-', 4000000, 'Ditolak', 'Tidak sesuai prioritas'],
        ],
        sectionBRows: [],
        paguAwal: 34000000.0,
    );

    try {
        $preview = $service->preview(
            filePath: $p3mFile,
            originalFilename: 'p3m_scenarios.xlsx',
            user: $ctx['p3mUser'],
            departmentId: $ctx['p3mDept']->id,
            scheduleId: $ctx['schedule']->id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $ctx['p3mDraft'],
        );

        expect($preview['has_fatal_errors'])->toBeFalse();
        $service->executeDraftImport($preview['token'], $ctx['p3mDraft'], $ctx['p3mUser']);

        // Skenario 1: Akun Turnitin tetap milik RKA P3M, status sinkron MOVED, tidak diduplikasi ke UPT ICT
        $freshTurnitin = $existingTurnitin->fresh();
        expect($freshTurnitin)->not->toBeNull()
            ->and($freshTurnitin->rka_header_id)->toBe($ctx['p3mDraft']->id)
            ->and($freshTurnitin->status)->toBe('MOVED')
            ->and((float) $freshTurnitin->approved_total)->toBe(0.0)
            ->and(RkaItem::where('rka_header_id', $ctx['ictDraft']->id)->count())->toBe(0);

        $adjMoved = RkaAdjustment::where('rka_header_id', $ctx['p3mDraft']->id)
            ->where('section_type', RkaAdjustment::SECTION_A)
            ->where('action_type', RkaAdjustment::ACTION_MOVED)
            ->firstOrFail();

        expect($adjMoved->rka_item_id)->toBe($existingTurnitin->id)
            ->and($adjMoved->rka_indicator_id)->toBe($p3mIndicatorId)
            ->and($adjMoved->destination_unit_id)->toBe($ctx['ictDept']->id)
            ->and($adjMoved->target_unit_id)->toBeNull()
            ->and((float) $adjMoved->approved_amount)->toBe(0.0);

        // Skenario 3: PENDING -> tidak ada unit tujuan palsu, approved_amount = 0, tidak ada budget baru
        $adjPending = RkaAdjustment::where('rka_header_id', $ctx['p3mDraft']->id)
            ->where('action_type', RkaAdjustment::ACTION_PENDING)
            ->firstOrFail();
        expect($adjPending->destination_unit_id)->toBeNull()
            ->and($adjPending->target_unit_id)->toBeNull()
            ->and((float) $adjPending->approved_amount)->toBe(0.0)
            ->and($adjPending->rka_item_id)->not->toBeNull();

        // Skenario 4: REJECTED -> tidak ada unit tujuan palsu, approved_amount = 0
        $adjRejected = RkaAdjustment::where('rka_header_id', $ctx['p3mDraft']->id)
            ->where('action_type', RkaAdjustment::ACTION_REJECTED)
            ->firstOrFail();
        expect($adjRejected->destination_unit_id)->toBeNull()
            ->and($adjRejected->target_unit_id)->toBeNull()
            ->and((float) $adjRejected->approved_amount)->toBe(0.0)
            ->and($adjRejected->rka_item_id)->not->toBeNull();

        // DepartmentBudget P3M hanya menghitung item APPROVED (Seminar Nasional = 10.000.000), bukan PENDING/REJECTED/MOVED
        $p3mAllocated = (float) DepartmentBudget::where('department_id', $ctx['p3mDept']->id)
            ->where('fiscal_year', $ctx['schedule']->fiscal_year)
            ->sum('allocated_amount');
        expect($p3mAllocated)->toBe(10000000.0);
    } finally {
        @unlink($p3mFile);
    }

    // Skenario 2: Prodi TIF -> UPT ICT
    $tifFile = buildNativeWorkbookForScenario(
        unitName: 'Prodi D3 Teknik Informatika (TIF)',
        accountCode: $code,
        belanjaItems: [
            ['desc' => 'Modul Praktikum', 'vol' => 1, 'unit' => 'Paket', 'price' => 8000000, 'status' => 'APPROVED'],
        ],
        sectionARows: [
            [1, $code, 'License Adobe', '1 Paket', 'UPT ICT', 12000000, 'Dipindahkan ke UPT ICT', 'Pengadaan terpusat'],
        ],
        sectionBRows: [],
        paguAwal: 20000000.0,
    );

    try {
        $previewTif = $service->preview(
            filePath: $tifFile,
            originalFilename: 'tif_scenario.xlsx',
            user: $ctx['tifUser'],
            departmentId: $ctx['tifDept']->id,
            scheduleId: $ctx['schedule']->id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $ctx['tifDraft'],
        );

        $service->executeDraftImport($previewTif['token'], $ctx['tifDraft'], $ctx['tifUser']);

        $tifAdj = RkaAdjustment::where('rka_header_id', $ctx['tifDraft']->id)->firstOrFail();
        $tifMovedItem = RkaItem::where('rka_header_id', $ctx['tifDraft']->id)
            ->where('description', 'License Adobe')
            ->firstOrFail();

        // Item Section A (License Adobe) dibuat di rka_items milik Prodi TIF, terhubung ke adjustment, dan tampil di Sheet RKA/Belanja
        expect($tifMovedItem->status)->toBe('MOVED')
            ->and((float) $tifMovedItem->proposed_total)->toBe(12000000.0)
            ->and((float) $tifMovedItem->approved_total)->toBe(0.0)
            ->and($tifAdj->rka_item_id)->toBe($tifMovedItem->id)
            ->and($tifAdj->destination_unit_id)->toBe($ctx['ictDept']->id)
            ->and($tifAdj->rka_header_id)->toBe($ctx['tifDraft']->id)
            ->and(RkaItem::where('rka_header_id', $ctx['tifDraft']->id)->count())->toBe(2)
            ->and(RkaItem::where('rka_header_id', $ctx['ictDraft']->id)->count())->toBe(0);

        $this->actingAs($ctx['tifUser']);
        Livewire::test(ReviewRkaPage::class, ['record' => $ctx['tifDraft']->id])
            ->set('activeTab', 'sheet_rka')
            ->call('toggleShowAllItems', true)
            ->assertOk()
            ->assertSee('License Adobe')
            ->assertSee('Modul Praktikum')
            ->set('activeTab', 'penyesuaian')
            ->assertOk()
            ->assertSee('License Adobe')
            ->assertSee($ctx['ictDept']->name);
    } finally {
        @unlink($tifFile);
    }
});

it('covers Scenario 5 & 8: Section B links existing UPT ICT item without duplication, sets target_unit_id to P3M, and renders accurately on ReviewRkaPage', function () {
    $ctx = makeConsistencyContext();
    $service = app(ApprovedRkaImportService::class);
    $code = $ctx['detailAccount']->code;

    // Skenario 5: UPT ICT mengelola item Akun Turnitin (dari P3M) di BELANJA + Section B
    $ictFile = buildNativeWorkbookForScenario(
        unitName: 'UPT ICT',
        accountCode: $code,
        belanjaItems: [
            ['desc' => 'Akun Turnitin', 'vol' => 1, 'unit' => 'Paket', 'price' => 15000000, 'status' => 'APPROVED'],
        ],
        sectionARows: [],
        sectionBRows: [
            [1, $code, 'Akun Turnitin', 'P3M', '1 Paket', 15000000, 15000000, 'Dipindahkan', 'Pindahan dari P3M'],
        ],
        paguAwal: 15000000.0,
    );

    try {
        $preview = $service->preview(
            filePath: $ictFile,
            originalFilename: 'ict_section_b.xlsx',
            user: $ctx['ictUser'],
            departmentId: $ctx['ictDept']->id,
            scheduleId: $ctx['schedule']->id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $ctx['ictDraft'],
        );

        expect($preview['has_fatal_errors'])->toBeFalse();
        $service->executeDraftImport($preview['token'], $ctx['ictDraft'], $ctx['ictUser']);

        // Hanya 1 item belanja di RKA UPT ICT (tidak ada duplikasi akibat Section B)
        $ictItems = RkaItem::where('rka_header_id', $ctx['ictDraft']->id)->get();
        expect($ictItems)->toHaveCount(1);

        $adjB = RkaAdjustment::where('rka_header_id', $ctx['ictDraft']->id)
            ->where('section_type', RkaAdjustment::SECTION_B)
            ->firstOrFail();

        expect($adjB->rka_item_id)->toBe($ictItems->first()->id)
            ->and($adjB->imported_rka_item_id)->toBe($ictItems->first()->id)
            ->and($adjB->target_unit_id)->toBe($ctx['p3mDept']->id)
            ->and($adjB->destination_unit_id)->toBeNull()
            ->and($adjB->action_type)->toBe(RkaAdjustment::ACTION_MOVED)
            ->and((float) $adjB->approved_amount)->toBe(15000000.0);

        // Skenario 8: ReviewRkaPage menampilkan nama unit sumber dari relasi targetUnit
        $this->actingAs($ctx['ictUser']);
        Livewire::test(ReviewRkaPage::class, ['record' => $ctx['ictDraft']->id])
            ->set('activeTab', 'penyesuaian')
            ->assertOk()
            ->assertSee('Akun Turnitin')
            ->assertSee($ctx['p3mDept']->name);
    } finally {
        @unlink($ictFile);
    }
});

it('covers Scenario 6 & 7: unresolved source unit never creates fake FK and repeated imports are idempotent without duplicate adjustments or logs', function () {
    $ctx = makeConsistencyContext();
    $service = app(ApprovedRkaImportService::class);
    $code = $ctx['detailAccount']->code;

    // Skenario 6: Unit sumber tidak dikenal pada Section B -> tidak buat FK palsu, warning tampil, eksekusi diblokir
    $badUnitFile = buildNativeWorkbookForScenario(
        unitName: 'UPT ICT',
        accountCode: $code,
        belanjaItems: [
            ['desc' => 'Akun Zoom', 'vol' => 1, 'unit' => 'Paket', 'price' => 5000000, 'status' => 'APPROVED'],
        ],
        sectionARows: [],
        sectionBRows: [
            [1, $code, 'Akun Zoom', 'UNIT FIKTIF XYZ', '1 Paket', 5000000, 5000000, 'SHARED_COST', 'Unit tidak dikenal'],
        ],
        paguAwal: 5000000.0,
    );

    try {
        $previewBad = $service->preview(
            filePath: $badUnitFile,
            originalFilename: 'bad_unit.xlsx',
            user: $ctx['ictUser'],
            departmentId: $ctx['ictDept']->id,
            scheduleId: $ctx['schedule']->id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $ctx['ictDraft'],
        );

        expect($previewBad['adjustment_rows'][0]['target_department_id'])->toBeNull()
            ->and($previewBad['adjustment_rows'][0]['relation_status'])->toBe('UNIT TIDAK VALID')
            ->and($previewBad['warnings'])->not->toBeEmpty()
            ->and(fn () => $service->executeDraftImport($previewBad['token'], $ctx['ictDraft'], $ctx['ictUser']))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        @unlink($badUnitFile);
    }

    // Skenario 7: Import berulang dengan file yang sama -> idempoten, tidak ada adjustment/item/log duplikat
    $validFile = buildNativeWorkbookForScenario(
        unitName: 'UPT ICT',
        accountCode: $code,
        belanjaItems: [
            ['desc' => 'Akun Zoom', 'vol' => 1, 'unit' => 'Paket', 'price' => 5000000, 'status' => 'APPROVED'],
        ],
        sectionARows: [],
        sectionBRows: [
            [1, $code, 'Akun Zoom', 'P3M', '1 Paket', 5000000, 5000000, 'SHARED_COST', ''],
        ],
        paguAwal: 5000000.0,
    );

    try {
        $preview1 = $service->preview(
            filePath: $validFile,
            originalFilename: 'valid_repeat.xlsx',
            user: $ctx['ictUser'],
            departmentId: $ctx['ictDept']->id,
            scheduleId: $ctx['schedule']->id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $ctx['ictDraft'],
        );

        $service->executeDraftImport($preview1['token'], $ctx['ictDraft'], $ctx['ictUser']);

        // Panggil ulang persistAdjustmentsForDraft secara langsung (simulasi retry) -> skipped, tidak ada duplikat
        $refMethod = new ReflectionMethod($service, 'persistAdjustmentsForDraft');
        $refMethod->setAccessible(true);
        $repeatResult = $refMethod->invoke($service, $ctx['ictDraft']->fresh(), $preview1['adjustment_rows'], [], $ctx['ictUser']);

        expect($repeatResult['createdCount'])->toBe(0)
            ->and($repeatResult['skippedCount'])->toBe(1)
            ->and(RkaAdjustment::where('rka_header_id', $ctx['ictDraft']->id)->count())->toBe(1)
            ->and(RkaItem::where('rka_header_id', $ctx['ictDraft']->id)->count())->toBe(1)
            ->and(RkaLog::where('rka_header_id', $ctx['ictDraft']->id)->where('action', RkaLog::ACTION_APPROVED_IMPORT_EXECUTED)->count())->toBe(1);
    } finally {
        @unlink($validFile);
    }
});

it('does not randomly link or duplicate when Section A has ambiguous candidates in DB', function () {
    $ctx = makeConsistencyContext();
    $service = app(ApprovedRkaImportService::class);
    $code = $ctx['detailAccount']->code;
    $p3mIndicatorId = $ctx['p3mDraft']->indicators()->first()->id;

    // Buat 2 item identik di RKA Draft P3M sehingga pencocokan konten menjadi ambigu
    for ($i = 0; $i < 2; $i++) {
        RkaItem::create([
            'rka_header_id' => $ctx['p3mDraft']->id,
            'rka_indicator_id' => $p3mIndicatorId,
            'account_id' => $ctx['detailAccount']->id,
            'form_type' => '5.2',
            'description' => 'Item Ambigu',
            'proposed_volume' => 1,
            'unit_symbol' => 'Unit',
            'unit_price' => 1000000,
            'approved_volume' => 1,
            'approved_total' => 1000000,
            'status' => 'PENDING',
        ]);
    }

    $ambigFile = buildNativeWorkbookForScenario(
        unitName: 'P3M',
        accountCode: $code,
        belanjaItems: [
            ['desc' => 'Item Normal', 'vol' => 1, 'unit' => 'Unit', 'price' => 2000000, 'status' => 'APPROVED'],
        ],
        sectionARows: [
            [1, $code, 'Item Ambigu', '1 Unit', 'UPT ICT', 1000000, 'Dipindahkan ke UPT ICT', ''],
        ],
        sectionBRows: [],
        paguAwal: 3000000.0,
    );

    try {
        $preview = $service->preview(
            filePath: $ambigFile,
            originalFilename: 'ambig_section_a.xlsx',
            user: $ctx['p3mUser'],
            departmentId: $ctx['p3mDept']->id,
            scheduleId: $ctx['schedule']->id,
            mode: RkaApprovedImportBatch::TYPE_DRAFT,
            targetHeader: $ctx['p3mDraft'],
        );

        $service->executeDraftImport($preview['token'], $ctx['p3mDraft'], $ctx['p3mUser']);

        // 2 item ambigu + 1 Item Normal = 3 (tidak membuat item ke-4 dan tidak menebak rka_item_id secara acak)
        expect(RkaItem::where('rka_header_id', $ctx['p3mDraft']->id)->count())->toBe(3);

        $adj = RkaAdjustment::where('rka_header_id', $ctx['p3mDraft']->id)->firstOrFail();
        expect($adj->rka_item_id)->toBeNull()
            ->and($adj->destination_unit_id)->toBe($ctx['ictDept']->id);
    } finally {
        @unlink($ambigFile);
    }
});
