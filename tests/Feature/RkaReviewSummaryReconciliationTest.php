<?php

use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaApprovedImportBatch;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use App\Services\Rka\RkaReviewSummaryService;
use App\Services\RkaReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeSummaryReconciliationContext(string $deptCode, string $fiscalYear): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $viewPerm = Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);
    $pemohonRole->givePermissionTo($viewPerm);
    $keuanganRole->givePermissionTo($viewPerm);

    $dept = Department::where('code', $deptCode)->firstOrFail();
    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();

    $pemohon = User::factory()->create(['name' => 'Summary Pemohon '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Summary Finance '.$suffix]);
    $pemohon->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);
    $dept->users()->attach($pemohon->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => $fiscalYear,
        'period_range' => 'September – Agustus',
        'phase_name' => 'Summary Test '.$suffix,
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDays(20),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Summary '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    $header = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $dept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $pemohon->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'is_locked' => false,
        'pagu_budget' => 100000000.0,
    ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $account52->id,
        'program_name' => 'Program '.$dept->code,
        'activity_name' => 'Kegiatan '.$dept->code,
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    return compact('dept', 'ictDept', 'pemohon', 'keuangan', 'schedule', 'setting', 'header');
}

it('reconciles all 8 summary positions of Prodi TIF exactly with Excel RINGKASAN formulas and ReviewRkaPage UI', function () {
    $ctx = makeSummaryReconciliationContext('PRODI-TIF', '2031/2032');
    $importService = app(ApprovedRkaImportService::class);

    $preview = $importService->preview(
        filePath: base_path('data-rka/19_RKA_2026-2027_Prodi_TIF.xlsx'),
        originalFilename: '19_RKA_2026-2027_Prodi_TIF.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['header'],
    );

    // Lengkapi unit tujuan manual untuk baris Section A MOVED yang belum memiliki kolom Unit Tujuan
    foreach ($preview['adjustment_rows'] as $row) {
        if (($row['section'] ?? '') === RkaAdjustment::SECTION_A && ($row['action_type'] ?? '') === RkaAdjustment::ACTION_MOVED && empty($row['destination_department_id'])) {
            $importService->assignAdjustmentUnit($preview['token'], $row['adj_ref_id'], $ctx['ictDept']->id, $ctx['pemohon']);
        }
    }

    $importService->executeDraftImport($preview['token'], $ctx['header'], $ctx['pemohon']);
    $freshHeader = $ctx['header']->fresh();

    $summary = app(RkaReviewSummaryService::class)->calculate($freshHeader);
    $viaReviewService = app(RkaReviewService::class)->calculateSummaryData($freshHeader);

    // Verifikasi tepat 100% sesuai Excel 19_RKA_2026-2027_Prodi_TIF.xlsx worksheet RINGKASAN
    expect($summary)->toBe($viaReviewService)
        ->and((float) $summary['usulan_awal'])->toBe(728338000.0)
        ->and((float) $summary['dipindahkan'])->toBe(-31084000.0)
        ->and((float) $summary['pos_cost'])->toBe(-106500000.0)
        ->and((float) $summary['tambahan_pusat'])->toBe(0.0)
        ->and((float) $summary['adjustment_price'])->toBe(-55500000.0)
        ->and((float) $summary['usulan_dibahas'])->toBe(535254000.0)
        ->and((float) $summary['disetujui_setelah_review'])->toBe(346464000.0)
        ->and((float) $summary['percentage'])->toBe(64.7);

    // Akumulasi posisi 1..5 == posisi 6
    $accumulated = $summary['usulan_awal'] + $summary['dipindahkan'] + $summary['pos_cost'] + $summary['tambahan_pusat'] + $summary['adjustment_price'];
    expect($accumulated)->toBe($summary['usulan_dibahas']);

    // Verifikasi tampilan UI ReviewRkaPage tab ringkasan menampilkan format positif (tanpa tanda minus)
    $this->actingAs($ctx['pemohon']);
    Livewire::test(ReviewRkaPage::class, ['record' => $freshHeader->id])
        ->assertOk()
        ->assertSee('728.338.000')
        ->assertSee('31.084.000')
        ->assertSee('106.500.000')
        ->assertSee('55.500.000')
        ->assertSee('535.254.000')
        ->assertSee('346.464.000')
        ->assertSee('64.7')
        ->assertDontSee('-31.084.000')
        ->assertDontSee('-106.500.000')
        ->assertDontSee('-55.500.000');
});

it('reconciles UPT ICT with Section B additions without double counting and handles zero-division safely', function () {
    $ctx = makeSummaryReconciliationContext('UPT-ICT', '2032/2033');
    $importService = app(ApprovedRkaImportService::class);

    $preview = $importService->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ctx['pemohon'],
        departmentId: $ctx['dept']->id,
        scheduleId: $ctx['schedule']->id,
        mode: RkaApprovedImportBatch::TYPE_DRAFT,
        targetHeader: $ctx['header'],
    );

    $importService->executeDraftImport($preview['token'], $ctx['header'], $ctx['pemohon']);
    $freshHeader = $ctx['header']->fresh();

    $summary = app(RkaReviewSummaryService::class)->calculate($freshHeader);

    // Verifikasi tepat sesuai Excel 05_RKA_2026-2027_UPT_ICT.xlsx worksheet RINGKASAN
    expect((float) $summary['usulan_awal'])->toBe(156583000.0)
        ->and((float) $summary['dipindahkan'])->toBe(0.0)
        ->and((float) $summary['pos_cost'])->toBe(0.0)
        ->and((float) $summary['tambahan_pusat'])->toBe(17619000.0)
        ->and((float) $summary['adjustment_price'])->toBe(0.0)
        ->and((float) $summary['usulan_dibahas'])->toBe(174202000.0)
        ->and((float) $summary['disetujui_setelah_review'])->toBe(173902000.0)
        ->and((float) $summary['percentage'])->toBe(99.8);

    // Safe zero division when usulan_dibahas = 0
    $emptyHeader = RkaHeader::create([
        'schedule_id' => RkaSchedule::create([
            'fiscal_year' => '2033/2034',
            'period_range' => 'September – Agustus',
            'phase_name' => 'Empty Summary '.uniqid(),
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(20),
            'is_active' => true,
            'created_by' => $ctx['keuangan']->id,
        ])->id,
        'department_id' => $ctx['dept']->id,
        'setting_id' => $ctx['setting']->id,
        'unit_head_user_id' => $ctx['pemohon']->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'is_locked' => false,
        'pagu_budget' => 0.0,
    ]);

    $emptySummary = app(RkaReviewSummaryService::class)->calculate($emptyHeader);
    expect((float) $emptySummary['usulan_dibahas'])->toBe(0.0)
        ->and((float) $emptySummary['percentage'])->toBe(0.0);
});
