<?php

use App\Filament\Pages\Development\CleanDataRkaPage;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\RkaAdjustment;
use App\Models\RkaApproval;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\Development\RkaDataCleanupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

function withAppEnv(string $env, callable $callback): mixed
{
    $originalEnv = app()->environment();
    app()->detectEnvironment(fn (): string => $env);

    try {
        return $callback();
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnv);
    }
}

function makeCleanupTestContext(): array
{
    $suffix = strtoupper(substr(uniqid(), -6));

    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create(['name' => 'SuperAdmin '.$suffix]);
    $superAdmin->assignRole($superAdminRole);

    $regularUser = User::factory()->create(['name' => 'Keuangan '.$suffix]);
    $regularUser->assignRole($keuanganRole);

    $dept = Department::create([
        'type' => 'PRODI',
        'code' => 'CLN-'.$suffix,
        'level_code' => '3',
        'name' => 'Prodi Cleanup '.$suffix,
    ]);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Cleanup Phase '.$suffix,
        'start_at' => now()->subDay(),
        'end_at' => now()->addDays(3),
        'is_active' => true,
        'created_by' => $superAdmin->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'Setting Cleanup '.$suffix,
        'director_user_id' => $superAdmin->id,
        'director_name' => $superAdmin->name,
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua Yayasan',
        'signing_place' => 'Bangkinang',
    ]);

    $account = Account::firstOrCreate(
        ['code' => '5.2'],
        ['code_clean' => '52', 'name' => 'Belanja Barang dan Jasa', 'level' => 1, 'level_label' => 'Kelompok', 'is_active' => true]
    );

    $header = RkaHeader::create([
        'schedule_id' => $schedule->id,
        'department_id' => $dept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $superAdmin->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'pagu_budget' => 100000,
    ]);

    $indicator = RkaIndicator::create([
        'rka_header_id' => $header->id,
        'account_id' => $account->id,
        'program_name' => 'Program Cleanup',
        'activity_name' => 'Kegiatan Cleanup',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    $item = RkaItem::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'account_id' => $account->id,
        'form_type' => '5.2',
        'description' => 'Item Cleanup',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
        'approved_volume' => 1,
        'approved_total' => 100000,
        'status' => 'APPROVED',
    ]);

    RkaAdjustment::create([
        'rka_header_id' => $header->id,
        'rka_indicator_id' => $indicator->id,
        'rka_item_id' => $item->id,
        'section_type' => RkaAdjustment::SECTION_A,
        'action_type' => RkaAdjustment::ACTION_PENDING,
        'account_id' => $account->id,
        'description' => 'Adj Cleanup',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 100000,
        'proposed_amount' => 100000,
        'approved_amount' => 0,
        'adjusted_by' => $superAdmin->id,
    ]);

    RkaApproval::create([
        'rka_header_id' => $header->id,
        'approver_user_id' => $superAdmin->id,
        'stage' => RkaApproval::STAGE_DIREKTUR_APPROVE,
        'action' => RkaApproval::ACTION_APPROVED,
        'comments' => 'Approved',
    ]);

    RkaLog::record(
        action: RkaLog::ACTION_APPROVE_RKA,
        headerId: $header->id,
        userId: $superAdmin->id,
        subject: $header,
        description: 'Log Cleanup',
    );

    // Synced budget (should be deleted) vs manual budget (should be kept)
    $syncedBudget = DepartmentBudget::create([
        'department_id' => $dept->id,
        'account_id' => $account->id,
        'fiscal_year' => '2026/2027',
        'schedule_id' => $schedule->id,
        'allocated_amount' => 100000,
    ]);

    $manualAccount = Account::create([
        'code' => '5.2.99.'.$suffix,
        'code_clean' => '5299'.$suffix,
        'name' => 'Manual Account '.$suffix,
        'parent_id' => $account->id,
        'parent_code' => '5.2',
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);

    $manualBudget = DepartmentBudget::create([
        'department_id' => $dept->id,
        'account_id' => $manualAccount->id,
        'fiscal_year' => '2026/2027',
        'schedule_id' => null,
        'allocated_amount' => 250000,
    ]);

    $fundRequest = FundRequest::create([
        'document_number' => 'FR-CLN-'.$suffix,
        'request_date' => now()->toDateString(),
        'fiscal_year' => '2026/2027',
        'department_id' => $dept->id,
        'activity_name' => 'Kegiatan FR',
        'responsible_person' => $superAdmin->name,
        'disbursement_type' => 'TU',
        'recipient_name' => $superAdmin->name,
        'total_requested' => 50000,
        'total_approved' => 0,
        'status' => 'draft',
        'created_by' => $superAdmin->id,
    ]);

    return compact('superAdmin', 'regularUser', 'dept', 'schedule', 'setting', 'account', 'header', 'syncedBudget', 'manualBudget', 'fundRequest');
}

test('authorization blocks non-super_admin and disallowed environments including testing, test, production, staging', function () {
    $ctx = makeCleanupTestContext();
    $service = app(RkaDataCleanupService::class);

    // Disallowed environments
    foreach (['testing', 'test', 'production', 'staging'] as $env) {
        withAppEnv($env, function () use ($ctx, $service): void {
            $this->actingAs($ctx['superAdmin']);

            expect($service->isAvailable($ctx['superAdmin']))->toBeFalse()
                ->and(CleanDataRkaPage::canAccess())->toBeFalse()
                ->and(CleanDataRkaPage::shouldRegisterNavigation())->toBeFalse();

            expect(fn () => $service->preview($ctx['superAdmin']))->toThrow(RuntimeException::class);
            expect(fn () => $service->execute(RkaDataCleanupService::CONFIRMATION_PHRASE, $ctx['superAdmin']))->toThrow(RuntimeException::class);

            $page = new CleanDataRkaPage;
            expect(fn () => $page->mount())->toThrow(HttpException::class);
        });
    }

    // Allowed environments: local & development
    foreach (['local', 'development'] as $env) {
        withAppEnv($env, function () use ($ctx, $service): void {
            $this->actingAs($ctx['superAdmin']);
            expect($service->isAvailable($ctx['superAdmin']))->toBeTrue()
                ->and(CleanDataRkaPage::canAccess())->toBeTrue();

            // Non-super_admin tetap ditolak meskipun di local/development
            $this->actingAs($ctx['regularUser']);
            expect($service->isAvailable($ctx['regularUser']))->toBeFalse()
                ->and(CleanDataRkaPage::canAccess())->toBeFalse();
            expect(fn () => $service->preview($ctx['regularUser']))->toThrow(AuthorizationException::class);
            expect(fn () => $service->execute(RkaDataCleanupService::CONFIRMATION_PHRASE, $ctx['regularUser']))->toThrow(AuthorizationException::class);
        });
    }
});

test('preview counts actual data without mutating database and excludes master data', function () {
    $ctx = makeCleanupTestContext();
    $service = app(RkaDataCleanupService::class);

    withAppEnv('local', function () use ($ctx, $service): void {
        $this->actingAs($ctx['superAdmin']);

        $beforeHeaders = RkaHeader::count();
        $preview = $service->preview($ctx['superAdmin']);

        expect($preview['database']['environment'])->toBe('local')
            ->and($preview['total_to_delete'])->toBeGreaterThan(0)
            ->and(collect($preview['plan']['delete_targets'])->pluck('table')->all())
            ->not->toContain('users', 'departments', 'accounts', 'rka_settings', 'rka_schedules');

        expect(RkaHeader::count())->toBe($beforeHeaders);
    });
});

test('execute requires exact confirmation phrase and blocks production database names', function () {
    $ctx = makeCleanupTestContext();
    $service = app(RkaDataCleanupService::class);

    withAppEnv('development', function () use ($ctx, $service): void {
        $this->actingAs($ctx['superAdmin']);

        expect(fn () => $service->execute('', $ctx['superAdmin']))->toThrow(InvalidArgumentException::class);
        expect(fn () => $service->execute('clean rka data', $ctx['superAdmin']))->toThrow(InvalidArgumentException::class);

        // Database safety guard menolak nama DB mengandung production
        $defaultConn = config('database.default');
        $originalDb = config("database.connections.{$defaultConn}.database");
        config(["database.connections.{$defaultConn}.database" => 'simrka_production']);

        try {
            expect(fn () => $service->execute(RkaDataCleanupService::CONFIRMATION_PHRASE, $ctx['superAdmin']))
                ->toThrow(RuntimeException::class);
        } finally {
            config(["database.connections.{$defaultConn}.database" => $originalDb]);
        }
    });
});

test('execute deletes all RKA operational data while preserving master data, super_admin, manual budgets, and fund requests', function () {
    $ctx = makeCleanupTestContext();
    $service = app(RkaDataCleanupService::class);

    withAppEnv('local', function () use ($ctx, $service): void {
        $this->actingAs($ctx['superAdmin']);

        $result = $service->execute(RkaDataCleanupService::CONFIRMATION_PHRASE, $ctx['superAdmin']);

        expect($result['status'])->toBe('SUCCESS')
            ->and(RkaHeader::count())->toBe(0)
            ->and(RkaIndicator::count())->toBe(0)
            ->and(RkaItem::count())->toBe(0)
            ->and(RkaAdjustment::count())->toBe(0)
            ->and(RkaApproval::count())->toBe(0)
            ->and(RkaLog::count())->toBe(0)
            ->and(DB::table('rka_item_reviews')->count())->toBe(0)
            ->and(DepartmentBudget::find($ctx['syncedBudget']->id))->toBeNull();

        // Master data, super_admin, manual budget, dan fund_request tetap utuh
        expect(User::find($ctx['superAdmin']->id))->not->toBeNull()
            ->and(Department::find($ctx['dept']->id))->not->toBeNull()
            ->and(Account::find($ctx['account']->id))->not->toBeNull()
            ->and(RkaSchedule::find($ctx['schedule']->id))->not->toBeNull()
            ->and(RkaSetting::find($ctx['setting']->id))->not->toBeNull()
            ->and(DepartmentBudget::find($ctx['manualBudget']->id))->not->toBeNull()
            ->and(FundRequest::find($ctx['fundRequest']->id))->not->toBeNull();
    });
});
