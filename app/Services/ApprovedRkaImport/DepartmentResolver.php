<?php

declare(strict_types=1);

namespace App\Services\ApprovedRkaImport;

use App\Models\Department;
use Illuminate\Support\Collection;

/**
 * Resolver deterministik berbasis master tabel departments (single source of truth).
 */
class DepartmentResolver
{
    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_NOT_FOUND = 'NOT_FOUND';

    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';

    public const STATUS_INVALID_INPUT = 'INVALID_INPUT';

    /**
     * Alias resmi terverifikasi untuk variasi nama unit pada dokumen Excel
     * yang berbeda dari departments.name maupun departments.code.
     * Setiap nilai target wajib merujuk ke departments.code yang valid.
     *
     * @var array<string, string>
     */
    public const UNIT_ALIASES = [
        'upt penelitian dan pengabdian kepada masyarakat (p3m)' => 'P3M',
        'prodi d3 teknik informatika (tif)' => 'PRODI-TIF',
        'prodi d4 administrasi bisnis internasional (abi)' => 'PRODI-ABI',
        'prodi d4 abi' => 'PRODI-ABI',
        'prodi abi' => 'PRODI-ABI',
        'bagian umum' => 'BAG-UMUM',
        'umum' => 'BAG-UMUM',
        'bagian keuangan' => 'BAKKU',
        'keuangan' => 'BAKKU',
        'upt pusat karir' => 'UPT-PPKPK',
    ];

    /**
     * Memetakan nama atau kode unit mentah ke satu Department aktif yang pasti.
     *
     * Prioritas pencocokan:
     * 1. Exact match terhadap departments.name setelah normalisasi
     * 2. Exact match terhadap departments.code setelah normalisasi
     * 3. Exact match terhadap alias resmi terverifikasi (UNIT_ALIASES)
     * 4. Normalized match terhadap nama unit (penghapusan token jenjang / singkatan dalam kurung yang unik)
     * 5. Tidak ditemukan (NOT_FOUND) / Ambigu (AMBIGUOUS) / Input tidak valid (INVALID_INPUT)
     *
     * @param  Collection<int, Department>  $departments
     * @return array{status: string, department: ?Department, reason: ?string}
     */
    public function resolve(?string $rawName, Collection $departments): array
    {
        $trimmed = trim((string) $rawName);
        if ($trimmed === '' || $trimmed === '-' || preg_match('/[a-z0-9]/i', $trimmed) !== 1) {
            return [
                'status' => self::STATUS_INVALID_INPUT,
                'department' => null,
                'reason' => 'Nama unit kosong atau tidak valid.',
            ];
        }

        $activeDepartments = $departments->filter(fn (Department $dept): bool => $this->isDepartmentActive($dept))->values();

        // 1. Exact match terhadap departments.name setelah normalisasi
        $normalizedInputName = $this->normalizeName($trimmed);
        $nameMatches = $activeDepartments->filter(
            fn (Department $dept): bool => $this->normalizeName((string) $dept->name) === $normalizedInputName
        )->values();

        if ($nameMatches->count() === 1) {
            return [
                'status' => self::STATUS_RESOLVED,
                'department' => $nameMatches->first(),
                'reason' => null,
            ];
        }

        if ($nameMatches->count() > 1) {
            return [
                'status' => self::STATUS_AMBIGUOUS,
                'department' => null,
                'reason' => "Nama unit '{$trimmed}' cocok dengan lebih dari satu departemen.",
            ];
        }

        // 2. Exact match terhadap departments.code setelah normalisasi
        $inputCodes = $this->candidateNormalizedCodes($trimmed);
        $codeMatches = $activeDepartments->filter(function (Department $dept) use ($inputCodes): bool {
            $deptCode = $this->normalizeCode((string) $dept->code);

            return $deptCode !== '' && in_array($deptCode, $inputCodes, true);
        })->values();

        if ($codeMatches->count() === 1) {
            return [
                'status' => self::STATUS_RESOLVED,
                'department' => $codeMatches->first(),
                'reason' => null,
            ];
        }

        if ($codeMatches->count() > 1) {
            return [
                'status' => self::STATUS_AMBIGUOUS,
                'department' => null,
                'reason' => "Kode unit '{$trimmed}' cocok dengan lebih dari satu departemen.",
            ];
        }

        // 3. Exact match terhadap alias resmi terverifikasi (UNIT_ALIASES)
        if (array_key_exists($normalizedInputName, self::UNIT_ALIASES)) {
            $aliasCode = $this->normalizeCode(self::UNIT_ALIASES[$normalizedInputName]);
            $aliasMatches = $activeDepartments->filter(
                fn (Department $dept): bool => $this->normalizeCode((string) $dept->code) === $aliasCode
            )->values();

            if ($aliasMatches->count() === 1) {
                return [
                    'status' => self::STATUS_RESOLVED,
                    'department' => $aliasMatches->first(),
                    'reason' => null,
                ];
            }

            if ($aliasMatches->count() > 1) {
                return [
                    'status' => self::STATUS_AMBIGUOUS,
                    'department' => null,
                    'reason' => "Alias unit '{$trimmed}' cocok dengan lebih dari satu departemen.",
                ];
            }
        }

        // 4. Normalized match terhadap nama unit (mis. menghapus token jenjang D3/D4 atau singkatan dalam tanda kurung)
        $strippedDegreeInput = $this->stripDegreeTokens($normalizedInputName);
        if ($strippedDegreeInput !== $normalizedInputName) {
            $degreeMatches = $activeDepartments->filter(
                fn (Department $dept): bool => $this->stripDegreeTokens($this->normalizeName((string) $dept->name)) === $strippedDegreeInput
            )->values();

            if ($degreeMatches->count() === 1) {
                return [
                    'status' => self::STATUS_RESOLVED,
                    'department' => $degreeMatches->first(),
                    'reason' => null,
                ];
            }

            if ($degreeMatches->count() > 1) {
                return [
                    'status' => self::STATUS_AMBIGUOUS,
                    'department' => null,
                    'reason' => "Nama unit '{$trimmed}' cocok dengan lebih dari satu departemen.",
                ];
            }
        }

        if (preg_match_all('/\(([A-Za-z0-9\-\s]+)\)/', $trimmed, $matches) && ! empty($matches[1])) {
            $abbreviations = array_values(array_unique(array_filter(array_map(
                fn (string $abbr): string => $this->normalizeCode($abbr),
                $matches[1]
            ))));

            $abbrMatches = $activeDepartments->filter(function (Department $dept) use ($abbreviations): bool {
                $dbCode = $this->normalizeCode((string) $dept->code);
                $dbName = $this->normalizeName((string) $dept->name);

                foreach ($abbreviations as $abbr) {
                    if ($dbCode === $abbr
                        || str_ends_with($dbCode, '-'.$abbr)
                        || str_contains($dbName, "({$abbr})")) {
                        return true;
                    }
                }

                return false;
            })->values();

            if ($abbrMatches->count() === 1) {
                return [
                    'status' => self::STATUS_RESOLVED,
                    'department' => $abbrMatches->first(),
                    'reason' => null,
                ];
            }

            if ($abbrMatches->count() > 1) {
                return [
                    'status' => self::STATUS_AMBIGUOUS,
                    'department' => null,
                    'reason' => "Singkatan dalam tanda kurung pada '{$trimmed}' cocok dengan lebih dari satu departemen.",
                ];
            }
        }

        return [
            'status' => self::STATUS_NOT_FOUND,
            'department' => null,
            'reason' => "Unit '{$trimmed}' tidak ditemukan pada master Departemen.",
        ];
    }

    public function normalizeCode(string $value): string
    {
        $clean = mb_strtolower(trim($value));
        $clean = str_replace(['–', '—', '_', '.'], '-', $clean);
        $clean = (string) preg_replace('/[^a-z0-9\-\s]/u', ' ', $clean);
        $clean = (string) preg_replace('/\s+/', '-', trim($clean));
        $clean = (string) preg_replace('/-+/', '-', $clean);

        return trim($clean, '-');
    }

    public function normalizeName(string $value): string
    {
        $clean = mb_strtolower(trim($value));
        $clean = str_replace(['–', '—'], '-', $clean);
        $clean = (string) preg_replace('/\bbag\.\s*/u', 'bagian ', $clean);
        $clean = (string) preg_replace('/[.,;]+(?=\s|$)/u', '', $clean);
        $clean = (string) preg_replace('/\s*-\s*/u', '-', $clean);
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);

        return trim($clean);
    }

    private function stripDegreeTokens(string $normalizedName): string
    {
        $stripped = (string) preg_replace('/\b(?:d[1-4]|s[1-3])\b/u', '', $normalizedName);
        $stripped = (string) preg_replace('/\s+/u', ' ', $stripped);

        return trim($stripped);
    }

    /**
     * Menghasilkan kandidat kode ternormalisasi termasuk penghapusan token jenjang (D1-D4/S1-S2) untuk prodi.
     *
     * @return array<int, string>
     */
    private function candidateNormalizedCodes(string $value): array
    {
        $primary = $this->normalizeCode($value);
        $withoutDegree = (string) preg_replace('/-(d[1-4]|s[1-3])-/u', '-', $primary);
        $withoutDegree = trim((string) preg_replace('/-+/', '-', $withoutDegree), '-');

        return array_values(array_unique(array_filter([$primary, $withoutDegree])));
    }

    private function isDepartmentActive(Department $department): bool
    {
        $attributes = $department->getAttributes();

        if (array_key_exists('is_active', $attributes) && ! (bool) $attributes['is_active']) {
            return false;
        }

        if (array_key_exists('status', $attributes) && in_array(mb_strtolower((string) $attributes['status']), ['inactive', 'nonaktif', 'disabled'], true)) {
            return false;
        }

        return true;
    }
}
