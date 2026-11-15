<?php

use App\Filament\Resources\RkaHeaders\Pages\ImportApprovedRkaPage;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeManualUnitAssignmentContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);

    $p3mDept = Department::where('code', 'P3M')->firstOrFail();
    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $tifDept = Department::where('code', 'PRODI-TIF')->firstOrFail();
    $umumDept = Department::where('code', 'BAG-UMUM')->firstOrFail();

    $p3mUser = User::factory()->create(['name' => 'Pemohon P3M '.$suffix]);
    $ictUser = User::factory()->create(['name' => 'Pemohon ICT '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Keuangan '.$suffix]);

    $p3mUser->assignRole($pemohonRole);
    $ictUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);

    $p3mDept->users()->attach($p3mUser->id);
    $ictDept->users()->attach($ictUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'September 2026 – Agustus 2027',
        'phase_name' => 'Manual Unit '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $p3mUser->id,
    ]);

    RkaSetting::create([
        'setting_name' => 'Setting Manual Unit '.$suffix,
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
        'p3mUser',
        'ictUser',
        'keuangan',
        'schedule',
    );
}

it('blocks finalization when section a moved lacks destination unit and succeeds once assigned manually', function () {
    $ctx = makeManualUnitAssignmentContext();
    $ctx['tifDept']->users()->attach($ctx['p3mUser']->id);
    $service = app(ApprovedRkaImportService::class);
    $tifFile = base_path('data-rka/19_RKA_2026-2027_Prodi_TIF.xlsx');

    $preview = $service->preview(
        filePath: $tifFile,
        originalFilename: '19_RKA_2026-2027_Prodi_TIF.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['tifDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    expect($preview['summary']['incomplete_unit_count'])->toBe(35)
        ->and($preview['summary']['complete_unit_count'])->toBe(0)
        ->and($preview['summary']['not_required_unit_count'])->toBe(2);

    // Finalize must be blocked while MOVED rows have no destination_department_id
    expect(fn () => $service->executeImport($preview['token'], $ctx['p3mUser']))
        ->toThrow(InvalidArgumentException::class, 'belum memiliki unit tujuan/asal');

    // Assign destination units manually for all Section A MOVED rows
    $budgetCountBefore = DepartmentBudget::count();
    $otherHeaderCountBefore = RkaHeader::where('department_id', '!=', $ctx['tifDept']->id)->count();

    foreach ($preview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED') {
            $preview = $service->assignUnitOnPreview(
                previewToken: $preview['token'],
                adjRefId: $row['adj_ref_id'],
                departmentId: $ctx['umumDept']->id,
                user: $ctx['p3mUser'],
            );
        }
    }

    expect($preview['summary']['incomplete_unit_count'])->toBe(0)
        ->and($preview['summary']['complete_unit_count'])->toBe(35);

    $logCount = RkaLog::where('action', RkaLog::ACTION_APPROVED_IMPORT_UNIT_ASSIGNED)
        ->where('user_id', $ctx['p3mUser']->id)
        ->count();
    expect($logCount)->toBe(35);

    $batch = $service->executeImport($preview['token'], $ctx['p3mUser']);
    $header = RkaHeader::findOrFail($batch->rka_header_id);

    $movedAdjustments = RkaAdjustment::where('rka_header_id', $header->id)
        ->where('section_type', RkaAdjustment::SECTION_A)
        ->where('action_type', RkaAdjustment::ACTION_MOVED)
        ->get();

    expect($movedAdjustments)->toHaveCount(35);
    foreach ($movedAdjustments as $adj) {
        expect($adj->destination_unit_id)->toBe($ctx['umumDept']->id)
            ->and($adj->target_unit_id)->toBeNull();
    }

    // Verify no side effects on DepartmentBudget or other units' RKAs
    expect(DepartmentBudget::count())->toBe($budgetCountBefore)
        ->and(RkaHeader::where('department_id', '!=', $ctx['tifDept']->id)->count())->toBe($otherHeaderCountBefore);
});

it('allows manual assignment for section b with invalid or empty source unit and saves target_unit_id', function () {
    $ctx = makeManualUnitAssignmentContext();
    $service = app(ApprovedRkaImportService::class);
    $sourceFile = base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx');

    $spreadsheet = IOFactory::load($sourceFile);
    $adjSheet = $spreadsheet->getSheetByName('DAFTAR PENYESUAIAN');
    expect($adjSheet)->not->toBeNull();

    // Corrupt a Section B source unit cell so it becomes UNIT TIDAK VALID (warning, not fatal)
    $modifiedRow = null;
    for ($r = 1; $r <= $adjSheet->getHighestRow(); $r++) {
        $colA = trim((string) $adjSheet->getCell("A{$r}")->getValue());
        $colD = trim((string) $adjSheet->getCell("D{$r}")->getValue());
        if (is_numeric($colA) && $colD !== '') {
            $adjSheet->setCellValue("D{$r}", 'UNIT UNKNOWN XYZ');
            $modifiedRow = $r;
            break;
        }
    }
    expect($modifiedRow)->not->toBeNull();

    $tempPath = storage_path('app/temp-manual-unit-b-test.xlsx');
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
            ->and($preview['summary']['incomplete_unit_count'])->toBeGreaterThanOrEqual(1);

        $invalidRow = collect($preview['adjustment_rows'])
            ->first(fn (array $row): bool => ($row['relation_status'] ?? '') === 'UNIT TIDAK VALID');
        expect($invalidRow)->not->toBeNull();

        $updatedPreview = $service->assignUnitOnPreview(
            previewToken: $preview['token'],
            adjRefId: $invalidRow['adj_ref_id'],
            departmentId: $ctx['tifDept']->id,
            user: $ctx['ictUser'],
        );

        expect($updatedPreview['summary']['incomplete_unit_count'])->toBe(0);

        $batch = $service->executeImport($updatedPreview['token'], $ctx['ictUser']);
        $savedAdj = RkaAdjustment::where('rka_header_id', $batch->rka_header_id)
            ->where('section_type', RkaAdjustment::SECTION_B)
            ->where('target_unit_id', $ctx['tifDept']->id)
            ->first();

        expect($savedAdj)->not->toBeNull()
            ->and($savedAdj->destination_unit_id)->toBeNull();
    } finally {
        @unlink($tempPath);
    }
});

it('rejects self-department, non-existent department, non-moved section a, and unauthorized user', function () {
    $ctx = makeManualUnitAssignmentContext();
    $service = app(ApprovedRkaImportService::class);
    $p3mFile = base_path('data-rka/10_RKA_2026-2027_P3M.xlsx');

    $preview = $service->preview(
        filePath: $p3mFile,
        originalFilename: '10_RKA_2026-2027_P3M.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['p3mDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    $movedRow = collect($preview['adjustment_rows'])
        ->first(fn (array $row): bool => ($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED');
    expect($movedRow)->not->toBeNull();

    // Self-department rejected
    expect(fn () => $service->assignUnitOnPreview(
        $preview['token'],
        $movedRow['adj_ref_id'],
        $ctx['p3mDept']->id,
        $ctx['p3mUser'],
    ))->toThrow(InvalidArgumentException::class, 'tidak boleh sama');

    // Non-existent department rejected
    expect(fn () => $service->assignUnitOnPreview(
        $preview['token'],
        $movedRow['adj_ref_id'],
        9999999,
        $ctx['p3mUser'],
    ))->toThrow(InvalidArgumentException::class, 'tidak ditemukan');

    // Unauthorized user rejected
    expect(fn () => $service->assignUnitOnPreview(
        $preview['token'],
        $movedRow['adj_ref_id'],
        $ctx['ictDept']->id,
        $ctx['ictUser'],
    ))->toThrow(AuthorizationException::class);

    // Non-MOVED Section A rejected (using synthetic row in cached preview)
    $cached = Cache::get("approved_rka_preview:{$preview['token']}");
    $cached['adjustment_rows'][] = [
        'adj_ref_id' => 'NATIVE:ADJ:A:999',
        'section' => 'A',
        'action_type' => RkaAdjustment::ACTION_REJECTED,
        'uraian' => 'Rejected Item',
    ];
    Cache::put("approved_rka_preview:{$preview['token']}", $cached, now()->addMinutes(10));

    expect(fn () => $service->assignUnitOnPreview(
        $preview['token'],
        'NATIVE:ADJ:A:999',
        $ctx['ictDept']->id,
        $ctx['p3mUser'],
    ))->toThrow(InvalidArgumentException::class, 'tidak memerlukan penentuan unit tujuan');
});

it('can assign unit through Filament assignUnit modal action on ImportApprovedRkaPage', function () {
    $ctx = makeManualUnitAssignmentContext();
    $ctx['tifDept']->users()->attach($ctx['p3mUser']->id);
    $service = app(ApprovedRkaImportService::class);
    $tifFile = base_path('data-rka/19_RKA_2026-2027_Prodi_TIF.xlsx');

    $preview = $service->preview(
        filePath: $tifFile,
        originalFilename: '19_RKA_2026-2027_Prodi_TIF.xlsx',
        user: $ctx['p3mUser'],
        departmentId: $ctx['tifDept']->id,
        scheduleId: $ctx['schedule']->id,
    );

    $movedRow = collect($preview['adjustment_rows'])
        ->first(fn (array $row): bool => ($row['section'] ?? '') === 'A' && ($row['action_type'] ?? '') === 'MOVED');

    $this->actingAs($ctx['p3mUser']);

    Livewire::test(ImportApprovedRkaPage::class)
        ->set('previewToken', $preview['token'])
        ->set('previewResult', $preview)
        ->set('activePreviewTab', 'adjustments')
        ->callAction('assignUnit', data: [
            'department_id' => $ctx['umumDept']->id,
        ], arguments: [
            'adj_ref_id' => $movedRow['adj_ref_id'],
        ])
        ->assertHasNoActionErrors()
        ->assertSet('previewResult.summary.complete_unit_count', 1)
        ->assertSet('previewResult.summary.incomplete_unit_count', 34);
});
