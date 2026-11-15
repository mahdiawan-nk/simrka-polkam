<?php

use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeAdaptiveImportContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);

    $p3mDept = Department::where('code', 'P3M')->firstOrFail();
    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $tifDept = Department::where('code', 'PRODI-TIF')->firstOrFail();
    $umumDept = Department::where('code', 'BAG-UMUM')->firstOrFail();
    $bakkuDept = Department::where('code', 'BAKKU')->firstOrFail();

    $p3mUser = User::factory()->create(['name' => 'Adaptive P3M '.$suffix]);
    $ictUser = User::factory()->create(['name' => 'Adaptive ICT '.$suffix]);
    $tifUser = User::factory()->create(['name' => 'Adaptive TIF '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Adaptive Keu '.$suffix]);

    $p3mUser->assignRole($pemohonRole);
    $ictUser->assignRole($pemohonRole);
    $tifUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);

    $p3mDept->users()->attach($p3mUser->id);
    $ictDept->users()->attach($ictUser->id);
    $tifDept->users()->attach($tifUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'September 2026 – Agustus 2027',
        'phase_name' => 'Adaptive '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $p3mUser->id,
    ]);

    RkaSetting::create([
        'setting_name' => 'Setting Adaptive '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    return compact(
        'p3mDept',
        'ictDept',
        'tifDept',
        'umumDept',
        'bakkuDept',
        'p3mUser',
        'ictUser',
        'tifUser',
        'keuangan',
        'schedule',
    );
}

it('resolves Section A Unit Tujuan automatically from Excel and allows user override before import', function () {
    $ctx = makeAdaptiveImportContext();
    $service = app(ApprovedRkaImportService::class);

    // 10_RKA_2026-2027_P3M.xlsx memiliki kolom Unit Tujuan (E) dengan nilai UMUM & KEUANGAN
    $preview = $service->preview(
        filePath: base_path('data-rka/10_RKA_2026-2027_P3M.xlsx'),
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['p3mDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['has_fatal_errors'])->toBeFalse()
        ->and($preview['summary']['complete_unit_count'])->toBe(23)
        ->and($preview['summary']['incomplete_unit_count'])->toBe(0);

    $firstRow = $preview['adjustment_rows'][0];
    expect($firstRow['unit_source'])->toBe('EXCEL')
        ->and($firstRow['unit_tujuan_excel'])->toBe('UMUM')
        ->and($firstRow['destination_department_id'])->toBe($ctx['umumDept']->id)
        ->and($firstRow['target_department_id'])->toBeNull()
        ->and((float) $firstRow['jumlah_usulan'])->toBe(330000.0);

    // User edits the first row's Unit Tujuan to UPT-ICT
    $updatedPreview = $service->assignUnitOnPreview(
        previewToken: $preview['token'],
        adjRefId: $firstRow['adj_ref_id'],
        departmentId: $ctx['ictDept']->id,
        user: $ctx['p3mUser'],
    );

    expect($updatedPreview['adjustment_rows'][0]['unit_source'])->toBe('MANUAL')
        ->and($updatedPreview['adjustment_rows'][0]['unit_tujuan_excel'])->toBe('UMUM')
        ->and($updatedPreview['adjustment_rows'][0]['destination_department_id'])->toBe($ctx['ictDept']->id);

    $batch = $service->executeImport($updatedPreview['token'], $ctx['p3mUser']);
    $header = RkaHeader::findOrFail($batch->rka_header_id);

    $firstSaved = RkaAdjustment::where('rka_header_id', $header->id)
        ->where('section_type', RkaAdjustment::SECTION_A)
        ->orderBy('id')
        ->firstOrFail();

    expect($firstSaved->destination_unit_id)->toBe($ctx['ictDept']->id)
        ->and($firstSaved->target_unit_id)->toBeNull();
});

it('handles Section A without Unit Tujuan column and leaves non-MOVED statuses without forcing unit', function () {
    $ctx = makeAdaptiveImportContext();
    $service = app(ApprovedRkaImportService::class);

    // 19_RKA_2026-2027_Prodi_TIF.xlsx tidak memiliki kolom Unit Tujuan: 35 MOVED + 2 BUDGETED_ELSEWHERE
    $preview = $service->preview(
        filePath: base_path('data-rka/19_RKA_2026-2027_Prodi_TIF.xlsx'),
        originalFilename: '19_RKA_2026-2027_Prodi_TIF.xlsx',
        user: $ctx['tifUser'],
        departmentId: $ctx['tifDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['summary']['incomplete_unit_count'])->toBe(35)
        ->and($preview['summary']['not_required_unit_count'])->toBe(2);

    $nonMoved = collect($preview['adjustment_rows'])
        ->filter(fn (array $row): bool => ($row['action_type'] ?? '') !== RkaAdjustment::ACTION_MOVED);

    foreach ($nonMoved as $row) {
        expect($row['relation_status'])->toBe('TIDAK MEMERLUKAN UNIT')
            ->and($row['destination_department_id'])->toBeNull();
    }
});

it('supports Section B without Unit Asal column, reordered columns, and manual override without swapping Section A/B fields', function () {
    $ctx = makeAdaptiveImportContext();
    $service = app(ApprovedRkaImportService::class);

    $spreadsheet = IOFactory::load(base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'));
    $sheet = $spreadsheet->getSheetByName('DAFTAR PENYESUAIAN');
    expect($sheet)->not->toBeNull();

    // Ubah header Section B (row 9): ganti 'Unit Asal' -> 'Unit Asal/Sumber' dan tukar posisi dengan 'Jumlah Disetujui'
    // Awal: A:No | B:Kode | C:Uraian | D:Unit Asal | E:Jumlah Usulan (Rp) | F:Jumlah Disetujui (Rp) | G:Keterangan
    // Baru: A:No | B:Kode | C:Uraian | D:Jumlah Disetujui (Rp) | E:Jumlah Usulan (Rp) | F:Unit Asal/Sumber | G:Keterangan
    $sheet->setCellValue('D9', 'Jumlah Disetujui (Rp)');
    $sheet->setCellValue('F9', 'Unit Asal/Sumber');

    for ($r = 10; $r <= 19; $r++) {
        $oldD = $sheet->getCell("D{$r}")->getValue();
        $oldF = $sheet->getCell("F{$r}")->getValue();
        $sheet->setCellValue("D{$r}", $oldF);
        // Kosongkan baris 10 agar menguji input manual Section B saat Unit Asal kosong
        $sheet->setCellValue("F{$r}", $r === 10 ? '' : $oldD);
    }

    $tempPath = storage_path('app/temp-adaptive-reordered-b.xlsx');
    (new Xlsx($spreadsheet))->save($tempPath);

    try {
        $preview = $service->preview(
            filePath: $tempPath,
            originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
            user: $ctx['ictUser'],
            departmentId: $ctx['ictDept']->id,
            scheduleId: $ctx['schedule']->id,
        );

        expect($preview['has_fatal_errors'])->toBeFalse()
            ->and($preview['summary']['incomplete_unit_count'])->toBe(1)
            ->and($preview['summary']['complete_unit_count'])->toBe(9);

        $firstRow = $preview['adjustment_rows'][0];
        expect($firstRow['relation_status'])->toBe('BELUM DITENTUKAN')
            ->and($firstRow['target_department_id'])->toBeNull()
            ->and($firstRow['destination_department_id'])->toBeNull();

        $updatedPreview = $service->assignUnitOnPreview(
            previewToken: $preview['token'],
            adjRefId: $firstRow['adj_ref_id'],
            departmentId: $ctx['p3mDept']->id,
            user: $ctx['ictUser'],
        );

        expect($updatedPreview['adjustment_rows'][0]['unit_source'])->toBe('MANUAL')
            ->and($updatedPreview['adjustment_rows'][0]['target_department_id'])->toBe($ctx['p3mDept']->id)
            ->and($updatedPreview['adjustment_rows'][0]['destination_department_id'])->toBeNull();

        $batch = $service->executeImport($updatedPreview['token'], $ctx['ictUser']);
        $savedSectionB = RkaAdjustment::where('rka_header_id', $batch->rka_header_id)
            ->where('section_type', RkaAdjustment::SECTION_B)
            ->get();

        expect($savedSectionB)->toHaveCount(10);
        foreach ($savedSectionB as $adj) {
            expect($adj->target_unit_id)->not->toBeNull()
                ->and($adj->destination_unit_id)->toBeNull();
        }
    } finally {
        @unlink($tempPath);
    }
});

it('detects ambiguous duplicate headers on DAFTAR PENYESUAIAN and blocks import', function () {
    $ctx = makeAdaptiveImportContext();
    $service = app(ApprovedRkaImportService::class);

    $spreadsheet = IOFactory::load(base_path('data-rka/10_RKA_2026-2027_P3M.xlsx'));
    $sheet = $spreadsheet->getSheetByName('DAFTAR PENYESUAIAN');
    // Duplikat header 'Unit Tujuan' pada kolom I
    $sheet->setCellValue('I5', 'Unit Tujuan');

    $tempPath = storage_path('app/temp-adaptive-ambiguous-header.xlsx');
    (new Xlsx($spreadsheet))->save($tempPath);

    try {
        $preview = $service->preview(
            filePath: $tempPath,
            originalFilename: '10_RKA_2026-2027_P3M.xlsx',
            user: $ctx['p3mUser'],
            departmentId: $ctx['p3mDept']->id,
            scheduleId: $ctx['schedule']->id,
        );

        expect($preview['has_fatal_errors'])->toBeTrue()
            ->and(fn () => $service->executeImport($preview['token'], $ctx['p3mUser']))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        @unlink($tempPath);
    }
});
