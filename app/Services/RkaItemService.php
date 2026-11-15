<?php

namespace App\Services;

use App\Imports\RkaImportFormatExcel;
use App\Models\Account;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class RkaItemService
{
    /**
     * Memuat record RKA beserta relasi yang diperlukan halaman.
     */
    public function loadRecord(
        int|string $recordId,
    ): RkaHeader {
        return RkaHeader::query()
            ->with([
                'schedule',
                'department',
                'setting',
                'indicators.account',
                'items.account',
            ])
            ->findOrFail($recordId);
    }

    /**
     * Menghitung hierarki akun dan item belanja untuk indikator aktif.
     *
     * @return array{
     *     level1Accounts: Collection,
     *     allAccounts: Collection,
     *     itemsByAccount: Collection,
     *     calculateAccountTotal: \Closure,
     *     totalUsulan: int|float
     * }
     */
    public function calculateBudgetHierarchy(
        int|string|null $indicatorId,
    ): array {
        if (! $indicatorId) {
            return [
                'level1Accounts' => collect(),
                'allAccounts' => collect(),
                'itemsByAccount' => collect(),
                'calculateAccountTotal' => fn ($account): int => 0,
                'totalUsulan' => 0,
            ];
        }

        $items = RkaItem::query()
            ->where('rka_indicator_id', $indicatorId)
            ->with('account.parent.parent')
            ->get();

        // Kumpulkan akun yang digunakan beserta seluruh akun induknya.
        $accountIds = collect();

        foreach ($items as $item) {
            $account = $item->account;

            while ($account) {
                $accountIds->push($account->id);
                $account = $account->parent;
            }
        }

        $allAccounts = Account::query()
            ->whereIn('id', $accountIds->unique())
            ->get();

        $itemsByAccount = $items->groupBy('account_id');

        // Kalkulasi total akun secara rekursif, termasuk akun anak.
        $calculateAccountTotal = function ($account) use (
            &$calculateAccountTotal,
            $allAccounts,
            $itemsByAccount,
        ) {
            $directTotal = $itemsByAccount
                ->get($account->id, collect())
                ->sum('proposed_total');

            $children = $allAccounts->where(
                'parent_id',
                $account->id,
            );

            $childrenTotal = 0;

            foreach ($children as $child) {
                $childrenTotal += $calculateAccountTotal($child);
            }

            return $directTotal + $childrenTotal;
        };

        return [
            'level1Accounts' => $allAccounts
                ->where('level', 1)
                ->sortBy('code')
                ->values(),

            'allAccounts' => $allAccounts,

            'itemsByAccount' => $itemsByAccount,

            'calculateAccountTotal' => $calculateAccountTotal,

            'totalUsulan' => $items->sum('proposed_total'),
        ];
    }

    /**
     * Submit RKA dan mengunci dokumen.
     */
    public function submitRka(RkaHeader $record): void
    {
        DB::transaction(function () use ($record): void {
            $rka = RkaHeader::query()
                ->lockForUpdate()
                ->findOrFail($record->id);

            if ($rka->is_locked || strtolower((string) $rka->status) === 'submitted') {
                throw new InvalidArgumentException(
                    'RKA sudah dikunci atau sudah diajukan.',
                );
            }

            $oldStatus = $rka->status;

            $rka->update([
                'status' => 'submitted',
                'is_locked' => true,
            ]);

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_SUBMIT_RKA,
                headerId: $rka->id,
                subject: $rka,
                description: 'RKA diajukan oleh pemohon.',
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'submitted'],
            );
        });

        $record->refresh();
    }

    /**
     * Membuat program dan matriks indikator baru.
     */
    public function addProgram(
        RkaHeader $record,
        array $data,
    ): RkaIndicator {
        return DB::transaction(function () use (
            $record,
            $data,
        ): RkaIndicator {
            $rka = RkaHeader::query()
                ->lockForUpdate()
                ->findOrFail($record->id);

            $this->ensureEditable($rka);

            $indicator = RkaIndicator::create([
                'rka_header_id' => $rka->id,
                'account_id' => $data['account_id'],
                'program_name' => $data['program_name'],
                'activity_name' => $data['activity_name'],
                'location' => $data['location'],
                'indicator_matrix' => $data['indicator_matrix'] ?? [],
            ]);

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_ADD_INDICATOR,
                headerId: $rka->id,
                subject: $indicator,
                description: "Program & indikator \"{$indicator->program_name}\" ditambahkan.",
                newValues: [
                    'rka_indicator_id' => $indicator->id,
                    'program_name' => $indicator->program_name,
                    'activity_name' => $indicator->activity_name,
                ],
            );

            return $indicator;
        });
    }

    /**
     * Menambahkan item belanja pada indikator tertentu.
     */
    public function addItem(
        RkaHeader $record,
        array $data,
        int|string $indicatorId,
    ): RkaItem {
        return DB::transaction(function () use (
            $record,
            $data,
            $indicatorId,
        ): RkaItem {
            $rka = RkaHeader::query()
                ->lockForUpdate()
                ->findOrFail($record->id);

            $this->ensureEditable($rka);

            $indicator = RkaIndicator::query()
                ->where('rka_header_id', $rka->id)
                ->findOrFail($indicatorId);

            $account = Account::query()
                ->findOrFail($data['account_id']);

            // Pastikan akun berada dalam kelompok rekening indikator.
            $indicatorAccount = $indicator->account;

            if (
                ! $indicatorAccount
                || ! str_starts_with(
                    (string) $account->code,
                    (string) $indicatorAccount->code,
                )
            ) {
                throw new InvalidArgumentException(
                    'Akun belanja tidak sesuai dengan kelompok rekening indikator.',
                );
            }

            $item = RkaItem::create([
                'rka_header_id' => $rka->id,
                'rka_indicator_id' => $indicator->id,
                'account_id' => $account->id,
                'form_type' => $data['form_type'],
                'description' => $data['description'],
                'proposed_volume' => $data['proposed_volume'],
                'unit_symbol' => $data['unit_symbol'],
                'unit_price' => $data['unit_price'],
                'status' => RkaItem::STATUS_PENDING,
            ]);

            $indicator->recalculateMasukanTargetValue();
            $rka->calculatePaguRka();

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_ADD_ITEM,
                headerId: $rka->id,
                subject: $item,
                description: "Item anggaran \"{$item->description}\" ditambahkan.",
                newValues: [
                    'item_id' => $item->id,
                    'account_id' => $item->account_id,
                    'proposed_volume' => $item->proposed_volume,
                    'unit_price' => $item->unit_price,
                ],
            );

            return $item;
        });
    }

    /**
     * Memperbarui item belanja yang sudah ada.
     */
    public function updateItem(
        RkaHeader $record,
        int|string $itemId,
        array $data,
    ): RkaItem {
        return DB::transaction(function () use (
            $record,
            $itemId,
            $data,
        ): RkaItem {
            $rka = RkaHeader::query()
                ->lockForUpdate()
                ->findOrFail($record->id);

            $this->ensureEditable($rka);

            $item = RkaItem::query()
                ->where('rka_header_id', $rka->id)
                ->findOrFail($itemId);

            $account = Account::query()
                ->findOrFail($data['account_id']);

            $indicator = RkaIndicator::query()
                ->where('rka_header_id', $rka->id)
                ->findOrFail($item->rka_indicator_id);

            if (
                ! $indicator->account
                || ! str_starts_with(
                    (string) $account->code,
                    (string) $indicator->account->code,
                )
            ) {
                throw new InvalidArgumentException(
                    'Akun belanja tidak sesuai dengan kelompok rekening indikator.',
                );
            }

            $oldValues = [
                'account_id' => $item->account_id,
                'description' => $item->description,
                'proposed_volume' => $item->proposed_volume,
                'unit_symbol' => $item->unit_symbol,
                'unit_price' => $item->unit_price,
            ];

            $item->update([
                'account_id' => $account->id,
                'description' => $data['description'],
                'proposed_volume' => $data['proposed_volume'],
                'unit_symbol' => $data['unit_symbol'],
                'unit_price' => $data['unit_price'],
            ]);

            $indicator->recalculateMasukanTargetValue();
            $rka->calculatePaguRka();

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_UPDATE_ITEM,
                headerId: $rka->id,
                subject: $item,
                description: "Item anggaran \"{$data['description']}\" diperbarui.",
                oldValues: $oldValues,
                newValues: [
                    'item_id' => $item->id,
                    'account_id' => $account->id,
                    'description' => $data['description'],
                    'proposed_volume' => $data['proposed_volume'],
                    'unit_symbol' => $data['unit_symbol'],
                    'unit_price' => $data['unit_price'],
                ],
            );

            return $item->refresh();
        });
    }

    /**
     * Menghapus satu item belanja.
     */
    public function deleteItem(
        RkaHeader $record,
        int|string $itemId,
    ): void {
        DB::transaction(function () use (
            $record,
            $itemId,
        ): void {
            $rka = RkaHeader::query()
                ->lockForUpdate()
                ->findOrFail($record->id);

            $this->ensureEditable($rka);

            $item = RkaItem::query()
                ->where('rka_header_id', $rka->id)
                ->findOrFail($itemId);

            $indicator = RkaIndicator::query()
                ->where('rka_header_id', $rka->id)
                ->find($item->rka_indicator_id);

            $oldValues = [
                'item_id' => $item->id,
                'description' => $item->description,
                'proposed_volume' => $item->proposed_volume,
                'unit_price' => $item->unit_price,
            ];

            $item->delete();

            $indicator?->recalculateMasukanTargetValue();

            $rka->calculatePaguRka();

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_DELETE_ITEM,
                headerId: $rka->id,
                subject: $item,
                description: "Item anggaran \"{$oldValues['description']}\" dihapus.",
                oldValues: $oldValues,
            );
        });
    }

    /**
     * Menghapus seluruh item belanja pada RKA.
     */
    public function clearAllItems(RkaHeader $record): int
    {
        return DB::transaction(function () use ($record): int {
            $rka = RkaHeader::query()
                ->lockForUpdate()
                ->findOrFail($record->id);

            $this->ensureEditable($rka);

            $indicatorIds = RkaItem::query()
                ->where('rka_header_id', $rka->id)
                ->whereNotNull('rka_indicator_id')
                ->distinct()
                ->pluck('rka_indicator_id');

            $deletedCount = RkaItem::query()
                ->where('rka_header_id', $rka->id)
                ->delete();

            foreach ($indicatorIds as $indicatorId) {
                $indicator = RkaIndicator::query()
                    ->where('rka_header_id', $rka->id)
                    ->find($indicatorId);

                $indicator?->recalculateMasukanTargetValue();
            }

            $rka->calculatePaguRka();

            if ($deletedCount > 0) {
                app(RkaActivityLogService::class)->log(
                    action: RkaLog::ACTION_CLEAR_ITEMS,
                    headerId: $rka->id,
                    subject: $rka,
                    description: "Seluruh item anggaran ({$deletedCount} item) dihapus.",
                    newValues: ['deleted_count' => $deletedCount],
                );
            }

            return $deletedCount;
        });
    }

    /**
     * Mengimpor item belanja dari Excel.
     *
     * File diharapkan sudah divalidasi oleh form Filament.
     * Nilai indicatorId null berarti impor untuk seluruh indikator.
     */
    public function importExcel(
        RkaHeader $record,
        UploadedFile $file,
        ?int $indicatorId = null,
    ): void {
        $rka = RkaHeader::query()->findOrFail($record->id);

        $this->ensureEditable($rka);

        if ($indicatorId !== null) {
            RkaIndicator::query()
                ->where('rka_header_id', $rka->id)
                ->findOrFail($indicatorId);
        }

        DB::transaction(function () use ($rka, $file, $indicatorId): void {
            Excel::import(
                new RkaImportFormatExcel(
                    $rka->id,
                    $indicatorId,
                ),
                $file->getRealPath(),
            );

            $rka->calculatePaguRka();

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_IMPORT_ITEMS,
                headerId: $rka->id,
                subject: $rka,
                description: 'Item anggaran diimpor melalui Excel.',
                newValues: ['indicator_id' => $indicatorId],
            );
        });

        $record->refresh();
    }

    /**
     * Menghitung ulang pagu RKA secara manual.
     */
    public function calculatePagu(RkaHeader $record): void
    {
        $record->calculatePaguRka();

        $record->refresh();
    }

    /**
     * Memastikan dokumen RKA masih dapat diubah.
     *
     * Digunakan sebelum operasi tambah, edit, dan hapus.
     */
    private function ensureEditable(RkaHeader $record): void
    {
        if (
            $record->is_locked
            || strtolower((string) $record->status) === 'submitted'
        ) {
            throw new InvalidArgumentException(
                'RKA sudah diajukan dan tidak dapat diubah.',
            );
        }
    }
}
