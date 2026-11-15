<?php

use App\Filament\Resources\FundRequests\Pages\EditFundRequestCustom;
use App\Models\Account;
use App\Models\Department;
use App\Models\FundRequest;
use App\Models\FundRequestItem;
use App\Models\User;
use App\Services\Context\ContextManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('preserves existing department_id and status when editing FundRequest (BUG-007)', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $deptOriginal = Department::create([
        'type' => 'PRODI',
        'code' => 'DO-'.$suffix,
        'level_code' => '3',
        'name' => 'Dept Original Test',
    ]);
    $deptActive = Department::create([
        'type' => 'UPT',
        'code' => 'DA-'.$suffix,
        'level_code' => '2',
        'name' => 'Dept Active Test',
    ]);
    $account = Account::create([
        'code' => '9.7.'.$suffix,
        'code_clean' => '97'.$suffix,
        'name' => 'Akun FR '.$suffix,
        'level' => 3,
        'level_label' => 'Rincian',
        'is_active' => true,
    ]);

    $role = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    $perm = Permission::firstOrCreate(['name' => 'Update:FundRequest', 'guard_name' => 'web']);
    $role->givePermissionTo($perm);

    $user = User::factory()->create();
    $user->assignRole($role);
    $user->departments()->attach($deptActive->id, ['assignment_type' => 'primary']);

    $fundRequest = FundRequest::create([
        'document_number' => 'FR-ED-'.$suffix,
        'department_id' => $deptOriginal->id,
        'request_date' => now()->toDateString(),
        'fiscal_year' => '2026/2027',
        'activity_name' => 'Kegiatan Draft',
        'execution_date' => now()->toDateString(),
        'responsible_person' => 'PIC Uji',
        'disbursement_type' => 'LS',
        'recipient_name' => 'Penerima Uji',
        'bank_account_info' => '123456789',
        'status' => 'draft',
        'total_requested' => 100000,
        'created_by' => $user->id,
    ]);
    $item = FundRequestItem::create([
        'fund_request_id' => $fundRequest->id,
        'account_id' => $account->id,
        'description' => 'Item Uji',
        'volume' => 1,
        'unit' => 'Unit',
        'unit_price' => 100000,
        'total_price' => 100000,
    ]);

    $this->actingAs($user);
    app(ContextManager::class)->setActiveDepartment($deptActive->id);

    $page = new EditFundRequestCustom;
    $page->mount($fundRequest->id);

    expect($page->department_id)->toBe($deptOriginal->id);

    $page->save();

    $fundRequest->refresh();
    expect($fundRequest->department_id)->toBe($deptOriginal->id)
        ->and($fundRequest->status)->toBe('draft');
});

it('loads pivot assignment_type, start_date, and end_date on User::departments() for PLT context (BUG-008)', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $dept = Department::create([
        'type' => 'UPT',
        'code' => 'PLT-'.$suffix,
        'level_code' => '2',
        'name' => 'Dept PLT Test',
    ]);

    $user = User::factory()->create();
    $user->departments()->attach($dept->id, [
        'assignment_type' => 'plt',
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($user);
    $context = app(ContextManager::class);
    $context->setActiveDepartment($dept->id);

    expect($context->getActiveAssignmentType())->toBe('plt');
});
