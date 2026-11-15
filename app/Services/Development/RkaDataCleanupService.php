<?php

namespace App\Services\Development;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Service khusus development untuk membersihkan seluruh data operasional RKA secara aman.
 */
class RkaDataCleanupService
{
    public const CONFIRMATION_PHRASE = 'CLEAN RKA DATA';

    /**
     * @var array<int, string>
     */
    public const ALLOWED_ENVIRONMENTS = ['local', 'development'];

    /**
     * Memeriksa apakah fitur cleanup diizinkan untuk environment & user saat ini.
     */
    public function isAvailable(?User $user = null): bool
    {
        $resolvedUser = $user ?? auth()->user();

        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            return false;
        }

        return $resolvedUser instanceof User && $resolvedUser->hasRole('super_admin');
    }

    /**
     * Memastikan environment aplikasi adalah local atau development.
     *
     * @throws RuntimeException
     */
    public function ensureAllowedEnvironment(): void
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw new RuntimeException(
                'Development Data Cleanup hanya diizinkan pada environment local atau development.'
            );
        }
    }

    /**
     * Memastikan user pelaksana memiliki role super_admin.
     *
     * @throws AuthorizationException
     */
    public function ensureSuperAdmin(?User $user = null): User
    {
        $resolvedUser = $user ?? auth()->user();

        if (! $resolvedUser instanceof User || ! $resolvedUser->hasRole('super_admin')) {
            throw new AuthorizationException(
                'Hanya pengguna dengan role super_admin yang diizinkan menjalankan cleanup RKA.'
            );
        }

        return $resolvedUser;
    }

    /**
     * Memvalidasi koneksi database agar tidak menyentuh database production.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function ensureSafeDatabase(): array
    {
        $info = $this->getDatabaseInformation();

        if ($info['database'] === '' || $info['database'] === null) {
            throw new RuntimeException('Nama database aktif tidak dapat diidentifikasi.');
        }

        if (! $info['is_safe_database']) {
            throw new RuntimeException(
                "Cleanup ditolak oleh database safety guard untuk database '{$info['database']}'."
            );
        }

        return $info;
    }

    /**
     * Mengambil metadata koneksi database aktif tanpa credential sensitif.
     *
     * @return array<string, mixed>
     */
    public function getDatabaseInformation(): array
    {
        $connection = (string) config('database.default');
        $database = (string) (config("database.connections.{$connection}.database") ?? '');
        $host = (string) (config("database.connections.{$connection}.host") ?? '');

        $isProdDb = preg_match('/(^|[_-])(prod|production)([_-]|$)/i', $database) === 1
            || str_contains(strtolower($database), 'production');
        $isProdHost = $host !== '' && (
            preg_match('/(^|[.\-_])(prod|production)([.\-_]|$)/i', $host) === 1
            || str_contains(strtolower($host), 'production')
        );

        return [
            'environment' => (string) app()->environment(),
            'connection' => $connection,
            'database' => $database,
            'host' => $host !== '' ? $host : 'local',
            'is_allowed_environment' => app()->environment(self::ALLOWED_ENVIRONMENTS),
            'is_safe_database' => $database !== '' && ! $isProdDb && ! $isProdHost,
        ];
    }

    /**
     * Mengambil rencana tabel yang akan dibersihkan dan tabel yang dipertahankan.
     *
     * @return array<string, mixed>
     */
    public function getCleanupPlan(): array
    {
        $syncedBudgetCount = $this->countRkaSyncedBudgets();
        $totalBudgets = $this->safeCount('department_budgets');

        $deleteTargets = [
            [
                'table' => 'rka_logs',
                'label' => 'RKA Log',
                'category' => 'Activity History',
                'count' => $this->safeCount('rka_logs'),
                'reason' => 'Seluruh riwayat aktivitas RKA Header & turunannya',
            ],
            [
                'table' => 'rka_item_reviews',
                'label' => 'RKA Item Review (Legacy)',
                'category' => 'Legacy Review',
                'count' => $this->safeCount('rka_item_reviews'),
                'reason' => 'Data review item lama yang terikat ke rka_items',
            ],
            [
                'table' => 'rka_adjustments',
                'label' => 'RKA Adjustment',
                'category' => 'Child',
                'count' => $this->safeCount('rka_adjustments'),
                'reason' => 'Penyesuaian Section A & Section B',
            ],
            [
                'table' => 'rka_items',
                'label' => 'RKA Item',
                'category' => 'Child',
                'count' => $this->safeCount('rka_items'),
                'reason' => 'Rincian item belanja RKA',
            ],
            [
                'table' => 'rka_indicators',
                'label' => 'RKA Indicator',
                'category' => 'Child',
                'count' => $this->safeCount('rka_indicators'),
                'reason' => 'Program & matriks indikator RKA',
            ],
            [
                'table' => 'rka_approvals',
                'label' => 'RKA Approval',
                'category' => 'History',
                'count' => $this->safeCount('rka_approvals'),
                'reason' => 'Histori persetujuan/revisi berjenjang',
            ],
            [
                'table' => 'department_budgets (RKA-synced)',
                'label' => 'Department Budget (Sinkronisasi RKA)',
                'category' => 'Synced Budget',
                'count' => $syncedBudgetCount,
                'reason' => 'Hanya alokasi dengan schedule_id yang cocok rka_headers; data manual/import dipertahankan',
            ],
            [
                'table' => 'rka_headers',
                'label' => 'RKA Header',
                'category' => 'Operational Root',
                'count' => $this->safeCount('rka_headers'),
                'reason' => 'Dokumen utama RKA per unit & jadwal',
            ],
        ];

        $preservedTables = [
            ['table' => 'users', 'label' => 'Users', 'count' => $this->safeCount('users')],
            ['table' => 'roles & permissions', 'label' => 'Roles & Permissions', 'count' => $this->safeCount('roles')],
            ['table' => 'departments', 'label' => 'Departments', 'count' => $this->safeCount('departments')],
            ['table' => 'accounts', 'label' => 'Accounts', 'count' => $this->safeCount('accounts')],
            ['table' => 'rka_settings', 'label' => 'RKA Settings', 'count' => $this->safeCount('rka_settings')],
            ['table' => 'rka_schedules', 'label' => 'RKA Schedules', 'count' => $this->safeCount('rka_schedules')],
            ['table' => 'rka_generate_configs', 'label' => 'RKA Generate Configs', 'count' => $this->safeCount('rka_generate_configs')],
            ['table' => 'department_budgets (Manual/Import)', 'label' => 'Department Budget Dipertahankan', 'count' => max(0, $totalBudgets - $syncedBudgetCount)],
            ['table' => 'fund_requests', 'label' => 'Fund Requests (Modul Lain)', 'count' => $this->safeCount('fund_requests')],
        ];

        return [
            'delete_targets' => $deleteTargets,
            'preserved_tables' => $preservedTables,
            'total_to_delete' => array_sum(array_column($deleteTargets, 'count')),
            'fund_request_count' => $this->safeCount('fund_requests'),
        ];
    }

    /**
     * Menghitung preview cleanup tanpa mengubah data di database.
     *
     * @return array<string, mixed>
     */
    public function preview(?User $user = null): array
    {
        $this->ensureAllowedEnvironment();
        $this->ensureSuperAdmin($user);
        $dbInfo = $this->ensureSafeDatabase();

        $plan = $this->getCleanupPlan();

        return [
            'database' => $dbInfo,
            'plan' => $plan,
            'total_to_delete' => $plan['total_to_delete'],
            'previewed_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Menjalankan pembersihan data operasional RKA secara transaksional (child-to-parent).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws AuthorizationException
     */
    public function execute(string $confirmationText, ?User $user = null): array
    {
        $this->ensureAllowedEnvironment();
        $actor = $this->ensureSuperAdmin($user);
        $dbInfo = $this->ensureSafeDatabase();

        if ($confirmationText !== self::CONFIRMATION_PHRASE) {
            throw new InvalidArgumentException(
                'Teks konfirmasi tidak sesuai. Ketik tepat: '.self::CONFIRMATION_PHRASE
            );
        }

        $beforePlan = $this->getCleanupPlan();
        $beforeCounts = [];
        foreach ($beforePlan['delete_targets'] as $target) {
            $beforeCounts[$target['table']] = $target['count'];
        }

        try {
            $deletedCounts = DB::transaction(function (): array {
                $deleted = [];

                $deleted['rka_logs'] = Schema::hasTable('rka_logs')
                    ? DB::table('rka_logs')->delete()
                    : 0;

                $deleted['rka_item_reviews'] = Schema::hasTable('rka_item_reviews')
                    ? DB::table('rka_item_reviews')->delete()
                    : 0;

                $deleted['rka_adjustments'] = Schema::hasTable('rka_adjustments')
                    ? DB::table('rka_adjustments')->delete()
                    : 0;

                $deleted['rka_items'] = Schema::hasTable('rka_items')
                    ? DB::table('rka_items')->delete()
                    : 0;

                $deleted['rka_indicators'] = Schema::hasTable('rka_indicators')
                    ? DB::table('rka_indicators')->delete()
                    : 0;

                $deleted['rka_approvals'] = Schema::hasTable('rka_approvals')
                    ? DB::table('rka_approvals')->delete()
                    : 0;

                $deleted['department_budgets (RKA-synced)'] = $this->deleteRkaSyncedBudgets();

                $deleted['rka_headers'] = Schema::hasTable('rka_headers')
                    ? DB::table('rka_headers')->delete()
                    : 0;

                return $deleted;
            });
        } catch (Throwable $e) {
            return [
                'status' => 'FAILED',
                'message' => $e->getMessage(),
                'executed_at' => now()->toDateTimeString(),
                'executed_by' => $actor->name,
                'environment' => $dbInfo['environment'],
                'database' => $dbInfo['database'],
                'before_counts' => $beforeCounts,
                'deleted_counts' => [],
                'total_deleted' => 0,
            ];
        }

        $afterPlan = $this->getCleanupPlan();
        $remainingOperational = $afterPlan['total_to_delete'];

        return [
            'status' => $remainingOperational === 0 ? 'SUCCESS' : 'PARTIAL',
            'executed_at' => now()->toDateTimeString(),
            'executed_by' => $actor->name,
            'environment' => $dbInfo['environment'],
            'database' => $dbInfo['database'],
            'before_counts' => $beforeCounts,
            'deleted_counts' => $deletedCounts,
            'total_deleted' => array_sum($deletedCounts),
            'remaining_operational_count' => $remainingOperational,
            'preserved_tables' => $afterPlan['preserved_tables'],
            'attachments_deleted' => 0,
        ];
    }

    /**
     * Menghitung jumlah baris department_budgets yang berasal dari rka_headers.
     */
    private function countRkaSyncedBudgets(): int
    {
        if (! Schema::hasTable('department_budgets') || ! Schema::hasTable('rka_headers')) {
            return 0;
        }

        return (int) DB::table('department_budgets')
            ->whereNotNull('schedule_id')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('rka_headers')
                    ->whereColumn('rka_headers.department_id', 'department_budgets.department_id')
                    ->whereColumn('rka_headers.schedule_id', 'department_budgets.schedule_id');
            })
            ->count();
    }

    /**
     * Menghapus baris department_budgets yang berasal dari rka_headers.
     */
    private function deleteRkaSyncedBudgets(): int
    {
        if (! Schema::hasTable('department_budgets') || ! Schema::hasTable('rka_headers')) {
            return 0;
        }

        return DB::table('department_budgets')
            ->whereNotNull('schedule_id')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('rka_headers')
                    ->whereColumn('rka_headers.department_id', 'department_budgets.department_id')
                    ->whereColumn('rka_headers.schedule_id', 'department_budgets.schedule_id');
            })
            ->delete();
    }

    private function safeCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }
}
