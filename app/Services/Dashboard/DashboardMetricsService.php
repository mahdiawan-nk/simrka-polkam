<?php

namespace App\Services\Dashboard;

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\FundRequestApproval;
use App\Models\RkaHeader;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\RkaSchedule;
use App\Models\User;
use App\Services\Context\Contracts\ContextManagerInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardMetricsService
{
    /**
     * Mengambil konteks aktif user (role, unit kerja, periode anggaran).
     *
     * @return array<string, mixed>
     */
    public function getActiveContext(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        /** @var ContextManagerInterface $contextManager */
        $contextManager = app(ContextManagerInterface::class);

        $activeDepartment = $contextManager->getActiveDepartment();
        $activeRole = method_exists($contextManager, 'getActiveRole')
            ? $contextManager->getActiveRole()
            : ($user?->roles->first()?->name ?? null);

        $activeSchedule = RkaSchedule::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        $fiscalYear = $activeSchedule?->fiscal_year ?? RkaSchedule::currentFiscalYear();

        return [
            'user' => $user,
            'active_role' => $activeRole,
            'active_department' => $activeDepartment,
            'active_schedule' => $activeSchedule,
            'fiscal_year' => $fiscalYear,
            'updated_at' => now(),
        ];
    }

    /**
     * Menentukan apakah user memiliki akses lintas unit berdasarkan role struktural atau permission eksplisit.
     */
    public function canViewAllUnits(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan'])
            || $user->can($permission);
    }

    /**
     * Ringkasan pagu anggaran, pencairan disetujui, pengajuan in-flight, dan sisa pagu.
     *
     * @return array<string, float>
     */
    public function getBudgetAndRealizationSummary(string $fiscalYear, ?int $departmentId = null): array
    {
        $pagu = (float) DepartmentBudget::query()
            ->where('fiscal_year', $fiscalYear)
            ->when($departmentId !== null, fn ($q) => $q->where('department_id', $departmentId))
            ->sum('allocated_amount');

        $fundTotals = FundRequest::query()
            ->where('fiscal_year', $fiscalYear)
            ->when($departmentId !== null, fn ($q) => $q->where('department_id', $departmentId))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'approved' THEN total_approved ELSE 0 END), 0) as approved_amount,
                COALESCE(SUM(CASE WHEN status IN ('submitted', 'verified_kabakku', 'verified_wadir_bakku') THEN total_requested ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN status != 'draft' THEN total_requested ELSE 0 END), 0) as submitted_requested_amount
            ")
            ->first();

        $approvedAmount = (float) ($fundTotals->approved_amount ?? 0);
        $pendingAmount = (float) ($fundTotals->pending_amount ?? 0);
        $submittedRequested = (float) ($fundTotals->submitted_requested_amount ?? 0);
        $remainingBudget = $pagu - ($approvedAmount + $pendingAmount);
        $absorptionRate = $pagu > 0 ? round(($approvedAmount / $pagu) * 100, 1) : 0.0;

        return [
            'pagu' => $pagu,
            'approved_amount' => $approvedAmount,
            'pending_amount' => $pendingAmount,
            'submitted_requested' => $submittedRequested,
            'remaining_budget' => $remainingBudget,
            'absorption_rate' => $absorptionRate,
        ];
    }

    /**
     * Ringkasan RKA (status header, total usulan, dan total disetujui final).
     *
     * @return array<string, mixed>
     */
    public function getRkaSummary(?RkaSchedule $schedule, ?int $departmentId = null): array
    {
        $headerQuery = RkaHeader::query()
            ->when($schedule !== null, fn ($q) => $q->where('schedule_id', $schedule->id))
            ->when($departmentId !== null, fn ($q) => $q->where('department_id', $departmentId));

        $statusCounts = (clone $headerQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $headerIds = (clone $headerQuery)->pluck('id');

        $totalProposed = $headerIds->isEmpty()
            ? 0.0
            : (float) RkaItem::query()
                ->whereIn('rka_header_id', $headerIds)
                ->sum('proposed_total');

        $approvedHeaderIds = (clone $headerQuery)
            ->where('status', RkaHeader::STATUS_APPROVED)
            ->pluck('id');

        $totalApproved = $approvedHeaderIds->isEmpty()
            ? 0.0
            : (float) RkaItem::query()
                ->whereIn('rka_header_id', $approvedHeaderIds)
                ->sum('approved_total');

        return [
            'total_headers' => (int) $statusCounts->sum(),
            'draft' => (int) ($statusCounts[RkaHeader::STATUS_DRAFT] ?? 0),
            'submitted' => (int) ($statusCounts[RkaHeader::STATUS_SUBMITTED] ?? 0),
            'in_review' => (int) ($statusCounts[RkaHeader::STATUS_IN_REVIEW] ?? 0),
            'revision_required' => (int) ($statusCounts[RkaHeader::STATUS_REVISION_REQUIRED] ?? 0),
            'approved' => (int) ($statusCounts[RkaHeader::STATUS_APPROVED] ?? 0),
            'total_proposed' => $totalProposed,
            'total_approved' => $totalApproved,
        ];
    }

    /**
     * Agregasi pagu, realisasi approved, pending, dan sisa pagu per departemen (tanpa N+1 query).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getDepartmentComparison(string $fiscalYear): Collection
    {
        $budgetsByDept = DepartmentBudget::query()
            ->where('fiscal_year', $fiscalYear)
            ->selectRaw('department_id, COALESCE(SUM(allocated_amount), 0) as pagu')
            ->groupBy('department_id')
            ->pluck('pagu', 'department_id');

        if ($budgetsByDept->isEmpty()) {
            return collect();
        }

        $fundsByDept = FundRequest::query()
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('department_id', $budgetsByDept->keys())
            ->selectRaw("
                department_id,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN total_approved ELSE 0 END), 0) as approved,
                COALESCE(SUM(CASE WHEN status IN ('submitted', 'verified_kabakku', 'verified_wadir_bakku') THEN total_requested ELSE 0 END), 0) as pending
            ")
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        $departments = Department::query()
            ->whereIn('id', $budgetsByDept->keys())
            ->orderBy('name')
            ->get();

        return $departments->map(function (Department $dept) use ($budgetsByDept, $fundsByDept): array {
            $pagu = (float) ($budgetsByDept[$dept->id] ?? 0);
            $fundRow = $fundsByDept->get($dept->id);
            $approved = (float) ($fundRow->approved ?? 0);
            $pending = (float) ($fundRow->pending ?? 0);
            $remaining = $pagu - ($approved + $pending);
            $percentage = $pagu > 0 ? round(($approved / $pagu) * 100, 1) : 0.0;

            return [
                'id' => $dept->id,
                'code' => $dept->code,
                'name' => $dept->name,
                'pagu' => $pagu,
                'approved' => $approved,
                'pending' => $pending,
                'remaining' => $remaining,
                'percentage' => $percentage,
            ];
        });
    }

    /**
     * Komparasi pagu vs realisasi lintas tahun anggaran (tanpa N+1 query).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getFiscalYearComparison(): Collection
    {
        $budgetsByYear = DepartmentBudget::query()
            ->selectRaw('fiscal_year, COALESCE(SUM(allocated_amount), 0) as pagu')
            ->groupBy('fiscal_year')
            ->orderBy('fiscal_year')
            ->pluck('pagu', 'fiscal_year');

        $approvedByYear = FundRequest::query()
            ->where('status', 'approved')
            ->selectRaw('fiscal_year, COALESCE(SUM(total_approved), 0) as approved')
            ->groupBy('fiscal_year')
            ->pluck('approved', 'fiscal_year');

        return $budgetsByYear->keys()
            ->merge($approvedByYear->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $fy): array => [
                'fiscal_year' => $fy,
                'pagu' => (float) ($budgetsByYear[$fy] ?? 0),
                'approved' => (float) ($approvedByYear[$fy] ?? 0),
            ]);
    }

    /**
     * Mengambil aktivitas terbaru lintas modul (RKA Logs & Fund Request Approvals/Submissions)
     * tanpa duplikasi event.
     *
     * @return array<int, array<string, string>>
     */
    public function getRecentSystemActivities(int $limit = 12, ?int $departmentId = null): array
    {
        $rkaLogs = RkaLog::query()
            ->with(['user:id,name', 'rkaHeader:id,department_id', 'rkaHeader.department:id,name'])
            ->when($departmentId !== null, function ($q) use ($departmentId) {
                $q->whereHas('rkaHeader', fn ($sub) => $sub->where('department_id', $departmentId));
            })
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (RkaLog $log): array => [
                'timestamp' => $log->created_at?->toIso8601String() ?? '',
                'time_formatted' => $log->created_at?->format('d/m/Y H:i') ?? '-',
                'module' => 'RKA',
                'actor' => $log->user?->name ?? 'Sistem',
                'department' => $log->rkaHeader?->department?->name ?? '-',
                'activity' => $log->action,
                'description' => $log->description ?: '-',
            ]);

        $approvals = FundRequestApproval::query()
            ->with(['user:id,name', 'fundRequest:id,document_number,department_id', 'fundRequest.department:id,name'])
            ->when($departmentId !== null, function ($q) use ($departmentId) {
                $q->whereHas('fundRequest', fn ($sub) => $sub->where('department_id', $departmentId));
            })
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (FundRequestApproval $approval): array {
                $stageLabel = match ($approval->stage) {
                    'kabakku' => 'Verifikasi Keuangan',
                    'wadir_bakku' => 'Persetujuan Wadir II',
                    'director' => 'Pengesahan Direktur',
                    default => ucfirst((string) $approval->stage),
                };

                return [
                    'timestamp' => $approval->created_at?->toIso8601String() ?? '',
                    'time_formatted' => $approval->created_at?->format('d/m/Y H:i') ?? '-',
                    'module' => 'Fund Request',
                    'actor' => $approval->user?->name ?? 'Sistem',
                    'department' => $approval->fundRequest?->department?->name ?? '-',
                    'activity' => "{$stageLabel} ({$approval->action})",
                    'description' => ($approval->fundRequest?->document_number ?? '-').($approval->note ? " — {$approval->note}" : ''),
                ];
            });

        return $rkaLogs
            ->concat($approvals)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values()
            ->all();
    }
}
