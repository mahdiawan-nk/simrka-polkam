<?php

namespace App\Services;

use App\Imports\RkaImportFormatExcel;
use App\Models\Account;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
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
                'calculateAccountTotal' => fn($account): int => 0,
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

            if ($rka->is_locked || $rka->rka_status === 'submitted') {
                throw new InvalidArgumentException(
                    'RKA sudah dikunci atau sudah diajukan.',
                );
            }

            $rka->update([
                'rka_status' => 'submitted',
                'is_locked' => true,
            ]);
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

            return RkaIndicator::create([
                'rka_header_id' => $rka->id,
                'account_id' => $data['account_id'],
                'program_name' => $data['program_name'],
                'activity_name' => $data['activity_name'],
                'location' => $data['location'],
                'indicator_matrix' => $data['indicator_matrix'] ?? [],
            ]);
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

            $item->update([
                'account_id' => $account->id,
                'description' => $data['description'],
                'proposed_volume' => $data['proposed_volume'],
                'unit_symbol' => $data['unit_symbol'],
                'unit_price' => $data['unit_price'],
            ]);

            $indicator->recalculateMasukanTargetValue();
            $rka->calculatePaguRka();

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

            $item->delete();

            $indicator?->recalculateMasukanTargetValue();

            $rka->calculatePaguRka();
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

        Excel::import(
            new RkaImportFormatExcel(
                $rka->id,
                $indicatorId,
            ),
            $file->getRealPath(),
        );

        $rka->calculatePaguRka();

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
            || $record->rka_status === 'submitted'
        ) {
            throw new InvalidArgumentException(
                'RKA sudah diajukan dan tidak dapat diubah.',
            );
        }
    }
}
