<?php

namespace App\Filament\Resources\FundRequests\Pages;

use App\Filament\Resources\FundRequests\FundRequestResource;
use App\Models\Account;
use App\Models\FundRequestItem;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class VerifyFundRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FundRequestResource::class;

    protected string $view = 'filament.resources.fund-requests.pages.verify-fund-request';

    // State Modal Approval
    public bool $isModalOpen = false;
    public ?int $selectedItemId = null;
    public ?string $selectedItemDescription = null;
    public float $selectedItemRequestedVolume = 0;
    public ?string $selectedItemUnit = null;
    public float $selectedItemUnitPrice = 0;
    public float $selectedItemRequestedPrice = 0;

    // Form Modal Fields
    public ?float $modal_approved_volume = null;
    public ?float $modal_approved_price = null;
    public ?string $modal_verification_status = null;
    public ?string $modal_finance_note = null;

    // Data Store Temporary
    public array $itemVerifications = [];
    public array $verifications = [];

    public ?string $verification_note = null;
    public string $action_type = 'approve';

    public function mount(int|string $record): void
    {
        // Eager-load approvals dan user agar efisien
        $this->record = $this->resolveRecord($record)->load(['items', 'approvals.user']);
        $items = $this->record->items ?? [];

        foreach ($items as $item) {
            $this->itemVerifications[$item->id] = [
                'approved_volume'     => $item->approved_volume,
                'approved_price'      => $item->approved_price,
                'verification_status' => $item->verification_status,
                'finance_note'        => $item->finance_note ?? '',
            ];
        }

        // 1. Kosongkan verification_note agar verifikator memasukkan catatan BARU
        $this->verification_note = null;

        $this->recalculateGroupedVerifications();
    }

    public function canVerifyItems(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user && $user->hasRole('keuangan');
    }

    /**
     * Buka Modal Approval Per Item
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

        // Set Informasi Ringkas Item Permohonan
        $this->selectedItemId = $item->id;
        $this->selectedItemDescription = $item->description;
        $this->selectedItemRequestedVolume = (float) $item->volume;
        $this->selectedItemUnit = $item->unit;
        $this->selectedItemUnitPrice = (float) $item->unit_price;
        $this->selectedItemRequestedPrice = (float) $item->total_price;

        // Populate nilai dari state sementara (jika null, default isi dengan nilai awal permohonan)
        $currentData = $this->itemVerifications[$itemId] ?? [];

        $this->modal_approved_volume = isset($currentData['approved_volume']) && $currentData['approved_volume'] !== null
            ? (float) $currentData['approved_volume']
            : (float) $item->volume;

        $this->modal_approved_price = isset($currentData['approved_price']) && $currentData['approved_price'] !== null
            ? (float) $currentData['approved_price']
            : (float) $item->total_price;

        $this->modal_verification_status = $currentData['verification_status'] ?? 'approved';
        $this->modal_finance_note = $currentData['finance_note'] ?? '';

        $this->isModalOpen = true;
    }

    /**
     * Listener Livewire: Mengkalkulasi total disetujui & status verifikasi secara otomatis ketika volume diubah
     */
    public function updatedModalApprovedVolume($value): void
    {
        if ($value === '' || $value === null) {
            $this->modal_approved_price = null;
            return;
        }

        $volume = (float) $value;
        $unitPrice = $this->selectedItemUnitPrice;
        $requestedVolume = $this->selectedItemRequestedVolume;

        // Auto calculate total approved price
        $this->modal_approved_price = round($volume * $unitPrice);

        // Auto determine verification status based on approved volume
        if ($volume <= 0) {
            $this->modal_verification_status = 'rejected';
        } elseif ($volume < $requestedVolume) {
            $this->modal_verification_status = 'partial';
        } else {
            $this->modal_verification_status = 'approved';
        }
    }

    /**
     * Tutup Modal
     */
    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetModalFields();
    }

    /**
     * Simpan Perubahan Form Modal ke State & Database
     */
    public function saveItemApproval(): void
    {
        if (! $this->selectedItemId || ! $this->canVerifyItems()) {
            return;
        }

        // 1. Validasi Input Modal
        $this->validate([
            'modal_approved_volume'     => 'nullable|numeric|min:0',
            'modal_approved_price'      => 'nullable|numeric|min:0',
            'modal_verification_status' => 'required|in:approved,partial,rejected',
            'modal_finance_note'        => 'nullable|string|max:500',
        ]);

        // 1. Tentukan nilai volume dan harga berdasarkan status verifikasi
        $isApprovedOrPartial = in_array($this->modal_verification_status, ['approved', 'partial'], true);

        $approvedVolume = $isApprovedOrPartial ? $this->modal_approved_volume : null;
        $approvedPrice  = $isApprovedOrPartial ? $this->modal_approved_price : null;

        // 2. Update Database
        FundRequestItem::where('id', $this->selectedItemId)
            ->where('fund_request_id', $this->record->id)
            ->update([
                'approved_volume'     => $approvedVolume,
                'approved_price'      => $approvedPrice,
                'verification_status' => $this->modal_verification_status,
                'finance_note'        => $this->modal_finance_note,
            ]);

        // 3. Sync Ke State Lokal Livewire (Pastikan state lokal juga mendapat nilai null jika rejected)
        $this->itemVerifications[$this->selectedItemId] = [
            'approved_volume'     => $approvedVolume,
            'approved_price'      => $approvedPrice,
            'verification_status' => $this->modal_verification_status,
            'finance_note'        => $this->modal_finance_note,
        ];

        // 4. Hitung ulang verifikasi akun & reload record
        $this->record->load('items');
        $this->recalculateGroupedVerifications();

        $this->closeModal();

        Notification::make()
            ->title('Status Item Berhasil Diperbarui')
            ->success()
            ->send();
    }

    private function recalculateGroupedVerifications(): void
    {
        $items = $this->record->items ?? [];
        $groupedAccounts = collect($items)->groupBy('account_id');
        $this->verifications = [];

        foreach ($groupedAccounts as $accountId => $groupItems) {
            $account = Account::find($accountId);

            $totalApproved = $groupItems->sum(function ($item) {
                $verification = $this->itemVerifications[$item->id] ?? [];
                $status = $verification['verification_status'] ?? null;

                // Hanya hitung jika statusnya 'approved' atau 'partial'
                if (in_array($status, ['approved', 'partial'], true)) {
                    return (float) ($verification['approved_price'] ?? 0);
                }

                // Jika status 'rejected' atau masih 'null' (pending), nominal dianggap 0
                return 0;
            });

            $this->verifications[] = [
                'account_id'            => $accountId,
                'account_code'          => $account?->code ?? '-',
                'final_account_id'      => $accountId,
                'remaining_budget'      => $account?->remaining_budget ?? 0,
                'amount_approved'       => $totalApproved,
                'status'                => 'Disetujui',
                'register_proof_number' => null,
            ];
        }
    }

    private function resetModalFields(): void
    {
        $this->selectedItemId = null;
        $this->selectedItemDescription = null;
        $this->selectedItemRequestedVolume = 0;
        $this->selectedItemUnit = null;
        $this->selectedItemUnitPrice = 0;
        $this->selectedItemRequestedPrice = 0;

        $this->modal_approved_volume = null;
        $this->modal_approved_price = null;
        $this->modal_verification_status = null;
        $this->modal_finance_note = null;
    }

    public function getApprovedTotalProperty(): float
    {
        return collect($this->itemVerifications)
            ->whereIn('verification_status', ['approved', 'partial'])
            ->sum(function ($item) {
                return (float) ($item['approved_price'] ?? 0);
            });
    }

    public function saveVerification(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isApproved = $this->action_type === 'approve';

        DB::transaction(function () use ($user, $isApproved) {
            if ($user->hasRole('keuangan')) {
                $stage = 'kabakku';
                $nextStatus = $isApproved ? 'verified_kabakku' : 'rejected';

                $this->record->verifications()->delete();

                foreach ($this->verifications as $verification) {
                    $this->record->verifications()->create([
                        'account_id'            => $verification['account_id'],
                        'final_account_id'      => $verification['final_account_id'],
                        'remaining_budget'      => $verification['remaining_budget'],
                        'amount_approved'       => $isApproved ? $verification['amount_approved'] : 0,
                        'status'                => $verification['status'] ?? 'Disetujui',
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
                $stage = 'kabakku';
                $nextStatus = $isApproved ? 'approved' : 'rejected';
            }

            $this->record->approvals()->create([
                'stage'           => $stage,
                'action'          => $isApproved ? 'approved' : 'rejected',
                'approved_amount' => $isApproved ? $this->approvedTotal : 0,
                'note'            => $this->verification_note,
                'user_id'         => $user->id,
            ]);

            $this->record->update([
                'status'         => $nextStatus,
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
