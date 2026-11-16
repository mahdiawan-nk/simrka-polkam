<?php

namespace App\Filament\Resources\FundRequests\Concerns;

use App\Models\RkaHeader;
use App\Services\FundRequest\FundRequestBudgetService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait InteractsWithApprovedRkaFundRequest
{
    public ?int $rkaHeaderId = null;

    public ?string $rkaSourceName = null;

    public ?string $activePeriodLabel = null;

    public ?string $rkaSourceError = null;

    public string $itemSearch = '';

    public string $itemFormTypeFilter = '';

    public string $itemAccountFilter = '';

    protected function resolveRkaSourceContext(): void
    {
        try {
            $ctx = app(FundRequestBudgetService::class)->resolveApprovedRkaContext(
                auth()->user(),
                $this->department_id,
            );

            $this->rkaHeaderId = $ctx['rka_header']->id;
            $this->department_id = $ctx['department']->id;
            $this->fiscal_year = $ctx['schedule']->fiscal_year;
            $this->disableSelectDepartment = true;
            $this->activePeriodLabel = trim($ctx['schedule']->fiscal_year.' — '.($ctx['schedule']->period_range ?? ''), ' —');
            $this->rkaSourceName = "RKA #{$ctx['rka_header']->id} ({$ctx['department']->name})";
            $this->rkaSourceError = null;
        } catch (InvalidArgumentException $e) {
            $this->rkaHeaderId = null;
            $this->rkaSourceName = null;
            $this->rkaSourceError = $e->getMessage();
        }
    }

    public function getRkaHeaderProperty(): ?RkaHeader
    {
        if ($this->rkaHeaderId === null) {
            return null;
        }

        return RkaHeader::with(['department', 'schedule'])->find($this->rkaHeaderId);
    }

    public function getSelectableRkaItemsProperty(): Collection
    {
        $header = $this->rkaHeader;
        if (! $header) {
            return collect();
        }

        $excludeId = property_exists($this, 'record') && $this->record ? $this->record->id : null;

        return app(FundRequestBudgetService::class)->getSelectableRkaItems(
            rkaHeader: $header,
            excludeFundRequestId: $excludeId,
            filters: [
                'search' => $this->itemSearch,
                'form_type' => $this->itemFormTypeFilter,
                'account_id' => $this->itemAccountFilter !== '' ? (int) $this->itemAccountFilter : null,
            ],
            onlyWithRemainingBalance: true,
        );
    }

    public function getSelectableAccountsProperty(): Collection
    {
        $header = $this->rkaHeader;
        if (! $header) {
            return collect();
        }

        return app(FundRequestBudgetService::class)
            ->getSelectableRkaItems($header, onlyWithRemainingBalance: false)
            ->map(fn (array $row): array => [
                'id' => $row['account_id'],
                'label' => "{$row['account_code']} — {$row['account_name']}",
            ])
            ->unique('id')
            ->values();
    }

    public function getTotalAvailableRkaItemsCountProperty(): int
    {
        $header = $this->rkaHeader;
        if (! $header) {
            return 0;
        }

        $excludeId = property_exists($this, 'record') && $this->record ? $this->record->id : null;

        return app(FundRequestBudgetService::class)
            ->getSelectableRkaItems($header, excludeFundRequestId: $excludeId, onlyWithRemainingBalance: true)
            ->count();
    }

    public function resetItemFilters(): void
    {
        $this->itemSearch = '';
        $this->itemFormTypeFilter = '';
        $this->itemAccountFilter = '';
    }

    public function openItemPickerModal(): void
    {
        if (! $this->rkaHeaderId) {
            Notification::make()
                ->title('RKA Approved Tidak Tersedia')
                ->body($this->rkaSourceError ?? 'Belum tersedia RKA yang telah disahkan untuk unit Anda pada periode anggaran aktif.')
                ->warning()
                ->send();

            return;
        }

        $this->dispatch('open-modal', id: 'rka-item-picker-modal');
    }

    public function selectRkaItem(int $rkaItemId): void
    {
        $header = $this->rkaHeader;
        if (! $header) {
            return;
        }

        $alreadySelected = collect($this->items)
            ->pluck('rka_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->contains($rkaItemId);

        if ($alreadySelected) {
            Notification::make()
                ->title('Item Sudah Dipilih')
                ->body('Item RKA tersebut sudah ada pada Rincian Kebutuhan. Ubah volume pada baris yang tersedia.')
                ->warning()
                ->send();

            return;
        }

        $excludeId = property_exists($this, 'record') && $this->record ? $this->record->id : null;
        $selected = app(FundRequestBudgetService::class)
            ->getSelectableRkaItems($header, excludeFundRequestId: $excludeId, onlyWithRemainingBalance: true)
            ->firstWhere('rka_item_id', $rkaItemId);

        if (! $selected) {
            Notification::make()
                ->title('Item Tidak Tersedia')
                ->body('Item RKA tidak ditemukan atau sisa anggarannya telah habis.')
                ->danger()
                ->send();

            return;
        }

        $unitPrice = (float) $selected['approved_unit_price'];
        $maxVolByRemaining = $unitPrice > 0
            ? floor(((float) $selected['remaining_amount'] / $unitPrice) * 100) / 100
            : (float) $selected['approved_volume'];
        $defaultVol = min(1.0, max(0.01, $maxVolByRemaining));
        $defaultTotal = round($defaultVol * $unitPrice, 2);

        $this->items[] = [
            'id' => null,
            'rka_item_id' => $selected['rka_item_id'],
            'account_id' => $selected['account_id'],
            'account_code' => $selected['account_code'],
            'account_name' => $selected['account_name'],
            'description' => $selected['description'],
            'approved_volume' => (float) $selected['approved_volume'],
            'approved_total' => (float) $selected['approved_total'],
            'remaining_amount' => (float) $selected['remaining_amount'],
            'volume' => $defaultVol,
            'unit' => $selected['unit'],
            'unit_price' => $unitPrice,
            'total_price' => $defaultTotal,
        ];

        $this->dispatch('close-modal', id: 'rka-item-picker-modal');
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $index => $item) {
            $vol = max(0.0, (float) ($item['volume'] ?? 0));
            $price = (float) ($item['unit_price'] ?? 0);
            $this->items[$index]['total_price'] = round($vol * $price, 2);
        }
    }

    public function getTotalRequestedProperty(): float
    {
        return round(array_sum(array_column($this->items, 'total_price')), 2);
    }

    /**
     * Hitung Rekapitulasi per Kode Akun secara Realtime (Bagian C) dari RKA Approved.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccountSummariesProperty(): array
    {
        $excludeId = property_exists($this, 'record') && $this->record ? $this->record->id : null;

        return app(FundRequestBudgetService::class)->buildAccountSummaries(
            rkaHeader: $this->rkaHeader,
            selectedRows: $this->items,
            excludeFundRequestId: $excludeId,
        );
    }
}
