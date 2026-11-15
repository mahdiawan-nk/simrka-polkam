<?php

use App\Filament\Resources\RkaGenerateConfigs\RkaGenerateConfigResource;
use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Filament\Resources\RkaSchedules\RkaScheduleResource;
use App\Filament\Resources\RkaSettings\RkaSettingResource;
use App\Filament\Widgets\FinanceStatsOverview;
use App\Filament\Widgets\SuperAdminSystemHealth;
use App\Models\Department;
use App\Models\FundRequest;
use App\Models\RkaSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

it('aggregates FinanceStatsOverview and SuperAdminSystemHealth without N+1 or full-model loading (BUG-017)', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $user = User::factory()->create();
    $dept = Department::create([
        'type' => 'PRODI',
        'code' => 'ST-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept Stats Test',
    ]);

    RkaSchedule::query()->update(['is_active' => false]);
    RkaSchedule::create([
        'fiscal_year' => '2029/2030',
        'period_range' => 'Sep 2029 - Agu 2030',
        'phase_name' => 'Pengusulan',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    FundRequest::create([
        'document_number' => 'FR-ST1-'.$suffix,
        'department_id' => $dept->id,
        'request_date' => now()->toDateString(),
        'fiscal_year' => '2029/2030',
        'activity_name' => 'Kegiatan Stats',
        'status' => 'approved',
        'total_requested' => 500000,
        'total_approved' => 350000,
        'created_by' => $user->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $financeWidget = new class extends FinanceStatsOverview
    {
        public function exposeStats(): array
        {
            return $this->getStats();
        }
    };
    $stats = $financeWidget->exposeStats();
    $financeQueries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($stats)->toHaveCount(3)
        ->and($stats[2]->getValue())->toContain('150.000,00')
        ->and(count($financeQueries))->toBeLessThanOrEqual(5);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $healthWidget = new class extends SuperAdminSystemHealth
    {
        public function exposeViewData(): array
        {
            return $this->getViewData();
        }
    };
    $data = $healthWidget->exposeViewData();
    $healthQueries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($data['statusCounts']['approved'])->toBeGreaterThanOrEqual(1)
        ->and(count($healthQueries))->toBeLessThanOrEqual(5);
});

it('uses consistent Manajemen RKA navigation group and removes duplicate ReviewRkaPage copy (BUG-018)', function () {
    expect(RkaSettingResource::getNavigationGroup())->toBe('Manajemen RKA')
        ->and(RkaScheduleResource::getNavigationGroup())->toBe('Manajemen RKA')
        ->and(RkaGenerateConfigResource::getNavigationGroup())->toBe('Manajemen RKA')
        ->and(RkaHeaderResource::getNavigationGroup())->toBe('Manajemen RKA')
        ->and(file_exists(app_path('Filament/Resources/RkaHeaders/Pages/ReviewRkaPage copy.php')))->toBeFalse();
});
