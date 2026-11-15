<?php

use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\ApprovedRkaImport\ApprovedRkaImportService;
use App\Services\ApprovedRkaImport\DepartmentResolver;
use App\Services\ApprovedRkaImport\NativeRkaWorkbookParser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('resolves exact department name, exact code, verified aliases, case differences, and extra whitespace', function () {
    $resolver = app(DepartmentResolver::class);
    $departments = Department::query()->get();

    // 1. Exact departments.name
    $resName = $resolver->resolve('Pusat Penelitian dan Pengabdian Masyarakat (P3M)', $departments);
    expect($resName['status'])->toBe(DepartmentResolver::STATUS_RESOLVED)
        ->and($resName['department']?->code)->toBe('P3M');

    // 2. Exact departments.code
    $resCode = $resolver->resolve('PRODI-TIF', $departments);
    expect($resCode['status'])->toBe(DepartmentResolver::STATUS_RESOLVED)
        ->and($resCode['department']?->code)->toBe('PRODI-TIF');

    // 3. Verified alias in DepartmentResolver::UNIT_ALIASES
    $resAliasP3m = $resolver->resolve('UPT Penelitian dan Pengabdian Kepada Masyarakat (P3M)', $departments);
    expect($resAliasP3m['status'])->toBe(DepartmentResolver::STATUS_RESOLVED)
        ->and($resAliasP3m['department']?->code)->toBe('P3M');

    $resAliasAbi = $resolver->resolve('Prodi D4 Administrasi Bisnis Internasional (ABI)', $departments);
    expect($resAliasAbi['status'])->toBe(DepartmentResolver::STATUS_RESOLVED)
        ->and($resAliasAbi['department']?->code)->toBe('PRODI-ABI');

    $resAliasUmum = $resolver->resolve('Bagian Umum', $departments);
    expect($resAliasUmum['status'])->toBe(DepartmentResolver::STATUS_RESOLVED)
        ->and($resAliasUmum['department']?->code)->toBe('BAG-UMUM');

    // 4. Case differences
    $resCase = $resolver->resolve('uPt iCt', $departments);
    expect($resCase['status'])->toBe(DepartmentResolver::STATUS_RESOLVED)
        ->and($resCase['department']?->code)->toBe('UPT-ICT');

    // 5. Extra whitespace
    $resSpaces = $resolver->resolve('   Prodi   D3   Teknik   Informatika   (TIF)  ', $departments);
    expect($resSpaces['status'])->toBe(DepartmentResolver::STATUS_RESOLVED)
        ->and($resSpaces['department']?->code)->toBe('PRODI-TIF');
});

it('returns INVALID_INPUT for empty or punctuation-only input and NOT_FOUND for unknown unit', function () {
    $resolver = app(DepartmentResolver::class);
    $departments = Department::query()->get();

    foreach ([null, '', '   ', '-', '---'] as $invalid) {
        $res = $resolver->resolve($invalid, $departments);
        expect($res['status'])->toBe(DepartmentResolver::STATUS_INVALID_INPUT)
            ->and($res['department'])->toBeNull();
    }

    // 6. Unknown unit
    $unknown = $resolver->resolve('Unit Fiktif Tidak Terdaftar', $departments);
    expect($unknown['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND)
        ->and($unknown['department'])->toBeNull()
        ->and($unknown['reason'])->not->toBeEmpty();
});

it('returns AMBIGUOUS instead of picking the first department when multiple departments match', function () {
    $resolver = app(DepartmentResolver::class);

    $deptA = new Department(['code' => 'UNIT-DUP-1', 'name' => 'Unit Layanan Kemahasiswaan']);
    $deptA->id = 9001;
    $deptB = new Department(['code' => 'UNIT-DUP-2', 'name' => 'Unit Layanan  Kemahasiswaan']);
    $deptB->id = 9002;

    $ambiguous = $resolver->resolve('Unit Layanan Kemahasiswaan', collect([$deptA, $deptB]));

    expect($ambiguous['status'])->toBe(DepartmentResolver::STATUS_AMBIGUOUS)
        ->and($ambiguous['department'])->toBeNull()
        ->and($ambiguous['reason'])->toContain('lebih dari satu');
});

it('does not produce a false positive when an alias target code is absent from the department collection', function () {
    $resolver = app(DepartmentResolver::class);

    // Collection intentionally excludes P3M
    $withoutP3m = Department::query()->where('code', '!=', 'P3M')->get();

    $res = $resolver->resolve('UPT Penelitian dan Pengabdian Kepada Masyarakat (P3M)', $withoutP3m);

    expect($res['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND)
        ->and($res['department'])->toBeNull();
});

it('resolves IDENTITAS!C5 and Section B target_unit_id consistently through NativeRkaWorkbookParser and ApprovedRkaImportService', function () {
    $suffix = strtoupper(substr(uniqid(), -6));
    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'View:RkaHeader', 'guard_name' => 'web']);

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $p3mDept = Department::where('code', 'P3M')->firstOrFail();
    $ictUser = User::factory()->create(['name' => 'Resolver ICT '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'Resolver Keu '.$suffix]);
    $ictUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);
    $ictDept->users()->attach($ictUser->id);

    $schedule = RkaSchedule::create([
        'fiscal_year' => '2026/2027',
        'period_range' => 'September 2026 – Agustus 2027',
        'phase_name' => 'Resolver Test '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $ictUser->id,
    ]);

    RkaSetting::create([
        'setting_name' => 'Setting Resolver '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    $parser = app(NativeRkaWorkbookParser::class);
    $departments = Department::query()->get();
    $accounts = Account::query()->get()->keyBy('code');

    $parsed = $parser->parse(base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'), $accounts, $departments);
    expect($parsed['matched_department']['id'] ?? null)->toBe($ictDept->id)
        ->and($parser->matchDepartment($parsed['identitas']['unit_kerja'], $departments)?->id)->toBe($ictDept->id);

    $service = app(ApprovedRkaImportService::class);
    $preview = $service->preview(
        filePath: base_path('data-rka/05_RKA_2026-2027_UPT_ICT.xlsx'),
        originalFilename: '05_RKA_2026-2027_UPT_ICT.xlsx',
        user: $ictUser,
        departmentId: $ictDept->id,
        scheduleId: $schedule->id,
    );

    $batch = $service->executeImport($preview['token'], $ictUser);
    $header = RkaHeader::findOrFail($batch->rka_header_id);

    expect($header->department_id)->toBe($ictDept->id);

    $p3mSectionB = RkaAdjustment::where('rka_header_id', $header->id)
        ->where('section_type', RkaAdjustment::SECTION_B)
        ->where('target_unit_id', $p3mDept->id)
        ->count();

    expect($p3mSectionB)->toBeGreaterThan(0);
});
