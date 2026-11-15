<?php

use App\Filament\Pages\Database\BackupManagement;
use App\Filament\Resources\FundRequests\Pages\VerifyFundRequest;
use App\Models\Department;
use App\Models\FundRequest;
use App\Models\RkaHeader;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

function ensureRoleWithPermissions(string $roleName, array $permissions = []): Role
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

    foreach ($permissions as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        if (! $role->hasPermissionTo($perm)) {
            $role->givePermissionTo($perm);
        }
    }

    return $role;
}

it('blocks pemohon from accessing VerifyFundRequest or executing saveVerification (BUG-002)', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $dept = Department::create([
        'type' => 'PRODI',
        'code' => 'D1-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept 1 Test',
    ]);

    $pemohonRole = ensureRoleWithPermissions('pemohon', ['View:FundRequest']);
    $keuanganRole = ensureRoleWithPermissions('keuangan', ['View:FundRequest']);

    $pemohon = User::factory()->create();
    $pemohon->assignRole($pemohonRole);
    $pemohon->departments()->attach($dept->id);

    $keuangan = User::factory()->create();
    $keuangan->assignRole($keuanganRole);

    $fundRequest = FundRequest::create([
        'document_number' => 'FR-'.$suffix,
        'department_id' => $dept->id,
        'request_date' => now()->toDateString(),
        'fiscal_year' => '2026/2027',
        'activity_name' => 'Kegiatan Uji',
        'status' => 'submitted',
        'total_requested' => 100000,
        'created_by' => $pemohon->id,
    ]);

    $this->actingAs($pemohon);
    expect(VerifyFundRequest::canAccess())->toBeFalse();

    $page = new VerifyFundRequest;
    $page->record = $fundRequest;
    $page->action_type = 'approve';

    expect(fn () => $page->saveVerification())
        ->toThrow(HttpException::class);

    $fundRequest->refresh();
    expect($fundRequest->status)->toBe('submitted');

    $this->actingAs($keuangan);
    expect(VerifyFundRequest::canAccess())->toBeTrue();
});

it('restricts BackupManagement page access to super_admin or authorized permission holders (BUG-003)', function () {
    $pemohonRole = ensureRoleWithPermissions('pemohon');
    $superAdminRole = ensureRoleWithPermissions('super_admin');

    $pemohon = User::factory()->create();
    $pemohon->assignRole($pemohonRole);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    auth()->logout();
    expect(BackupManagement::canAccess())->toBeFalse();

    $this->actingAs($pemohon);
    expect(BackupManagement::canAccess())->toBeFalse();

    $this->actingAs($superAdmin);
    expect(BackupManagement::canAccess())->toBeTrue();
});

it('enforces department-level ownership in FundRequestPolicy, RkaHeaderPolicy, and PDF preview (BUG-004)', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $deptA = Department::create([
        'type' => 'PRODI',
        'code' => 'DA-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept A Test',
    ]);
    $deptB = Department::create([
        'type' => 'PRODI',
        'code' => 'DB-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept B Test',
    ]);

    $pemohonRole = ensureRoleWithPermissions('pemohon', [
        'View:FundRequest',
        'Update:FundRequest',
        'View:RkaHeader',
        'Update:RkaHeader',
    ]);
    $keuanganRole = ensureRoleWithPermissions('keuangan', [
        'View:FundRequest',
        'View:RkaHeader',
    ]);

    $userA = User::factory()->create();
    $userA->assignRole($pemohonRole);
    $userA->departments()->attach($deptA->id);

    $keuangan = User::factory()->create();
    $keuangan->assignRole($keuanganRole);

    $frA = FundRequest::create([
        'document_number' => 'FRA-'.$suffix,
        'department_id' => $deptA->id,
        'request_date' => now()->toDateString(),
        'fiscal_year' => '2026/2027',
        'activity_name' => 'Kegiatan A',
        'status' => 'approved',
        'total_requested' => 50000,
        'created_by' => $userA->id,
    ]);

    $frB = FundRequest::create([
        'document_number' => 'FRB-'.$suffix,
        'department_id' => $deptB->id,
        'request_date' => now()->toDateString(),
        'fiscal_year' => '2026/2027',
        'activity_name' => 'Kegiatan B',
        'status' => 'approved',
        'total_requested' => 75000,
        'created_by' => $keuangan->id,
    ]);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'Sep 2026 - Agu 2027',
        'phase_name' => 'Pengusulan',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'is_active' => true,
        'created_by' => $userA->id,
    ]);
    $setting = RkaSetting::create([
        'setting_name' => 'Setting '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Direktur',
        'director_nrp' => '001',
        'foundation_head_name' => 'Ketua',
        'signing_place' => 'Bangkinang',
    ]);

    $rkaA = RkaHeader::create([
        'department_id' => $deptA->id,
        'schedule_id' => $schedule->id,
        'setting_id' => $setting->id,
        'fiscal_year' => '2026/2027',
        'status' => 'draft',
        'created_by' => $userA->id,
    ]);
    $rkaB = RkaHeader::create([
        'department_id' => $deptB->id,
        'schedule_id' => $schedule->id,
        'setting_id' => $setting->id,
        'fiscal_year' => '2026/2027',
        'status' => 'draft',
        'created_by' => $keuangan->id,
    ]);

    expect($userA->can('view', $frA))->toBeTrue()
        ->and($userA->can('update', $frA))->toBeTrue()
        ->and($userA->can('view', $frB))->toBeFalse()
        ->and($userA->can('update', $frB))->toBeFalse()
        ->and($userA->can('view', $rkaA))->toBeTrue()
        ->and($userA->can('view', $rkaB))->toBeFalse()
        ->and($keuangan->can('view', $frB))->toBeTrue()
        ->and($keuangan->can('view', $rkaB))->toBeTrue();

    // Cross-department PDF preview must return 403
    $this->actingAs($userA)
        ->get('/fund-requests/'.$frB->id.'/pdf-preview')
        ->assertForbidden();
});
