<?php

use App\Models\Department;
use App\Services\ApprovedRkaImport\DepartmentResolver;
use Illuminate\Support\Collection;

function makeFixtureDepartment(int $id, string $code, string $name, array $extra = []): Department
{
    $dept = new Department;
    $dept->setRawAttributes(array_merge([
        'id' => $id,
        'type' => 'unit',
        'code' => $code,
        'level_code' => 'PK.3',
        'name' => $name,
    ], $extra), true);

    return $dept;
}

it('resolves exact code and exact name regardless of casing and extra spaces', function () {
    $resolver = new DepartmentResolver;
    $departments = new Collection([
        makeFixtureDepartment(1, 'UPT-ICT', 'UPT ICT'),
        makeFixtureDepartment(2, 'P3M', 'Pusat Penelitian dan Pengabdian Masyarakat (P3M)'),
        makeFixtureDepartment(3, 'UPT-MEDIA', 'UPT Media Center'),
        makeFixtureDepartment(4, 'BAG-UMUM', 'Bag. UMUM'),
    ]);

    expect($resolver->resolve('UPT-ICT', $departments))
        ->toMatchArray(['status' => DepartmentResolver::STATUS_RESOLVED])
        ->and($resolver->resolve('UPT-ICT', $departments)['department']->id)->toBe(1);

    expect($resolver->resolve('  upt   ict  ', $departments)['department']->id)->toBe(1);
    expect($resolver->resolve('p3m', $departments)['department']->id)->toBe(2);
    expect($resolver->resolve('  upt   media   center ', $departments)['department']->id)->toBe(3);
    expect($resolver->resolve('Bagian Umum', $departments)['department']->id)->toBe(4);
});

it('resolves codes with hyphens and parenthetical abbreviations uniquely', function () {
    $resolver = new DepartmentResolver;
    $departments = new Collection([
        makeFixtureDepartment(1, 'UPT-ICT', 'UPT ICT'),
        makeFixtureDepartment(2, 'PRODI-TIF', 'Prodi Teknik Informatika (TIF)'),
        makeFixtureDepartment(3, 'PRODI-ABI', 'Prodi Administrasi Bisnis (ABI)'),
    ]);

    expect($resolver->resolve('UPT–ICT', $departments)['department']->id)->toBe(1);
    expect($resolver->resolve('UPT_ICT', $departments)['department']->id)->toBe(1);
    expect($resolver->resolve('Prodi D3 Teknik Informatika (TIF)', $departments)['department']->id)->toBe(2);
    expect($resolver->resolve('Prodi D4 Administrasi Bisnis Internasional (ABI)', $departments)['department']->id)->toBe(3);
    expect($resolver->resolve('Prodi D3 TIF', $departments)['department']->id)->toBe(2);
    expect($resolver->resolve('Prodi D4 ABI', $departments)['department']->id)->toBe(3);
});

it('returns NOT_FOUND when department does not exist or input is empty', function () {
    $resolver = new DepartmentResolver;
    $departments = new Collection([
        makeFixtureDepartment(1, 'UPT-ICT', 'UPT ICT'),
    ]);

    expect($resolver->resolve('', $departments)['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND)
        ->and($resolver->resolve('-', $departments)['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND)
        ->and($resolver->resolve('Unit Tidak Ada', $departments)['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND);
});

it('returns AMBIGUOUS for duplicate codes, duplicate names, or ambiguous abbreviations', function () {
    $resolver = new DepartmentResolver;
    $departments = new Collection([
        makeFixtureDepartment(1, 'UNIT-X', 'Unit Sama'),
        makeFixtureDepartment(2, 'UNIT-X', 'Unit Berbeda'),
        makeFixtureDepartment(3, 'PRODI-TIF', 'Unit Sama'),
        makeFixtureDepartment(4, 'LAB-A', 'Laboratorium (LAB)'),
        makeFixtureDepartment(5, 'LAB-B', 'Laboratorium B (LAB)'),
    ]);

    expect($resolver->resolve('UNIT-X', $departments)['status'])->toBe(DepartmentResolver::STATUS_AMBIGUOUS)
        ->and($resolver->resolve('Unit Sama', $departments)['status'])->toBe(DepartmentResolver::STATUS_AMBIGUOUS)
        ->and($resolver->resolve('Unit Baru (LAB)', $departments)['status'])->toBe(DepartmentResolver::STATUS_AMBIGUOUS);
});

it('ignores inactive departments when status or is_active attribute is present', function () {
    $resolver = new DepartmentResolver;
    $departments = new Collection([
        makeFixtureDepartment(1, 'UPT-LAMA', 'UPT Lama', ['is_active' => false]),
        makeFixtureDepartment(2, 'UPT-BARU', 'UPT Baru', ['status' => 'inactive']),
        makeFixtureDepartment(3, 'UPT-AKTIF', 'UPT Aktif', ['is_active' => true]),
    ]);

    expect($resolver->resolve('UPT-LAMA', $departments)['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND)
        ->and($resolver->resolve('UPT Baru', $departments)['status'])->toBe(DepartmentResolver::STATUS_NOT_FOUND)
        ->and($resolver->resolve('UPT-AKTIF', $departments)['department']->id)->toBe(3);
});
