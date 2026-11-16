<?php

namespace App\Filament\Resources\FundRequests\Pages;

use App\Filament\Resources\FundRequests\FundRequestResource;
use App\Models\Account;
use App\Models\DepartmentBudget;
use App\Models\FundRequestItem;
use App\Models\User;
use App\Services\FundRequest\FundRequestBudgetService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VerifyFundRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FundRequestResource::class;

    protected string $view = 'filament.resources.fund-requests.pages.verify-fund-request';

    // State Slide-Over Verifikasi Item
    public bool $isModalOpen = false;

    public bool $hasUnsavedModalChanges = false;

    public ?int $selectedItemId = null;

    public ?string $selectedItemDescription = null;

    public ?string $selectedItemAccountCode = null;

    public ?string $selectedItemAccountName = null;

    public ?string $selectedItemProgramActivity = null;

    public ?string $selectedItemRkaSource = null;

    public ?string $selectedItemCurrentStatus = null;

    public float $selectedItemRequestedVolume = 0;

    public ?string $selectedItemUnit = null;

    public float $selectedItemUnitPrice = 0;

    public float $selectedItemRequestedPrice = 0;

    public float $selectedItemRkaApprovedTotal = 0;

    public float $selectedItemOtherUsed = 0;

    public float $selectedItemRemainingBefore = 0;

    // Form Slide-Over Fields
    public ?float $modal_approved_volume = null;

    public ?float $modal_approved_price = null;

    public ?string $modal_verification_status = null;

    public ?string $modal_finance_note = null;

    // Slide-Over Rincian Per Kode Rekening (Bagian F)
    public ?int $detailAccountId = null;

    // Data Store Temporary
    public array $itemVerifications = [];

    public array $verifications = [];

    public ?string $verification_note = null;

    public string $action_type = 'approve';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['keuangan', 'wadir_keuangan', 'direktur']);
    }

    public function mount(int|string $record): void
    {
        $user = auth()->user();

        abort_unless(
            $user && $user->hasAnyRole(['keuangan', 'wadir_keuangan', 'direktur']),
            403,
            'Anda tidak memiliki akses ke halaman verifikasi.'
        );

        $this->record = $this->resolveRecord($record)->load([
            'department',
            'rkaHeader.schedule',
            'items.account',
            'items.rkaItem.indicator',
            'approvals.user',
        ]);

        abort_unless($user->can('view', $this->record), 403);
        $items = $this->record->items ?? [];

        foreach ($items as $item) {
            $this->itemVerifications[$item->id] = [
                'approved_volume' => $item->approved_volume,
                'approved_price' => $item->approved_price,
                'verification_status' => $item->verification_status,
                'finance_note' => $item->finance_note ?? '',
            ];
        }

        $this->verification_note = null;
        $this->recalculateGroupedVerifications();
    }

    public function canVerifyItems(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null
            && $user->hasRole('keuangan')
            && ! in_array($this->record->status, ['approved', 'rejected'], true);
    }

    /**
     * Buka Slide-Over Verifikasi Per Item.
     */
    public function openApprovalModal(int $itemId): void
    {
        if (! $this->canVerifyItems()) {
            return;
        }

        $item = $this->record->items->firstWhere('id', $itemId);
        if (! $item) {
            return;
        }

        $this->selectedItemId = $item->id;
        $this->selectedItemDescription = $item->description;
        $this->selectedItemAccountCode = $item->account?->code ?? $item->rka_account_code_snapshot ?? '-';
        $this->selectedItemAccountName = $item->account?->name ?? $item->rka_account_name_snapshot ?? '-';
        $this->selectedItemProgramActivity = $item->rkaItem?->indicator
            ? trim($item->rkaItem->indicator->program_name.' - '.$item->rkaItem->indicator->activity_name, ' -')
            : '-';
        $this->selectedItemRkaSource = $this->record->rka_header_id
            ? "RKA #{$this->record->rka_header_id} ({$this->record->fiscal_year})"
            : 'Manual / Historis';
        $this->selectedItemRequestedVolume = (float) $item->volume;
        $this->selectedItemUnit = $item->unit;
        $this->selectedItemUnitPrice = (float) $item->unit_price;
        $this->selectedItemRequestedPrice = (float) $item->total_price;

        // Hitung ringkasan anggaran RKA dari backend
        if ($item->rkaItem) {
            $rkaApprovedTotal = (float) $item->rkaItem->approved_total;
            $usedByOthers = app(FundRequestBudgetService::class)->getUsedAmountByRkaItemIds(
                [$item->rkaItem->id],
                $this->record->id
            )[$item->rkaItem->id] ?? 0.0;

            $this->selectedItemRkaApprovedTotal = $rkaApprovedTotal;
            $this->selectedItemOtherUsed = (float) $usedByOthers;
            $this->selectedItemRemainingBefore = max(0.0, round($rkaApprovedTotal - $usedByOthers, 2));
        } else {
            $this->selectedItemRkaApprovedTotal = (float) ($item->rka_approved_total_snapshot ?? 0);
            $this->selectedItemOtherUsed = 0.0;
            $this->selectedItemRemainingBefore = $this->selectedItemRkaApprovedTotal;
        }

        $currentData = $this->itemVerifications[$itemId] ?? [];
        $this->selectedItemCurrentStatus = $currentData['verification_status'] ?? null;

        $this->modal_approved_volume = isset($currentData['approved_volume']) && $currentData['approved_volume'] !== null
            ? (float) $currentData['approved_volume']
            : (float) $item->volume;

        $this->modal_approved_price = isset($currentData['approved_price']) && $currentData['approved_price'] !== null
            ? (float) $currentData['approved_price']
            : (float) $item->total_price;

        $this->modal_verification_status = $currentData['verification_status'] ?? 'approved';
        $this->modal_finance_note = $currentData['finance_note'] ?? '';
        $this->hasUnsavedModalChanges = false;

        $this->isModalOpen = true;
    }

    public function updatedModalApprovedVolume($value): void
    {
        $this->hasUnsavedModalChanges = true;

        if ($value === '' || $value === null) {
            $this->modal_approved_price = null;

            return;
        }

        $volume = (float) $value;
        $unitPrice = $this->selectedItemUnitPrice;
        $requestedVolume = $this->selectedItemRequestedVolume;

        $this->modal_approved_price = round($volume * $unitPrice, 2);

        if ($volume <= 0) {
            $this->modal_verification_status = 'rejected';
        } elseif ($volume < $requestedVolume) {
            $this->modal_verification_status = 'partial';
        } else {
            $this->modal_verification_status = 'approved';
        }
    }

    public function updatedModalApprovedPrice(): void
    {
        $this->hasUnsavedModalChanges = true;
    }

    public function updatedModalVerificationStatus($value): void
    {
        $this->hasUnsavedModalChanges = true;

        if ($value === 'rejected') {
            $this->modal_approved_volume = 0.0;
            $this->modal_approved_price = 0.0;
        }
    }

    public function updatedModalFinanceNote(): void
    {
        $this->hasUnsavedModalChanges = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetModalFields();
    }

    /**
     * Simpan perubahan verifikasi item ke state & database secara transaksional.
     */
    public function saveItemApproval(): void
    {
        abort_unless($this->canVerifyItems(), 403, 'Anda tidak memiliki wewenang untuk memverifikasi item.');

        if (! $this->selectedItemId) {
            return;
        }

        $this->validate([
            'modal_approved_volume' => [
                'nullable',
                'numeric',
                'min:0',
                'max:'.$this->selectedItemRequestedVolume,
            ],
            'modal_approved_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:'.$this->selectedItemRequestedPrice,
            ],
            'modal_verification_status' => 'required|in:approved,partial,rejected',
            'modal_finance_note' => 'nullable|string|max:500',
        ], [
            'modal_approved_volume.max' => 'Volume disetujui tidak boleh melebihi volume dimohonkan.',
            'modal_approved_price.max' => 'Total disetujui tidak boleh melebihi total dimohonkan.',
        ]);

        $isApprovedOrPartial = in_array($this->modal_verification_status, ['approved', 'partial'], true);
        $approvedVolume = $isApprovedOrPartial ? (float) ($this->modal_approved_volume ?? 0) : 0.0;
        $approvedPrice = $isApprovedOrPartial ? (float) ($this->modal_approved_price ?? 0) : 0.0;

        $item = $this->record->items->firstWhere('id', $this->selectedItemId);
        if ($isApprovedOrPartial && $item?->rka_item_id !== null && $approvedPrice > $this->selectedItemRemainingBefore) {
            $this->addError(
                'modal_approved_price',
                'Total disetujui melebihi sisa anggaran item RKA (sisa Rp'.number_format($this->selectedItemRemainingBefore, 0, ',', '.').').'
            );

            return;
        }

        DB::transaction(function () use ($approvedVolume, $approvedPrice): void {
            FundRequestItem::where('id', $this->selectedItemId)
                ->where('fund_request_id', $this->record->id)
                ->update([
                    'approved_volume' => $approvedVolume,
                    'approved_price' => $approvedPrice,
                    'verification_status' => $this->modal_verification_status,
                    'finance_note' => $this->modal_finance_note,
                ]);

            $this->record->recalculateTotals();
        });

        $this->itemVerifications[$this->selectedItemId] = [
            'approved_volume' => $approvedVolume,
            'approved_price' => $approvedPrice,
            'verification_status' => $this->modal_verification_status,
            'finance_note' => $this->modal_finance_note,
        ];

        $this->record->load(['items.account', 'items.rkaItem.indicator']);
        $this->recalculateGroupedVerifications();
        $this->closeModal();

        Notification::make()
            ->title('Status Item Berhasil Diperbarui')
            ->success()
            ->send();
    }

    public function openAccountDetail(int $accountId): void
    {
        $this->detailAccountId = $accountId;
    }

    public function closeAccountDetail(): void
    {
        $this->detailAccountId = null;
    }

    public function getDetailAccountItemsProperty(): Collection
    {
        if ($this->detailAccountId === null) {
            return collect();
        }

        return ($this->record->items ?? collect())
            ->where('account_id', $this->detailAccountId)
            ->values();
    }

    private function recalculateGroupedVerifications(): void
    {
        $items = $this->record->items ?? collect();
        $groupedAccounts = collect($items)->groupBy('account_id');
        $accountIds = $groupedAccounts->keys()->filter()->map(fn ($id) => (int) $id)->values()->all();

        $accounts = Account::query()->whereIn('id', $accountIds)->get()->keyBy('id');
        $budgets = DepartmentBudget::query()
            ->where('department_id', $this->record->department_id)
            ->where('fiscal_year', $this->record->fiscal_year)
            ->whereIn('account_id', $accountIds)
            ->get()
            ->keyBy('account_id');

        $usedOthersByAccount = app(FundRequestBudgetService::class)->getUsedAmountByAccountIds(
            (int) $this->record->department_id,
            (string) $this->record->fiscal_year,
            $accountIds,
            $this->record->id
        );

        $existingRows = collect($this->verifications)->keyBy('account_id');
        $this->verifications = [];

        foreach ($groupedAccounts as $accountId => $groupItems) {
            $account = $accounts->get((int) $accountId);
            $allocated = (float) ($budgets->get((int) $accountId)?->allocated_amount ?? 0.0);
            $usedOthers = (float) ($usedOthersByAccount[(int) $accountId] ?? 0.0);
            $remainingBudget = max(0.0, round($allocated - $usedOthers, 2));

            $totalRequested = (float) $groupItems->sum(fn ($item) => (float) $item->total_price);

            $totalApproved = (float) $groupItems->sum(function ($item): float {
                $verification = $this->itemVerifications[$item->id] ?? [];
                $status = $verification['verification_status'] ?? null;

                if (in_array($status, ['approved', 'partial'], true)) {
                    return (float) ($verification['approved_price'] ?? 0);
                }

                return 0.0;
            });

            $statuses = $groupItems->map(fn ($item) => $this->itemVerifications[$item->id]['verification_status'] ?? null);
            if ($statuses->contains(null)) {
                $aggregateStatus = 'Belum Selesai Diverifikasi';
            } elseif ($statuses->every(fn ($s): bool => $s === 'approved')) {
                $aggregateStatus = 'Disetujui';
            } elseif ($statuses->every(fn ($s): bool => $s === 'rejected')) {
                $aggregateStatus = 'Ditolak';
            } else {
                $aggregateStatus = 'Disetujui Sebagian';
            }

            $existing = $existingRows->get($accountId, []);

            $this->verifications[] = [
                'account_id' => $accountId,
                'account_code' => $account?->code ?? '-',
                'account_name' => $account?->name ?? '-',
                'item_count' => $groupItems->count(),
                'final_account_id' => $existing['final_account_id'] ?? $accountId,
                'remaining_budget' => $remainingBudget,
                'total_requested' => $totalRequested,
                'amount_approved' => $totalApproved,
                'selisih' => max(0.0, round($totalRequested - $totalApproved, 2)),
                'status' => $aggregateStatus,
                'register_proof_number' => $existing['register_proof_number'] ?? null,
            ];
        }
    }

    private function resetModalFields(): void
    {
        $this->selectedItemId = null;
        $this->selectedItemDescription = null;
        $this->selectedItemAccountCode = null;
        $this->selectedItemAccountName = null;
        $this->selectedItemProgramActivity = null;
        $this->selectedItemRkaSource = null;
        $this->selectedItemCurrentStatus = null;
        $this->selectedItemRequestedVolume = 0;
        $this->selectedItemUnit = null;
        $this->selectedItemUnitPrice = 0;
        $this->selectedItemRequestedPrice = 0;
        $this->selectedItemRkaApprovedTotal = 0;
        $this->selectedItemOtherUsed = 0;
        $this->selectedItemRemainingBefore = 0;

        $this->modal_approved_volume = null;
        $this->modal_approved_price = null;
        $this->modal_verification_status = null;
        $this->modal_finance_note = null;
        $this->hasUnsavedModalChanges = false;
        $this->resetErrorBag();
    }

    public function getApprovedTotalProperty(): float
    {
        return (float) collect($this->itemVerifications)
            ->whereIn('verification_status', ['approved', 'partial'])
            ->sum(fn ($item): float => (float) ($item['approved_price'] ?? 0));
    }

    public function saveVerification(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        abort_unless(
            $user && $user->hasAnyRole(['keuangan', 'wadir_keuangan', 'direktur']),
            403,
            'Anda tidak memiliki wewenang untuk melakukan verifikasi.'
        );

        abort_unless($user->can('view', $this->record), 403);

        $isApproved = $this->action_type === 'approve';

        if (! $isApproved) {
            $this->validate([
                'verification_note' => 'required|string|min:3',
            ], [
                'verification_note.required' => 'Alasan penolakan wajib diisi sebelum menolak pengajuan.',
                'verification_note.min' => 'Alasan penolakan minimal 3 karakter.',
            ]);
        }

        DB::transaction(function () use ($user, $isApproved): void {
            if ($user->hasRole('keuangan')) {
                $stage = 'kabakku';
                $nextStatus = $isApproved ? 'verified_kabakku' : 'rejected';

                $this->record->verifications()->delete();

                foreach ($this->verifications as $verification) {
                    $this->record->verifications()->create([
                        'account_id' => $verification['account_id'],
                        'final_account_id' => $verification['final_account_id'],
                        'remaining_budget' => $verification['remaining_budget'],
                        'amount_approved' => $isApproved ? $verification['amount_approved'] : 0,
                        'status' => $verification['status'] ?? 'Disetujui',
                        'register_proof_number' => $verification['register_proof_number'] ?? null,
                    ]);
                }
            } elseif ($user->hasRole('wadir_keuangan')) {
                $stage = 'wadir_bakku';
                $nextStatus = $isApproved ? 'verified_wadir_bakku' : 'rejected';
            } elseif ($user->hasRole('direktur')) {
                $stage = 'director';
                $nextStatus = $isApproved ? 'approved' : 'rejected';
            } else {
                abort(403, 'Anda tidak memiliki wewenang untuk melakukan verifikasi.');
            }

            $this->record->approvals()->create([
                'stage' => $stage,
                'action' => $isApproved ? 'approved' : 'rejected',
                'approved_amount' => $isApproved ? $this->approvedTotal : 0,
                'note' => $this->verification_note,
                'user_id' => $user->id,
            ]);

            $this->record->update([
                'status' => $nextStatus,
                'total_approved' => $isApproved ? $this->approvedTotal : 0,
            ]);
        });

        Notification::make()
            ->title('Verifikasi Berhasil Disimpan')
            ->body($isApproved ? 'Pengajuan berhasil diteruskan ke tahap berikutnya.' : 'Pengajuan telah ditolak.')
            ->success()
            ->send();

        $this->redirect(FundRequestResource::getUrl('index'));
    }
}
