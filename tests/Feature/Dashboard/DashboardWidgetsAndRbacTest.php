<?php

use App\Filament\Widgets\DashboardContextBanner;
use App\Filament\Widgets\DepartmentStatsOverview;
use App\Filament\Widgets\DepartmentTopAccounts;
use App\Filament\Widgets\DepartmentTrackingTable;
use App\Filament\Widgets\DirectorDepartmentPerformance;
use App\Filament\Widgets\DirectorExecutiveApprovalQueue;
use App\Filament\Widgets\DirectorFinancialBreakdownMatrix;
use App\Filament\Widgets\DirectorFiscalYearDistributionChart;
use App\Filament\Widgets\DirectorStatsOverview;
use App\Filament\Widgets\FinanceDepartmentBudgetChart;
use App\Filament\Widgets\FinanceDisbursementTypeChart;
use App\Filament\Widgets\FinanceMonitoringTable;
use App\Filament\Widgets\FinanceStatsOverview;
use App\Filament\Widgets\FinanceVerificationQueue;
use App\Filament\Widgets\RkaOverviewWidget;
use App\Filament\Widgets\SuperAdminRecentActivity;
use App\Filament\Widgets\SuperAdminStatsOverview;
use App\Filament\Widgets\SuperAdminSystemHealth;
use App\Filament\Widgets\WadirDepartmentUtilizationTable;
use App\Filament\Widgets\WadirMonthlyDisbursementTrendChart;
use App\Filament\Widgets\WadirPendingApprovalTable;
use App\Filament\Widgets\WadirStatsOverview;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\Context\Contracts\ContextManagerInterface;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\FundRequest\FundRequestBudgetService;
use Database\Seeders\DashboardRbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

function makeDashboardTestContext(): array
{
    app(DashboardRbacSeeder::class)->run();

    RkaSchedule::query()->where('is_active', true)->update(['is_active' => false]);
    $suffix = strtoupper(substr(uniqid(), -6));

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $p3mDept = Department::where('code', 'P3M')->firstOrFail();

    $superAdmin = User::factory()->create(['name' => 'Dash SuperAdmin '.$suffix]);
    $superAdmin->assignRole(Role::where('name', 'super_admin')->firstOrFail());

    $stafKeuangan = User::factory()->create(['name' => 'Dash StafKeu '.$suffix]);
    $stafKeuangan->assignRole(Role::where('name', 'staf_keuangan')->firstOrFail());

    $keuangan = User::factory()->create(['name' => 'Dash Keuangan '.$suffix]);
    $keuangan->assignRole(Role::where('name', 'keuangan')->firstOrFail());

    $wadir = User::factory()->create(['name' => 'Dash Wadir '.$suffix]);
    $wadir->assignRole(Role::where('name', 'wadir_keuangan')->firstOrFail());

    $direktur = User::factory()->create(['name' => 'Dash Direktur '.$suffix]);
    $direktur->assignRole(Role::where('name', 'direktur')->firstOrFail());

    $pemohonIct = User::factory()->create(['name' => 'Dash Pemohon ICT '.$suffix]);
    $pemohonIct->assignRole(Role::where('name', 'pemohon')->firstOrFail());
    $ictDept->users()->attach($pemohonIct->id, ['is_primary' => true]);

    $pemohonP3m = User::factory()->create(['name' => 'Dash Pemohon P3M '.$suffix]);
    $pemohonP3m->assignRole(Role::where('name', 'pemohon')->firstOrFail());
    $p3mDept->users()->attach($pemohonP3m->id, ['is_primary' => true]);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2041/2042',
        'period_range' => 'September 2041 – Agustus 2042',
        'phase_name' => 'Dashboard Test '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $superAdmin->id,
    ]);

    $setting = RkaSetting::query()->firstOrFail();
    $account52 = Account::where('code', '5.2')->firstOrFail();
    $detailAccount = Account::query()->where('code', 'like', '5.2.%')->orderByRaw('LENGTH(code) DESC')->firstOrFail();

    $ictRka = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $pemohonIct->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 50000000.0,
    ]);

    $ictIndicator = RkaIndicator::create([
        'rka_header_id' => $ictRka->id,
        'account_id' => $account52->id,
        'program_code' => 'PRG-ICT',
        'program_name' => 'Program ICT',
        'indicator_code' => 'IND-ICT',
        'indicator_name' => 'Indikator ICT',
    ]);

    $ictItem = RkaItem::create([
        'rka_header_id' => $ictRka->id,
        'rka_indicator_id' => $ictIndicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Perangkat Server ICT '.$suffix,
        'proposed_volume' => 5,
        'unit_symbol' => 'Unit',
        'unit_price' => 4000000,
        'approved_volume' => 4,
        'approved_total' => 16000000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    // Unapproved DRAFT RKA for P3M (must NOT be counted as approved RKA)
    $p3mRka = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $p3mDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $pemohonP3m->id,
        'status' => RkaHeader::STATUS_DRAFT,
        'is_locked' => false,
        'pagu_budget' => 30000000.0,
    ]);

    $p3mIndicator = RkaIndicator::create([
        'rka_header_id' => $p3mRka->id,
        'account_id' => $account52->id,
        'program_code' => 'PRG-P3M',
        'program_name' => 'Program P3M',
        'indicator_code' => 'IND-P3M',
        'indicator_name' => 'Indikator P3M',
    ]);

    RkaItem::create([
        'rka_header_id' => $p3mRka->id,
        'rka_indicator_id' => $p3mIndicator->id,
        'account_id' => $detailAccount->id,
        'form_type' => '5.2',
        'description' => 'Draft Item P3M '.$suffix,
        'proposed_volume' => 2,
        'unit_symbol' => 'Paket',
        'unit_price' => 5000000,
        'approved_volume' => 2,
        'approved_total' => 10000000,
        'status' => RkaItem::STATUS_PENDING,
    ]);

    DepartmentBudget::create([
        'department_id' => $ictDept->id,
        'account_id' => $detailAccount->id,
        'fiscal_year' => $schedule->fiscal_year,
        'schedule_id' => $schedule->id,
        'allocated_amount' => 50000000.0,
    ]);

    $ictFundRequest = app(FundRequestBudgetService::class)->createFundRequest(
        $pemohonIct,
        [
            'document_number' => 'FR-DASH-'.$suffix,
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ictDept->id,
            'activity_name' => 'Pengadaan ICT '.$suffix,
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Bendahara ICT',
            'attachments' => [],
        ],
        [
            ['rka_item_id' => $ictItem->id, 'volume' => 2],
        ]
    );

    return compact(
        'superAdmin',
        'stafKeuangan',
        'keuangan',
        'wadir',
        'direktur',
        'pemohonIct',
        'pemohonP3m',
        'ictDept',
        'p3mDept',
        'schedule',
        'ictRka',
        'p3mRka',
        'ictFundRequest',
        'suffix'
    );
}

it('calculates accurate KPIs without double counting or counting unapproved RKA', function () {
    $ctx = makeDashboardTestContext();
    $service = app(DashboardMetricsService::class);

    $budgetSummary = $service->getBudgetAndRealizationSummary($ctx['schedule']->fiscal_year, $ctx['ictDept']->id);
    expect($budgetSummary['pagu'])->toBe(50000000.0)
        ->and($budgetSummary['approved_amount'])->toBe(0.0)
        ->and($budgetSummary['pending_amount'])->toBe(8000000.0)
        ->and($budgetSummary['remaining_budget'])->toBe(42000000.0);

    $rkaSummary = $service->getRkaSummary($ctx['schedule']);
    expect($rkaSummary['approved'])->toBe(1)
        ->and($rkaSummary['draft'])->toBe(1)
        ->and($rkaSummary['total_proposed'])->toBe(30000000.0) // (5*4M) + (2*5M)
        ->and($rkaSummary['total_approved'])->toBe(16000000.0); // Only APPROVED RKA counted
});

it('enforces read-only RBAC and dashboard visibility for staf_keuangan', function () {
    $ctx = makeDashboardTestContext();
    $staf = $ctx['stafKeuangan'];

    expect(FundRequest::class)->not->toBeNull();
    expect($staf->can('viewAny', FundRequest::class))->toBeTrue()
        ->and($staf->can('view', $ctx['ictFundRequest']))->toBeTrue()
        ->and($staf->can('create', FundRequest::class))->toBeFalse()
        ->and($staf->can('update', $ctx['ictFundRequest']))->toBeFalse()
        ->and($staf->can('delete', $ctx['ictFundRequest']))->toBeFalse()
        ->and($staf->can('create', RkaHeader::class))->toBeFalse()
        ->and($staf->can('update', $ctx['ictRka']))->toBeFalse()
        ->and($staf->can('create', DepartmentBudget::class))->toBeFalse();

    $this->actingAs($staf);
    expect(FinanceStatsOverview::canView())->toBeTrue()
        ->and(RkaOverviewWidget::canView())->toBeTrue()
        ->and(FinanceMonitoringTable::canView())->toBeTrue()
        ->and(FinanceDepartmentBudgetChart::canView())->toBeTrue()
        ->and(FinanceDisbursementTypeChart::canView())->toBeTrue()
        ->and(WadirDepartmentUtilizationTable::canView())->toBeTrue()
        ->and(FinanceVerificationQueue::canView())->toBeFalse()
        ->and(SuperAdminStatsOverview::canView())->toBeFalse();

    // Direct URL access to create/edit/verify must be forbidden
    $this->actingAs($staf)
        ->get(route('filament.admin.resources.fund-requests.create'))
        ->assertForbidden();

    $this->actingAs($staf)
        ->get(route('filament.admin.resources.fund-requests.edit', $ctx['ictFundRequest']))
        ->assertForbidden();

    $this->actingAs($staf)
        ->get(route('filament.admin.resources.fund-requests.verify', $ctx['ictFundRequest']))
        ->assertForbidden();

    Livewire::actingAs($staf)
        ->test(FinanceMonitoringTable::class)
        ->assertSee($ctx['ictFundRequest']->document_number);
});

it('scopes pemohon dashboard widgets strictly to active department', function () {
    $ctx = makeDashboardTestContext();

    $this->actingAs($ctx['pemohonP3m']);
    app(ContextManagerInterface::class)->setActiveDepartment($ctx['p3mDept']->id);

    expect(DepartmentStatsOverview::canView())->toBeTrue()
        ->and(RkaOverviewWidget::canView())->toBeTrue()
        ->and(DepartmentTrackingTable::canView())->toBeTrue()
        ->and(DepartmentTopAccounts::canView())->toBeTrue()
        ->and(FinanceVerificationQueue::canView())->toBeFalse()
        ->and(SuperAdminStatsOverview::canView())->toBeFalse();

    Livewire::actingAs($ctx['pemohonP3m'])
        ->test(DepartmentTrackingTable::class)
        ->assertDontSee($ctx['ictFundRequest']->document_number);

    // P3M pemohon cannot view ICT FundRequest directly
    expect($ctx['pemohonP3m']->can('view', $ctx['ictFundRequest']))->toBeFalse();
});

it('renders role-specific widgets without duplicates for super_admin, keuangan, wadir, and direktur', function () {
    $ctx = makeDashboardTestContext();

    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
    $superAdminRole->givePermissionTo([
        'View:DashboardContextBanner',
        'View:SuperAdminStatsOverview',
        'View:SuperAdminSystemHealth',
        'View:SuperAdminRecentActivity',
        'View:RkaOverviewWidget',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($ctx['superAdmin']);
    expect(SuperAdminStatsOverview::canView())->toBeTrue()
        ->and(SuperAdminSystemHealth::canView())->toBeTrue()
        ->and(SuperAdminRecentActivity::canView())->toBeTrue()
        ->and(RkaOverviewWidget::canView())->toBeTrue();

    Livewire::actingAs($ctx['superAdmin'])
        ->test(DashboardContextBanner::class)
        ->assertSee('Super Administrator')
        ->assertSee($ctx['schedule']->fiscal_year);

    $this->actingAs($ctx['keuangan']);
    expect(FinanceStatsOverview::canView())->toBeTrue()
        ->and(FinanceVerificationQueue::canView())->toBeTrue()
        ->and(SuperAdminStatsOverview::canView())->toBeFalse();

    $this->actingAs($ctx['wadir']);
    expect(WadirStatsOverview::canView())->toBeTrue()
        ->and(WadirPendingApprovalTable::canView())->toBeTrue()
        ->and(WadirMonthlyDisbursementTrendChart::canView())->toBeTrue()
        ->and(SuperAdminStatsOverview::canView())->toBeFalse();

    $this->actingAs($ctx['direktur']);
    expect(DirectorStatsOverview::canView())->toBeTrue()
        ->and(DirectorExecutiveApprovalQueue::canView())->toBeTrue()
        ->and(DirectorDepartmentPerformance::canView())->toBeTrue()
        ->and(DirectorFinancialBreakdownMatrix::canView())->toBeTrue()
        ->and(DirectorFiscalYearDistributionChart::canView())->toBeTrue();
});

it('does not grant cross-unit access without ViewAllUnits permission', function () {
    $ctx = makeDashboardTestContext();

    // Create a custom role with View:FundRequest but WITHOUT ViewAllUnits:FundRequest
    $limitedRole = Role::firstOrCreate(['name' => 'limited_viewer', 'guard_name' => 'web']);
    $limitedRole->syncPermissions([
        Permission::firstOrCreate(['name' => 'ViewAny:FundRequest', 'guard_name' => 'web'])->name,
        Permission::firstOrCreate(['name' => 'View:FundRequest', 'guard_name' => 'web'])->name,
    ]);

    $limitedUser = User::factory()->create(['name' => 'Limited Viewer '.$ctx['suffix']]);
    $limitedUser->assignRole($limitedRole);
    $ctx['p3mDept']->users()->attach($limitedUser->id, ['is_primary' => true]);

    expect($limitedUser->can('view', $ctx['ictFundRequest']))->toBeFalse();
});
