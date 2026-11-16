<?php

namespace App\Services\FundRequest;

use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\FundRequestItem;
use App\Models\RkaHeader;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\User;
use App\Services\Context\Contracts\ContextManagerInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service terpusat untuk resolusi sumber RKA Approved, perhitungan saldo item & rekening,
 * validasi kontrol anggaran dua tingkat, dan penyimpanan transaksional Fund Request.
 */
class FundRequestBudgetService
{
    /**
     * Status RkaItem yang sah dijadikan sumber Fund Request.
     *
     * @var array<int, string>
     */
    public const ELIGIBLE_RKA_ITEM_STATUSES = [
        RkaItem::STATUS_APPROVED,
        RkaItem::STATUS_ACCEPTED,
        RkaItem::STATUS_REDUCED,
        'TRIMMED',
    ];

    /**
     * Status FundRequest yang masih menahan atau mengurangi saldo anggaran.
     * Status 'rejected' dikecualikan (melepaskan saldo).
     *
     * @var array<int, string>
     */
    public const ACTIVE_BALANCE_STATUSES = [
        'draft',
        'submitted',
        'verified_kabakku',
        'verified_wadir_bakku',
        'approved',
    ];

    /**
     * Menentukan jadwal RKA aktif tunggal secara deterministik (tanpa fallback ke periode lama).
     *
     * @throws InvalidArgumentException
     */
    public function resolveActiveSchedule(): RkaSchedule
    {
        $activeSchedules = RkaSchedule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($activeSchedules->isEmpty()) {
            throw new InvalidArgumentException(
                'Tidak ada periode anggaran RKA yang sedang aktif. Pengajuan dana tidak dapat dibuat.'
            );
        }

        if ($activeSchedules->count() > 1) {
            throw new InvalidArgumentException(
                'Terdapat lebih dari satu periode anggaran RKA yang aktif secara bersamaan sehingga periode anggaran ambigu. Hubungi tim keuangan.'
            );
        }

        return $activeSchedules->first();
    }

    /**
     * Menentukan departemen aktif pemohon & memvalidasi akses organisasional.
     *
     * @throws InvalidArgumentException
     */
    public function resolveAuthorizedDepartment(User $user, ?int $requestedDepartmentId = null): Department
    {
        /** @var ContextManagerInterface $context */
        $context = app(ContextManagerInterface::class);
        $activeDept = $context->getActiveDepartment();

        if ($activeDept !== null) {
            return $activeDept;
        }

        $isPrivileged = $user->hasAnyRole(['super_admin', 'direktur', 'keuangan', 'wadir_keuangan']);

        if ($requestedDepartmentId !== null) {
            $dept = Department::find($requestedDepartmentId);
            if (! $dept) {
                throw new InvalidArgumentException('Unit/departemen yang dipilih tidak ditemukan.');
            }

            if (! $isPrivileged && ! $user->departments()->where('departments.id', $dept->id)->exists()) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke unit/departemen tersebut.');
            }

            return $dept;
        }

        $userDept = $user->departments()->first();
        if (! $userDept) {
            throw new InvalidArgumentException('Akun Anda belum terhubung dengan unit/departemen aktif.');
        }

        return $userDept;
    }

    /**
     * Menentukan RKA Approved untuk unit dan periode aktif.
     *
     * @return array{schedule: RkaSchedule, department: Department, rka_header: RkaHeader}
     *
     * @throws InvalidArgumentException
     */
    public function resolveApprovedRkaContext(User $user, ?int $requestedDepartmentId = null): array
    {
        $schedule = $this->resolveActiveSchedule();
        $department = $this->resolveAuthorizedDepartment($user, $requestedDepartmentId);

        $rkaHeader = RkaHeader::query()
            ->with(['department', 'schedule'])
            ->where('schedule_id', $schedule->id)
            ->where('department_id', $department->id)
            ->where('status', RkaHeader::STATUS_APPROVED)
            ->first();

        if (! $rkaHeader) {
            throw new InvalidArgumentException(
                'Belum tersedia RKA yang telah disahkan untuk unit Anda pada periode anggaran aktif. Silakan hubungi tim keuangan.'
            );
        }

        return [
            'schedule' => $schedule,
            'department' => $department,
            'rka_header' => $rkaHeader,
        ];
    }

    /**
     * Mengambil daftar RkaItem yang dapat dipilih beserta sisa anggaran masing-masing.
     *
     * @param  array{search?: string, form_type?: string, account_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getSelectableRkaItems(
        RkaHeader $rkaHeader,
        ?int $excludeFundRequestId = null,
        array $filters = [],
        bool $onlyWithRemainingBalance = true,
    ): Collection {
        $query = RkaItem::query()
            ->with(['account', 'indicator'])
            ->where('rka_header_id', $rkaHeader->id)
            ->whereIn('status', self::ELIGIBLE_RKA_ITEM_STATUSES)
            ->where('approved_total', '>', 0)
            ->whereNotNull('account_id')
            ->where(function ($q): void {
                // Item dengan volume disetujui > 0, ATAU item APPROVED dengan approved_volume NULL (disetujui sesuai proposed_volume)
                $q->where('approved_volume', '>', 0)
                    ->orWhere(function ($fallback): void {
                        $fallback->whereNull('approved_volume')
                            ->where('proposed_volume', '>', 0);
                    });
            })
            ->orderBy('account_id')
            ->orderBy('id');

        if (! empty($filters['form_type'])) {
            $query->where('form_type', $filters['form_type']);
        }

        if (! empty($filters['account_id'])) {
            $query->where('account_id', (int) $filters['account_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('account', function ($aq) use ($search): void {
                        $aq->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('indicator', function ($iq) use ($search): void {
                        $iq->where('program_name', 'like', "%{$search}%")
                            ->orWhere('activity_name', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            return collect();
        }

        $usedByItem = $this->getUsedAmountByRkaItemIds(
            $items->pluck('id')->all(),
            $excludeFundRequestId
        );

        $mapped = $items->map(function (RkaItem $item) use ($usedByItem): array {
            $approvedTotal = (float) $item->approved_total;
            $approvedVol = $this->resolveEffectiveApprovedVolume($item);
            $usedCents = $this->toCents($usedByItem[$item->id] ?? 0.0);
            $approvedCents = $this->toCents($approvedTotal);
            $remainingCents = max(0, $approvedCents - $usedCents);
            $unitPrice = $approvedVol > 0 ? round($approvedTotal / $approvedVol, 2) : 0.0;

            return [
                'rka_item_id' => $item->id,
                'account_id' => $item->account_id,
                'account_code' => $item->account?->code ?? '-',
                'account_name' => $item->account?->name ?? '-',
                'form_type' => $item->form_type,
                'program_activity' => trim(($item->indicator?->program_name ?? '').' - '.($item->indicator?->activity_name ?? ''), ' -'),
                'description' => $item->description,
                'unit' => $item->unit_symbol ?: 'Unit',
                'approved_volume' => $approvedVol,
                'approved_unit_price' => $unitPrice,
                'approved_total' => $approvedTotal,
                'used_amount' => $this->fromCents($usedCents),
                'remaining_amount' => $this->fromCents($remainingCents),
                'status' => $item->status,
            ];
        });

        if ($onlyWithRemainingBalance) {
            return $mapped->filter(fn (array $row): bool => $row['remaining_amount'] > 0)->values();
        }

        return $mapped->values();
    }

    /**
     * Menghitung total penggunaan anggaran per rka_item_id dari Fund Request aktif.
     *
     * @param  array<int, int>  $rkaItemIds
     * @return array<int, float>
     */
    public function getUsedAmountByRkaItemIds(array $rkaItemIds, ?int $excludeFundRequestId = null): array
    {
        if ($rkaItemIds === []) {
            return [];
        }

        $items = FundRequestItem::query()
            ->whereIn('rka_item_id', $rkaItemIds)
            ->whereHas('fundRequest', function ($q) use ($excludeFundRequestId): void {
                $q->whereIn('status', self::ACTIVE_BALANCE_STATUSES);
                if ($excludeFundRequestId !== null) {
                    $q->where('id', '!=', $excludeFundRequestId);
                }
            })
            ->get(['rka_item_id', 'total_price', 'approved_price', 'verification_status']);

        $totalsCents = [];
        foreach ($items as $item) {
            $id = (int) $item->rka_item_id;
            $totalsCents[$id] = ($totalsCents[$id] ?? 0) + $this->effectiveLineCents($item);
        }

        $result = [];
        foreach ($totalsCents as $id => $cents) {
            $result[$id] = $this->fromCents($cents);
        }

        return $result;
    }

    /**
     * Menghitung total penggunaan anggaran per account_id pada unit & tahun anggaran aktif.
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, float>
     */
    public function getUsedAmountByAccountIds(
        int $departmentId,
        string $fiscalYear,
        array $accountIds,
        ?int $excludeFundRequestId = null,
    ): array {
        if ($accountIds === []) {
            return [];
        }

        $items = FundRequestItem::query()
            ->whereIn('account_id', $accountIds)
            ->whereHas('fundRequest', function ($q) use ($departmentId, $fiscalYear, $excludeFundRequestId): void {
                $q->where('department_id', $departmentId)
                    ->where('fiscal_year', $fiscalYear)
                    ->whereIn('status', self::ACTIVE_BALANCE_STATUSES);
                if ($excludeFundRequestId !== null) {
                    $q->where('id', '!=', $excludeFundRequestId);
                }
            })
            ->get(['account_id', 'total_price', 'approved_price', 'verification_status']);

        $totalsCents = [];
        foreach ($items as $item) {
            $accId = (int) $item->account_id;
            $totalsCents[$accId] = ($totalsCents[$accId] ?? 0) + $this->effectiveLineCents($item);
        }

        $result = [];
        foreach ($totalsCents as $accId => $cents) {
            $result[$accId] = $this->fromCents($cents);
        }

        return $result;
    }

    /**
     * Menghitung Rekap per Kode Akun (Bagian C) secara otomatis berdasarkan item terpilih.
     *
     * @param  array<int, array<string, mixed>>  $selectedRows
     * @return array<int, array<string, mixed>>
     */
    public function buildAccountSummaries(
        ?RkaHeader $rkaHeader,
        array $selectedRows,
        ?int $excludeFundRequestId = null,
    ): array {
        $validRows = collect($selectedRows)->filter(fn ($r): bool => ! empty($r['rka_item_id']) && ! empty($r['account_id']));
        if ($validRows->isEmpty() || ! $rkaHeader) {
            return [];
        }

        $accountIds = $validRows->pluck('account_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        $accounts = Account::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        $budgets = DepartmentBudget::query()
            ->where('department_id', $rkaHeader->department_id)
            ->where('fiscal_year', $rkaHeader->schedule?->fiscal_year ?? '')
            ->whereIn('account_id', $accountIds)
            ->get()
            ->keyBy('account_id');

        $usedByAccount = $this->getUsedAmountByAccountIds(
            $rkaHeader->department_id,
            $rkaHeader->schedule?->fiscal_year ?? '',
            $accountIds,
            $excludeFundRequestId
        );

        $rkaItems = RkaItem::query()
            ->where('rka_header_id', $rkaHeader->id)
            ->whereIn('id', $validRows->pluck('rka_item_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $usedByItem = $this->getUsedAmountByRkaItemIds(
            $rkaItems->keys()->all(),
            $excludeFundRequestId
        );

        $grouped = [];
        foreach ($validRows as $row) {
            $accId = (int) $row['account_id'];
            $rkaItemId = (int) $row['rka_item_id'];
            $lineCents = $this->toCents((float) ($row['total_price'] ?? 0));

            if (! isset($grouped[$accId])) {
                $acc = $accounts->get($accId);
                $paguUnit = (float) ($budgets->get($accId)?->allocated_amount ?? 0.0);
                $usedOther = (float) ($usedByAccount[$accId] ?? 0.0);
                $remainingAccount = max(0.0, $this->fromCents($this->toCents($paguUnit) - $this->toCents($usedOther)));

                $grouped[$accId] = [
                    'account_id' => $accId,
                    'account_code' => $acc?->code ?? '-',
                    'account_name' => $acc?->name ?? '-',
                    'note' => $acc?->managed_by_note ?? '',
                    'total_requested_cents' => 0,
                    'pagu_unit' => $paguUnit,
                    'used_other' => $usedOther,
                    'remaining_before_request' => $remainingAccount,
                    'has_invalid_item' => false,
                    'has_item_over_budget' => false,
                ];
            }

            $grouped[$accId]['total_requested_cents'] += $lineCents;

            $rkaItem = $rkaItems->get($rkaItemId);
            if (! $rkaItem || ! in_array($rkaItem->status, self::ELIGIBLE_RKA_ITEM_STATUSES, true) || (float) $rkaItem->approved_total <= 0) {
                $grouped[$accId]['has_invalid_item'] = true;
            } else {
                $itemRemCents = max(0, $this->toCents((float) $rkaItem->approved_total) - $this->toCents($usedByItem[$rkaItemId] ?? 0.0));
                if ($lineCents > $itemRemCents) {
                    $grouped[$accId]['has_item_over_budget'] = true;
                }
            }
        }

        return collect($grouped)->map(function (array $g): array {
            $totalRequested = $this->fromCents($g['total_requested_cents']);
            $sisaSetelahPengajuan = $this->fromCents($this->toCents($g['remaining_before_request']) - $g['total_requested_cents']);
            $isOverAccount = $g['total_requested_cents'] > $this->toCents($g['remaining_before_request']);

            $status = match (true) {
                $g['has_invalid_item'] => 'ITEM TIDAK TERSEDIA',
                $g['has_item_over_budget'] => 'MELEBIHI PAGU ITEM',
                $isOverAccount => 'MELEBIHI PAGU REKENING',
                default => 'VALID',
            };

            return [
                'account_id' => $g['account_id'],
                'account_code' => $g['account_code'],
                'account_name' => $g['account_name'],
                'note' => $g['note'],
                'total_requested' => $totalRequested,
                'pagu_unit' => $g['pagu_unit'],
                'used_other' => $g['used_other'],
                'remaining_before_request' => $g['remaining_before_request'],
                'remaining_after_request' => $sisaSetelahPengajuan,
                'status' => $status,
            ];
        })->values()->all();
    }

    /**
     * Membuat Fund Request baru secara transaksional dengan locking & validasi dua tingkat di server.
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $rawItems
     *
     * @throws InvalidArgumentException
     */
    public function createFundRequest(User $user, array $headerData, array $rawItems): FundRequest
    {
        return DB::transaction(function () use ($user, $headerData, $rawItems): FundRequest {
            $ctx = $this->resolveApprovedRkaContext($user, isset($headerData['department_id']) ? (int) $headerData['department_id'] : null);
            $rkaHeader = $ctx['rka_header'];
            $schedule = $ctx['schedule'];
            $department = $ctx['department'];

            $validatedLines = $this->validateAndLockBudgetLines(
                rkaHeader: $rkaHeader,
                department: $department,
                fiscalYear: $schedule->fiscal_year,
                rawItems: $rawItems,
                excludeFundRequestId: null,
            );

            $totalRequestedCents = array_sum(array_column($validatedLines, 'total_price_cents'));

            $fundRequest = FundRequest::create([
                'document_number' => $headerData['document_number'],
                'request_date' => $headerData['request_date'],
                'fiscal_year' => $schedule->fiscal_year,
                'department_id' => $department->id,
                'rka_header_id' => $rkaHeader->id,
                'activity_name' => $headerData['activity_name'],
                'execution_date' => ! empty($headerData['execution_date']) ? $headerData['execution_date'] : null,
                'responsible_person' => $headerData['responsible_person'],
                'disbursement_type' => $headerData['disbursement_type'],
                'recipient_name' => $headerData['recipient_name'],
                'bank_account_info' => $headerData['bank_account_info'] ?? null,
                'attachments' => $headerData['attachments'] ?? [],
                'total_requested' => $this->fromCents($totalRequestedCents),
                'status' => 'submitted',
                'created_by' => $user->id,
            ]);

            foreach ($validatedLines as $line) {
                $fundRequest->items()->create([
                    'rka_item_id' => $line['rka_item_id'],
                    'account_id' => $line['account_id'],
                    'rka_account_code_snapshot' => $line['account_code'],
                    'rka_account_name_snapshot' => $line['account_name'],
                    'rka_approved_volume_snapshot' => $line['rka_approved_volume'],
                    'rka_approved_total_snapshot' => $line['rka_approved_total'],
                    'description' => $line['description'],
                    'volume' => $line['volume'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ]);
            }

            return $fundRequest;
        });
    }

    /**
     * Memperbarui Fund Request yang ada secara transaksional dengan mengecualikan transaksi yang sedang diedit.
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $rawItems
     *
     * @throws InvalidArgumentException
     */
    public function updateFundRequest(User $user, FundRequest $fundRequest, array $headerData, array $rawItems): FundRequest
    {
        return DB::transaction(function () use ($user, $fundRequest, $headerData, $rawItems): FundRequest {
            $lockedHeader = FundRequest::query()->lockForUpdate()->findOrFail($fundRequest->id);

            if (in_array($lockedHeader->status, ['approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Pengajuan yang sudah disahkan atau ditolak tidak dapat diubah.');
            }

            $ctx = $this->resolveApprovedRkaContext($user, $lockedHeader->department_id);
            $rkaHeader = $ctx['rka_header'];
            $schedule = $ctx['schedule'];
            $department = $ctx['department'];

            if ($lockedHeader->rka_header_id !== null && (int) $lockedHeader->rka_header_id !== (int) $rkaHeader->id) {
                throw new InvalidArgumentException('RKA sumber pengajuan tidak lagi sesuai dengan periode anggaran aktif.');
            }

            $validatedLines = $this->validateAndLockBudgetLines(
                rkaHeader: $rkaHeader,
                department: $department,
                fiscalYear: $schedule->fiscal_year,
                rawItems: $rawItems,
                excludeFundRequestId: $lockedHeader->id,
            );

            $totalRequestedCents = array_sum(array_column($validatedLines, 'total_price_cents'));

            $lockedHeader->update([
                'document_number' => $headerData['document_number'],
                'request_date' => $headerData['request_date'],
                'fiscal_year' => $schedule->fiscal_year,
                'department_id' => $department->id,
                'rka_header_id' => $rkaHeader->id,
                'activity_name' => $headerData['activity_name'],
                'execution_date' => ! empty($headerData['execution_date']) ? $headerData['execution_date'] : null,
                'responsible_person' => $headerData['responsible_person'],
                'disbursement_type' => $headerData['disbursement_type'],
                'recipient_name' => $headerData['recipient_name'],
                'bank_account_info' => $headerData['bank_account_info'] ?? null,
                'attachments' => $headerData['attachments'] ?? [],
                'total_requested' => $this->fromCents($totalRequestedCents),
                'status' => $lockedHeader->status,
            ]);

            $keptIds = [];
            foreach ($validatedLines as $line) {
                $existingId = $line['existing_id'] ?? null;
                $payload = [
                    'rka_item_id' => $line['rka_item_id'],
                    'account_id' => $line['account_id'],
                    'rka_account_code_snapshot' => $line['account_code'],
                    'rka_account_name_snapshot' => $line['account_name'],
                    'rka_approved_volume_snapshot' => $line['rka_approved_volume'],
                    'rka_approved_total_snapshot' => $line['rka_approved_total'],
                    'description' => $line['description'],
                    'volume' => $line['volume'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total_price'],
                ];

                if ($existingId !== null) {
                    $existing = $lockedHeader->items()->where('id', $existingId)->first();
                    if ($existing) {
                        $existing->update($payload);
                        $keptIds[] = $existing->id;

                        continue;
                    }
                }

                $created = $lockedHeader->items()->create($payload);
                $keptIds[] = $created->id;
            }

            $lockedHeader->items()
                ->when($keptIds !== [], fn ($q) => $q->whereNotIn('id', $keptIds))
                ->delete();

            return $lockedHeader->fresh(['items']);
        });
    }

    /**
     * Mengunci RkaItem & DepartmentBudget di DB, lalu memvalidasi kontrol item dan rekening di server.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private function validateAndLockBudgetLines(
        RkaHeader $rkaHeader,
        Department $department,
        string $fiscalYear,
        array $rawItems,
        ?int $excludeFundRequestId,
    ): array {
        if ($rawItems === []) {
            throw new InvalidArgumentException('Minimal satu item RKA Approved harus dipilih pada Rincian Kebutuhan.');
        }

        $rkaItemIds = [];
        foreach ($rawItems as $idx => $row) {
            $rkaItemId = isset($row['rka_item_id']) ? (int) $row['rka_item_id'] : 0;
            if ($rkaItemId <= 0) {
                throw new InvalidArgumentException('Baris kebutuhan #'.($idx + 1).' wajib memilih item dari RKA Approved.');
            }
            if (in_array($rkaItemId, $rkaItemIds, true)) {
                throw new InvalidArgumentException('Item RKA yang sama tidak boleh ditambahkan lebih dari satu baris.');
            }
            $rkaItemIds[] = $rkaItemId;
        }

        // Lock RkaItems dalam urutan ID deterministik untuk mencegah race condition & deadlock
        $sortedItemIds = $rkaItemIds;
        sort($sortedItemIds);

        $lockedRkaItems = RkaItem::query()
            ->with('account')
            ->whereIn('id', $sortedItemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $accountIds = $lockedRkaItems->pluck('account_id')->filter()->unique()->sort()->values()->all();

        // Lock DepartmentBudget dalam urutan ID deterministik
        $lockedBudgets = DepartmentBudget::query()
            ->where('department_id', $department->id)
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('account_id', $accountIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('account_id');

        $usedByItem = $this->getUsedAmountByRkaItemIds($sortedItemIds, $excludeFundRequestId);
        $usedByAccount = $this->getUsedAmountByAccountIds($department->id, $fiscalYear, $accountIds, $excludeFundRequestId);

        $validatedLines = [];
        $requestedCentsByAccount = [];

        foreach ($rawItems as $row) {
            $rkaItemId = (int) $row['rka_item_id'];
            $rkaItem = $lockedRkaItems->get($rkaItemId);

            if (! $rkaItem || (int) $rkaItem->rka_header_id !== (int) $rkaHeader->id) {
                throw new InvalidArgumentException("Item RKA #{$rkaItemId} bukan milik RKA Approved unit Anda pada periode aktif.");
            }

            if (! in_array($rkaItem->status, self::ELIGIBLE_RKA_ITEM_STATUSES, true)) {
                throw new InvalidArgumentException("Item '{$rkaItem->description}' tidak berstatus disetujui dan tidak dapat diajukan.");
            }

            $approvedTotal = (float) $rkaItem->approved_total;
            $approvedVol = $this->resolveEffectiveApprovedVolume($rkaItem);

            if ($approvedTotal <= 0 || $approvedVol <= 0 || $rkaItem->account_id === null) {
                throw new InvalidArgumentException("Item '{$rkaItem->description}' tidak memiliki nilai atau volume persetujuan yang valid.");
            }

            $requestedVol = round((float) ($row['volume'] ?? 0), 2);
            if ($requestedVol <= 0) {
                throw new InvalidArgumentException("Volume pengajuan untuk '{$rkaItem->description}' harus lebih besar dari 0.");
            }

            // Harga satuan selalu dihitung dari RKA Approved (mengabaikan manipulasi harga dari browser)
            $approvedUnitPrice = round($approvedTotal / $approvedVol, 2);
            $lineTotal = round($requestedVol * $approvedUnitPrice, 2);
            $lineCents = $this->toCents($lineTotal);

            // Kontrol Pertama: Sisa Anggaran Per Item RKA
            $approvedCents = $this->toCents($approvedTotal);
            $usedItemCents = $this->toCents($usedByItem[$rkaItemId] ?? 0.0);
            $remainingItemCents = max(0, $approvedCents - $usedItemCents);

            if ($lineCents > $remainingItemCents) {
                $remFormatted = number_format($this->fromCents($remainingItemCents), 0, ',', '.');
                throw new InvalidArgumentException(
                    "Jumlah pengajuan melebihi sisa anggaran item. Sisa anggaran yang tersedia adalah Rp{$remFormatted}."
                );
            }

            $accountId = (int) $rkaItem->account_id;
            $requestedCentsByAccount[$accountId] = ($requestedCentsByAccount[$accountId] ?? 0) + $lineCents;

            $validatedLines[] = [
                'existing_id' => isset($row['id']) ? (int) $row['id'] : null,
                'rka_item_id' => $rkaItem->id,
                'account_id' => $accountId,
                'account_code' => $rkaItem->account?->code ?? '-',
                'account_name' => $rkaItem->account?->name ?? '-',
                'rka_approved_volume' => $approvedVol,
                'rka_approved_total' => $approvedTotal,
                'description' => $rkaItem->description,
                'volume' => $requestedVol,
                'unit' => $rkaItem->unit_symbol ?: 'Unit',
                'unit_price' => $approvedUnitPrice,
                'total_price' => $this->fromCents($lineCents),
                'total_price_cents' => $lineCents,
            ];
        }

        // Kontrol Kedua: Sisa Pagu Per Kode Rekening (DepartmentBudget)
        foreach ($requestedCentsByAccount as $accountId => $reqCents) {
            $budget = $lockedBudgets->get($accountId);
            $allocatedCents = $this->toCents((float) ($budget?->allocated_amount ?? 0.0));
            $usedAccCents = $this->toCents($usedByAccount[$accountId] ?? 0.0);
            $remainingAccCents = max(0, $allocatedCents - $usedAccCents);

            if ($reqCents > $remainingAccCents) {
                $account = Account::find($accountId);
                $code = $account?->code ?? (string) $accountId;
                $remFormatted = number_format($this->fromCents($remainingAccCents), 0, ',', '.');

                throw new InvalidArgumentException(
                    "Total pengajuan untuk rekening {$code} melebihi sisa pagu rekening yang tersedia (sisa Rp{$remFormatted})."
                );
            }
        }

        return $validatedLines;
    }

    /**
     * Mengembalikan volume disetujui efektif: jika approved_volume NULL (item disetujui penuh dari Excel),
     * gunakan proposed_volume; jika bernilai 0 atau negatif, tetap kembalikan nilai tersebut agar ditolak.
     */
    private function resolveEffectiveApprovedVolume(RkaItem $item): float
    {
        if ($item->approved_volume !== null) {
            return (float) $item->approved_volume;
        }

        return (float) $item->proposed_volume;
    }

    private function effectiveLineCents(FundRequestItem $item): int
    {
        if ($item->verification_status === 'rejected') {
            return 0;
        }

        if (in_array($item->verification_status, ['approved', 'partial'], true)) {
            return $this->toCents((float) ($item->approved_price ?? $item->total_price));
        }

        return $this->toCents((float) $item->total_price);
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
