<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RkaReviewService
{
    /**
     * Memuat dan menyinkronkan data RKA Header beserta relasinya berdasarkan department_id yang dipilih.
     */
    public function loadRkaRecord(RkaHeader $currentRecord, ?int $selectedDepartmentId): RkaHeader
    {
        $targetDepartmentId = $selectedDepartmentId ?? $currentRecord->department_id;

        $header = RkaHeader::where('schedule_id', $currentRecord->schedule_id)
            ->where('department_id', $targetDepartmentId)
            ->first() ?? $currentRecord;

        $header->load([
            'schedule',
            'department',
            'setting',
            'indicators.account',
            'indicators.items',
            'items.account',
            'adjustments.account',
            'adjustments.destinationUnit',
            'adjustments.targetUnit',
            'incomingMovedAdjustments.rkaHeader.department',
            'incomingMovedAdjustments.account',
            'incomingSharedCosts.rkaHeader.department',
            'incomingSharedCosts.account',
        ]);

        if (
            strtolower((string) $header->status) === 'submitted'
            && auth()->user()?->hasAnyRole(['keuangan', 'wadir_keuangan', 'direktur'])
        ) {
            DB::transaction(function () use ($header): void {
                $oldStatus = $header->status;
                $header->update(['status' => 'IN_REVIEW']);

                app(RkaActivityLogService::class)->log(
                    action: RkaLog::ACTION_START_REVIEW,
                    headerId: $header->id,
                    subject: $header,
                    description: 'RKA mulai direview.',
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => RkaHeader::STATUS_IN_REVIEW],
                );
            });
        }

        return $header;
    }

    /**
     * Menghitung Ringkasan Eksekutif (Summary Data).
     */
    public function calculateSummaryData(RkaHeader $record): array
    {
        $usulanAwal = (float) $record->pagu_budget;

        $tambahanPusat = (float) $record->adjustments()
            ->where('section_type', 'B')
            ->sum('proposed_amount');

        $dipindahkan = (float) $record->adjustments()
            ->where('section_type', 'A')
            ->where('action_type', 'MOVED')
            ->sum('proposed_amount');

        $postCost = (float) $record->adjustments()
            ->where('section_type', 'A')
            ->where('action_type', 'BUDGETED_ELSEWHERE')
            ->sum('proposed_amount');

        $usulanDibahas = (float) $record->items()
            ->whereIn('status', ['REJECTED_WITH_ADJUSTMENT', 'REJECTED', 'APPROVED'])
            ->sum('proposed_total');

        $adjustmentPriceAndVolume = ($usulanAwal - $dipindahkan - $postCost + $tambahanPusat) - $usulanDibahas;
        $disetujuiSetelahReview = (float) $record->calculateApprovedTotal();

        $percentage = $usulanDibahas > 0 ? ($disetujuiSetelahReview / $usulanDibahas) * 100 : 0;

        return [
            'usulan_awal' => $usulanAwal,
            'dipindahkan' => $dipindahkan,
            'pos_cost' => $postCost,
            'adjustment_price' => $adjustmentPriceAndVolume,
            'tambahan_pusat' => $tambahanPusat,
            'usulan_dibahas' => $usulanDibahas,
            'disetujui_setelah_review' => $disetujuiSetelahReview,
            'percentage' => round($percentage, 1),
        ];
    }

    /**
     * Menghitung Hierarki Akun dan Subtotal Berjenjang.
     */
    public function calculateAccountHierarchy(RkaHeader $record): array
    {
        $items = RkaItem::where('rka_header_id', $record->id)
            ->with('account.parent.parent')
            ->get();

        $itemAccountIds = collect();
        foreach ($items as $item) {
            if (! $item->account) {
                continue;
            }
            $curr = $item->account;
            while ($curr) {
                $itemAccountIds->push($curr->id);
                $curr = $curr->parent;
            }
        }

        $masterLevel1AccountIds = Account::where('level', 1)->pluck('id');
        $allAccountIds = $itemAccountIds->merge($masterLevel1AccountIds)->unique();

        $allAccounts = Account::whereIn('id', $allAccountIds)->get();
        $itemsByAccount = $items->groupBy('account_id');

        $getItemProposedAmount = function ($item): float {
            $status = $item->status ?? 'PENDING';
            if (in_array($status, ['APPROVED', 'ACCEPTED', 'REDUCED', 'REJECTED_WITH_ADJUSTMENT'], true)) {
                return (float) $item->proposed_total;
            }

            if ($status === 'TRIMMED' && ((float) ($item->approved_volume ?? 0) > 0 || (float) ($item->approved_total ?? 0) > 0)) {
                return (float) $item->proposed_total;
            }

            return 0.00;
        };

        $getItemApprovedAmount = function ($item): float {
            $status = $item->status ?? 'PENDING';

            if ($status === 'REJECTED_WITH_ADJUSTMENT') {
                return (float) ($item->approved_total ?? 0);
            }

            if ($status === 'TRIMMED') {
                return ((float) ($item->approved_volume ?? 0) > 0 || (float) ($item->approved_total ?? 0) > 0)
                    ? (float) ($item->approved_total ?? 0)
                    : 0.00;
            }

            if (in_array($status, ['APPROVED', 'ACCEPTED', 'REDUCED'], true)) {
                return (float) ($item->approved_total ?? $item->proposed_total);
            }

            return 0.00;
        };

        $grandTotalUsulan = (float) $items->sum(fn ($i) => $getItemProposedAmount($i));
        $grandTotalApproved = (float) $items->sum(fn ($i) => $getItemApprovedAmount($i));

        $calculateTotals = function ($account) use (&$calculateTotals, $allAccounts, $itemsByAccount, $getItemProposedAmount, $getItemApprovedAmount, $grandTotalApproved) {
            $accItems = $itemsByAccount[$account->id] ?? collect();

            $directUsulan = (float) $accItems->sum(fn ($i) => $getItemProposedAmount($i));
            $directApproved = (float) $accItems->sum(fn ($i) => $getItemApprovedAmount($i));

            $children = $allAccounts->where('parent_id', $account->id);
            $childrenUsulan = 0;
            $childrenApproved = 0;

            foreach ($children as $child) {
                $res = $calculateTotals($child);
                $childrenUsulan += $res['usulan'];
                $childrenApproved += $res['approved'];
            }

            $totalUsulan = $directUsulan + $childrenUsulan;
            $totalApproved = $directApproved + $childrenApproved;
            $selisih = $totalApproved - $totalUsulan;
            $percentage = $grandTotalApproved > 0 ? ($totalApproved / $grandTotalApproved) * 100 : 0;

            return [
                'usulan' => $totalUsulan,
                'approved' => $totalApproved,
                'selisih' => $selisih,
                'percentage' => round($percentage, 1),
            ];
        };

        return [
            'level1Accounts' => $allAccounts->where('level', 1)->sortBy('code'),
            'allAccounts' => $allAccounts,
            'itemsByAccount' => $itemsByAccount,
            'calculateTotals' => $calculateTotals,
            'grandTotalUsulan' => $grandTotalUsulan,
            'grandTotalApproved' => $grandTotalApproved,
            'grandTotalSelisih' => $grandTotalApproved - $grandTotalUsulan,
        ];
    }

    /**
     * Menghitung Kontrol Pagu Anggaran.
     */
    public function calculateKontrolPagu(RkaHeader $record, array $summary): array
    {
        $paguUnit = (float) ($record->pagu_budget ?? 0);

        $totalBelanjaUsulan = (float) ($summary['grandTotalUsulan'] ?? 0);
        $totalBelanjaReview = (float) ($summary['grandTotalApproved'] ?? 0);

        $sisaPaguUsulan = $paguUnit - $totalBelanjaUsulan;
        $sisaPaguReview = $paguUnit - $totalBelanjaReview;

        $statusUsulan = $sisaPaguUsulan < 0 ? 'MELEBIHI PAGU' : 'SESUAI PAGU';
        $statusReview = $sisaPaguReview < 0 ? 'MELEBIHI PAGU' : 'SESUAI PAGU';

        $items = $record->items;
        $trimmedItems = $items->filter(function ($item) {
            return $item->price_trimed != 0 && $item->status == 'APPROVED';
        });

        $countDipangkas = $trimmedItems->count();
        $totalNilaiPemangkasan = (float) $trimmedItems->sum(function ($item) {
            $approved = (float) ($item->approved_total ?? 0);

            return max(0, (float) $item->proposed_total - $approved);
        });

        return [
            'pagu_unit' => $paguUnit,
            'total_belanja_usulan' => $totalBelanjaUsulan,
            'total_belanja_review' => $totalBelanjaReview,
            'sisa_pagu_usulan' => $sisaPaguUsulan,
            'sisa_pagu_review' => $sisaPaguReview,
            'status_usulan' => $statusUsulan,
            'status_review' => $statusReview,
            'count_dipangkas' => $countDipangkas,
            'total_nilai_pemangkasan' => $totalNilaiPemangkasan,
        ];
    }

    /**
     * Rekapitulasi Berdasarkan Kelompok Belanja.
     */
    public function calculateRekapByKelompok(RkaHeader $record): array
    {
        $categories = [
            '5.1' => 'Belanja Pegawai',
            '5.2' => 'Belanja Barang dan Jasa',
            '5.3' => 'Belanja Modal',
            '5.4' => 'Belanja Hibah',
            '5.5' => 'Belanja Pajak',
            '2.1' => 'Pembayaran Kewajiban Jangka Pendek',
        ];

        $rekap = [];
        $totalUsulanAwal = 0;
        $totalUsulanReview = 0;
        $totalApproved = 0;

        $record->loadMissing(['items.account', 'adjustments']);

        foreach ($categories as $code => $label) {
            $categoryItems = $record->items->filter(function ($item) use ($code) {
                return str_starts_with($item->account?->code ?? '', $code);
            });

            $usulanAwal = $categoryItems->sum(function ($item) use ($record) {
                $adjustment = $record->adjustments
                    ->where('rka_item_id', $item->id)
                    ->where('section_type', RkaAdjustment::SECTION_A)
                    ->first();

                if ($adjustment && $adjustment->proposed_amount > 0) {
                    return $adjustment->proposed_amount;
                }

                return $item->proposed_total;
            });

            $inReviewItems = $categoryItems->filter(function ($item): bool {
                $status = $item->status ?? 'PENDING';
                if (in_array($status, ['APPROVED', 'ACCEPTED', 'REDUCED', 'REJECTED_WITH_ADJUSTMENT', 'REJECTED'], true)) {
                    return true;
                }

                return $status === 'TRIMMED' && ((float) ($item->approved_volume ?? 0) > 0 || (float) ($item->approved_total ?? 0) > 0);
            });
            $usulanReview = $inReviewItems->sum('proposed_total');
            $approved = $categoryItems->sum('approved_total');

            $pct = $usulanReview > 0 ? ($approved / $usulanReview) * 100 : 0;

            $totalUsulanAwal += $usulanAwal;
            $totalUsulanReview += $usulanReview;
            $totalApproved += $approved;

            $rekap[] = [
                'code' => $code,
                'label' => $label,
                'usulan' => $usulanAwal,
                'usulan_review' => $usulanReview,
                'approved' => $approved,
                'percentage' => round($pct, 1),
            ];
        }

        return [
            'items' => $rekap,
            'total_usulan' => $totalUsulanAwal,
            'total_usulan_review' => $totalUsulanReview,
            'total_approved' => $totalApproved,
            'total_percentage' => $totalUsulanReview > 0 ? round(($totalApproved / $totalUsulanReview) * 100, 1) : 0,
        ];
    }

    /**
     * Menghitung Struktur Hierarki Anggaran per Indikator Terpilih.
     */
    public function calculateBudgetHierarchy(?string $activeIndicatorTab, bool $showAllItems): array
    {
        if (! $activeIndicatorTab) {
            return [
                'level1Accounts' => collect(),
                'allAccounts' => collect(),
                'itemsByAccount' => collect(),
                'calculateAccountTotals' => fn () => ['usulan' => 0, 'approved' => 0],
                'totalUsulan' => 0,
                'totalApproved' => 0,
            ];
        }

        $itemsQuery = RkaItem::where('rka_indicator_id', $activeIndicatorTab)
            ->with('account.parent.parent');

        if (! $showAllItems) {
            $itemsQuery->where(function ($query): void {
                $query->whereIn('status', [
                    RkaItem::STATUS_APPROVED,
                    RkaItem::STATUS_ACCEPTED,
                    RkaItem::STATUS_REDUCED,
                ])->orWhere(function ($trimmedQuery): void {
                    $trimmedQuery->where('status', 'TRIMMED')
                        ->where(function ($approvalQuery): void {
                            $approvalQuery->where('approved_volume', '>', 0)
                                ->orWhere('approved_total', '>', 0);
                        });
                });
            });
        }

        $items = $itemsQuery->get();

        $accountIds = collect();
        foreach ($items as $item) {
            if (! $item->account) {
                continue;
            }
            $curr = $item->account;
            while ($curr) {
                $accountIds->push($curr->id);
                $curr = $curr->parent;
            }
        }

        $allAccounts = Account::whereIn('id', $accountIds->unique())->get();
        $itemsByAccount = $items->groupBy('account_id');

        $calculateAccountTotals = function ($account) use (&$calculateAccountTotals, $allAccounts, $itemsByAccount) {
            $directUsulan = isset($itemsByAccount[$account->id]) ? $itemsByAccount[$account->id]->sum('proposed_total') : 0;
            $directApproved = isset($itemsByAccount[$account->id]) ? $itemsByAccount[$account->id]->sum('approved_total') : 0;

            $children = $allAccounts->where('parent_id', $account->id);
            $childrenUsulan = 0;
            $childrenApproved = 0;

            foreach ($children as $child) {
                $res = $calculateAccountTotals($child);
                $childrenUsulan += $res['usulan'];
                $childrenApproved += $res['approved'];
            }

            return [
                'usulan' => $directUsulan + $childrenUsulan,
                'approved' => $directApproved + $childrenApproved,
            ];
        };

        return [
            'level1Accounts' => $allAccounts->where('level', 1)->sortBy('code'),
            'allAccounts' => $allAccounts,
            'itemsByAccount' => $itemsByAccount,
            'calculateAccountTotals' => $calculateAccountTotals,
            'totalUsulan' => $items->sum('proposed_total'),
            'totalUsulanAfterReview' => $items->sum('proposed_total'),
            'totalApproved' => $items->sum('approved_total'),
        ];
    }

    /**
     * Memproses Review / Adjustment pada satu Item RKA.
     */
    public function reviewItem(RkaHeader $record, array $data, array $arguments): void
    {
        DB::transaction(function () use ($record, $data, $arguments) {
            $itemId = $data['item_id'] ?? $arguments['item_id'] ?? null;
            $item = RkaItem::where('rka_header_id', $record->id)->findOrFail($itemId);
            $actionType = $data['action_type'];

            $originalProposedVolume = (float) $item->proposed_volume;
            $originalUnitPrice = (float) $item->unit_price;
            $originalProposedTotal = $originalProposedVolume * $originalUnitPrice;

            if ($actionType === 'REJECTED') {
                $newUnitPrice = $originalUnitPrice;
            } else {
                $newUnitPrice = ! empty($data['approved_price']) ? (float) $data['approved_price'] : $originalUnitPrice;
            }

            $approvedVolume = $actionType === 'APPROVED' ? (float) ($data['approved_volume'] ?? 0) : 0;
            $approvedTotal = $actionType === 'APPROVED'
                ? (float) ($data['approved_total'] ?? ($approvedVolume * $newUnitPrice))
                : 0;

            $isTrimmed = ($actionType !== 'APPROVED') || ($approvedTotal < $originalProposedTotal);

            $oldItemSnapshot = [
                'status' => $item->status,
                'approved_volume' => $item->approved_volume,
                'approved_total' => $item->approved_total,
            ];

            $item->update([
                'approved_volume' => $approvedVolume,
                'approved_total' => $approvedTotal,
                'unit_symbol' => $data['unit_symbol'] ?? $item->unit_symbol,
                'review_notes' => $data['review_notes'] ?? null,
                'price_trimed' => $isTrimmed ? $newUnitPrice : 0,
                'is_trimmed' => $isTrimmed,
                'status' => $actionType,
            ]);

            $proposedVolume = $originalProposedVolume;
            $unitSymbol = $data['unit_symbol'] ?? $item->unit_symbol ?? 'Satuan';
            $adjustedProposedAmount = $originalProposedTotal;

            if ($actionType !== 'APPROVED') {
                if ($actionType === 'MOVED') {
                    $destinationUnitId = $data['destination_unit_id'] ?? null;

                    RkaAdjustment::updateOrCreate(
                        [
                            'rka_header_id' => $record->id,
                            'rka_item_id' => $item->id,
                            'section_type' => 'A',
                        ],
                        [
                            'rka_indicator_id' => $item->rka_indicator_id,
                            'account_id' => $item->account_id,
                            'section_name' => 'Penyesuaian Unit Asal (Dialihkan)',
                            'action_type' => 'MOVED',
                            'description' => $item->description,
                            'destination_unit_id' => $destinationUnitId,
                            'target_unit_id' => null,
                            'proposed_volume' => $proposedVolume,
                            'unit_symbol' => $unitSymbol,
                            'unit_price' => $newUnitPrice,
                            'proposed_amount' => $adjustedProposedAmount,
                            'approved_amount' => 0,
                            'notes' => $data['review_notes'],
                            'adjusted_by' => Auth::id(),
                        ]
                    );

                    if ($destinationUnitId) {
                        $targetRkaHeader = RkaHeader::where('department_id', $destinationUnitId)
                            ->where('schedule_id', $record->schedule_id)
                            ->where('setting_id', $record->setting_id)
                            ->first();

                        if ($targetRkaHeader) {
                            RkaAdjustment::updateOrCreate(
                                [
                                    'rka_header_id' => $targetRkaHeader->id,
                                    'rka_item_id' => $item->id,
                                    'section_type' => 'B',
                                ],
                                [
                                    'rka_indicator_id' => null,
                                    'account_id' => $item->account_id,
                                    'section_name' => 'Penyesuaian Unit Pengelola',
                                    'action_type' => 'MOVED',
                                    'description' => $item->description,
                                    'destination_unit_id' => null,
                                    'target_unit_id' => $record->department_id,
                                    'proposed_volume' => $proposedVolume,
                                    'unit_symbol' => $unitSymbol,
                                    'unit_price' => $newUnitPrice,
                                    'proposed_amount' => $adjustedProposedAmount,
                                    'approved_amount' => $data['approved_total'],
                                    'notes' => $data['review_notes'],
                                    'adjusted_by' => Auth::id(),
                                ]
                            );
                        }
                    }
                } else {
                    $sectionAStatuses = ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE', 'PENDING'];
                    $sectionType = in_array($actionType, $sectionAStatuses, true) ? 'A' : 'B';
                    $sectionName = $sectionType === 'A' ? 'Penyesuaian Unit Asal' : 'Penyesuaian Unit Pengelola';

                    // Hapus adjustment lintas header (misal bekas MOVED) dan section lawan pada header ini agar tidak terjadi duplikasi
                    RkaAdjustment::where('rka_item_id', $item->id)
                        ->where(function ($query) use ($record, $sectionType): void {
                            $query->where('rka_header_id', '!=', $record->id)
                                ->orWhere('section_type', '!=', $sectionType);
                        })
                        ->delete();

                    RkaAdjustment::updateOrCreate(
                        [
                            'rka_header_id' => $record->id,
                            'rka_item_id' => $item->id,
                            'section_type' => $sectionType,
                        ],
                        [
                            'rka_indicator_id' => $item->rka_indicator_id,
                            'account_id' => $item->account_id,
                            'section_name' => $sectionName,
                            'action_type' => $actionType,
                            'description' => $item->description,
                            'destination_unit_id' => null,
                            'target_unit_id' => null,
                            'proposed_volume' => $proposedVolume,
                            'unit_symbol' => $unitSymbol,
                            'unit_price' => $newUnitPrice,
                            'proposed_amount' => $adjustedProposedAmount,
                            'approved_amount' => in_array($actionType, $sectionAStatuses, true) ? 0 : ($data['approved_total'] ?? 0),
                            'notes' => $data['review_notes'],
                            'adjusted_by' => Auth::id(),
                        ]
                    );
                }
            } else {
                RkaAdjustment::where('rka_item_id', $item->id)->delete();
            }

            if ($indicator = RkaIndicator::find($item->rka_indicator_id)) {
                $indicator->recalculateMasukanTargetValue();
            }

            if ($actionType === 'MOVED') {
                $destinationUnitId = $data['destination_unit_id'] ?? null;
                $destName = $destinationUnitId ? (Department::whereKey($destinationUnitId)->value('name') ?? 'unit tujuan') : 'unit tujuan';

                app(RkaActivityLogService::class)->log(
                    action: RkaLog::ACTION_ADJUSTMENT_MOVE,
                    headerId: $record->id,
                    subject: $item,
                    description: "Item \"{$item->description}\" dipindahkan ke {$destName}.",
                    oldValues: $oldItemSnapshot,
                    newValues: [
                        'item_id' => $item->id,
                        'status' => $actionType,
                        'destination_unit_id' => $destinationUnitId,
                    ],
                );

                if (isset($targetRkaHeader) && $targetRkaHeader) {
                    $originName = $record->department->name ?? 'unit asal';
                    app(RkaActivityLogService::class)->log(
                        action: RkaLog::ACTION_ADJUSTMENT_MOVE,
                        headerId: $targetRkaHeader->id,
                        subject: $item,
                        description: "Menerima pemindahan item \"{$item->description}\" dari {$originName}.",
                        newValues: [
                            'rka_item_id' => $item->id,
                            'source_department_id' => $record->department_id,
                        ],
                    );
                }
            } else {
                app(RkaActivityLogService::class)->log(
                    action: RkaLog::ACTION_REVIEW_UPDATE,
                    headerId: $record->id,
                    subject: $item,
                    description: "Item anggaran \"{$item->description}\" direview ({$actionType}).",
                    oldValues: $oldItemSnapshot,
                    newValues: [
                        'item_id' => $item->id,
                        'status' => $actionType,
                        'approved_volume' => $approvedVolume,
                        'approved_total' => $approvedTotal,
                    ],
                );
            }
        });
    }

    /**
     * Memasukkan data adjustment (Section B) ke RkaItem unit.
     */
    public function importAdjustmentToItem(RkaHeader $record, int $adjustmentId): bool
    {
        return DB::transaction(function () use ($record, $adjustmentId) {
            $adjustment = RkaAdjustment::find($adjustmentId);

            if (! $adjustment || $adjustment->is_imported) {
                return false;
            }

            $account = Account::with('parent.parent.parent')->find($adjustment->account_id);

            if (! $account) {
                throw new InvalidArgumentException('Akun rekening penyesuaian tidak ditemukan.');
            }

            $parentLevel1 = $account;
            while ($parentLevel1->parent && $parentLevel1->level > 1) {
                $parentLevel1 = $parentLevel1->parent;
            }

            $codeParts = explode('.', (string) $account->code);
            $fallbackFormType = count($codeParts) >= 2
                ? $codeParts[0].'.'.$codeParts[1]
                : (string) $account->code;

            $formType = $parentLevel1->level === 1
                ? (string) $parentLevel1->code
                : $fallbackFormType;

            if ($formType === '') {
                throw new InvalidArgumentException('Kelompok belanja (form_type) tidak dapat ditentukan dari rekening.');
            }

            $rkaIndicatorId = null;

            if ($adjustment->rka_indicator_id) {
                $rkaIndicatorId = RkaIndicator::query()
                    ->where('rka_header_id', $record->id)
                    ->where('id', $adjustment->rka_indicator_id)
                    ->value('id');
            }

            if (! $rkaIndicatorId) {
                $rkaIndicatorId = RkaIndicator::query()
                    ->where('rka_header_id', $record->id)
                    ->where('account_id', $parentLevel1->id)
                    ->value('id');
            }

            if (! $rkaIndicatorId) {
                throw new InvalidArgumentException("Indikator RKA untuk kelompok belanja {$formType} belum tersedia pada RKA tujuan.");
            }

            $unitSymbol = trim((string) ($adjustment->unit_symbol ?: $adjustment->rkaItem?->unit_symbol ?? ''));

            if ($unitSymbol === '') {
                throw new InvalidArgumentException('Satuan (unit_symbol) pada data penyesuaian wajib diisi sebelum diimpor.');
            }

            $newItem = RkaItem::updateOrCreate(
                [
                    'id' => $adjustment->imported_rka_item_id,
                ],
                [
                    'rka_header_id' => $record->id,
                    'rka_indicator_id' => $rkaIndicatorId,
                    'account_id' => $adjustment->account_id,
                    'form_type' => $formType,
                    'description' => $adjustment->description,
                    'proposed_volume' => $adjustment->proposed_volume,
                    'unit_symbol' => $unitSymbol,
                    'unit_price' => $adjustment->unit_price,
                    'approved_volume' => $adjustment->proposed_volume,
                    'approved_total' => $adjustment->approved_amount,
                    'review_notes' => $adjustment->notes ?? 'Hasil limpahan / adjustment Section B',
                    'status' => 'APPROVED',
                    'is_trimmed' => false,
                    'is_imported' => true,
                ]
            );

            $adjustment->update([
                'is_imported' => true,
                'imported_rka_item_id' => $newItem->id,
            ]);

            if ($newItem->rka_indicator_id && ($indicator = RkaIndicator::find($newItem->rka_indicator_id))) {
                $indicator->recalculateMasukanTargetValue();
            }

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_IMPORT_ADJUSTMENT,
                headerId: $record->id,
                subject: $adjustment,
                description: "Penyesuaian \"{$adjustment->description}\" diimpor menjadi item RKA.",
                newValues: [
                    'adjustment_id' => $adjustment->id,
                    'rka_item_id' => $newItem->id,
                ],
            );

            return true;
        });
    }

    /**
     * Menyetujui beberapa item secara bersamaan (Bulk Approve).
     */
    public function bulkApproveItems(RkaHeader $record, array $selectedItemIds): void
    {
        DB::transaction(function () use ($record, $selectedItemIds) {
            $items = RkaItem::where('rka_header_id', $record->id)
                ->whereIn('id', $selectedItemIds)
                ->get();
            $affectedIndicatorIds = [];

            foreach ($items as $item) {
                $item->update([
                    'approved_volume' => $item->proposed_volume,
                    'approved_total' => $item->proposed_total,
                    'is_trimmed' => false,
                    'status' => 'APPROVED',
                ]);

                RkaAdjustment::where('rka_item_id', $item->id)->delete();
                $affectedIndicatorIds[] = $item->rka_indicator_id;
            }

            foreach (array_unique($affectedIndicatorIds) as $indicatorId) {
                if ($indicator = RkaIndicator::find($indicatorId)) {
                    $indicator->recalculateMasukanTargetValue();
                }
            }

            if ($items->isNotEmpty()) {
                app(RkaActivityLogService::class)->log(
                    action: RkaLog::ACTION_BULK_APPROVE,
                    headerId: $record->id,
                    subject: $record,
                    description: "{$items->count()} item anggaran disetujui secara massal.",
                    newValues: [
                        'item_ids' => $items->pluck('id')->all(),
                        'count' => $items->count(),
                    ],
                );
            }
        });
    }

    /**
     * Memindahkan beberapa item secara massal (Bulk Move).
     */
    public function bulkMoveItems(RkaHeader $record, array $selectedItemIds, int $destinationUnitId, string $reviewNotes): void
    {
        DB::transaction(function () use ($record, $selectedItemIds, $destinationUnitId, $reviewNotes) {
            $authUserId = Auth::id();

            $targetRkaHeader = RkaHeader::where('department_id', $destinationUnitId)
                ->where('schedule_id', $record->schedule_id)
                ->where('setting_id', $record->setting_id)
                ->first();

            $items = RkaItem::where('rka_header_id', $record->id)
                ->whereIn('id', $selectedItemIds)
                ->get();
            $affectedIndicatorIds = [];

            foreach ($items as $item) {
                $oldItemSnapshot = [
                    'status' => $item->status,
                    'approved_volume' => $item->approved_volume,
                    'approved_total' => $item->approved_total,
                ];

                $oldItemSnapshot = [
                    'status' => $item->status,
                    'approved_volume' => $item->approved_volume,
                    'approved_total' => $item->approved_total,
                ];

                $item->update([
                    'approved_volume' => 0,
                    'approved_total' => 0,
                    'review_notes' => $reviewNotes,
                    'is_trimmed' => true,
                    'status' => 'MOVED',
                ]);

                $proposedVolume = $item->proposed_volume;
                $unitSymbol = $item->unit_symbol ?? $item->unit ?? 'Satuan';
                $unitPrice = $item->unit_price;

                RkaAdjustment::updateOrCreate(
                    [
                        'rka_header_id' => $record->id,
                        'rka_item_id' => $item->id,
                        'section_type' => 'A',
                    ],
                    [
                        'rka_indicator_id' => $item->rka_indicator_id,
                        'account_id' => $item->account_id,
                        'section_name' => 'Penyesuaian Unit Asal',
                        'action_type' => 'MOVED',
                        'description' => $item->description,
                        'destination_unit_id' => $destinationUnitId,
                        'target_unit_id' => null,
                        'proposed_volume' => $proposedVolume,
                        'unit_symbol' => $unitSymbol,
                        'unit_price' => $unitPrice,
                        'proposed_amount' => $item->proposed_total,
                        'approved_amount' => 0,
                        'notes' => $reviewNotes,
                        'adjusted_by' => $authUserId,
                    ]
                );

                if ($targetRkaHeader) {
                    RkaAdjustment::updateOrCreate(
                        [
                            'rka_header_id' => $targetRkaHeader->id,
                            'rka_item_id' => $item->id,
                            'section_type' => 'B',
                        ],
                        [
                            'rka_indicator_id' => null,
                            'account_id' => $item->account_id,
                            'section_name' => 'Penyesuaian Unit Pengelola',
                            'action_type' => 'MOVED',
                            'description' => $item->description,
                            'destination_unit_id' => null,
                            'target_unit_id' => $record->department_id,
                            'proposed_volume' => $proposedVolume,
                            'unit_symbol' => $unitSymbol,
                            'unit_price' => $unitPrice,
                            'proposed_amount' => $item->proposed_total,
                            'approved_amount' => $item->proposed_total,
                            'notes' => $reviewNotes,
                            'adjusted_by' => $authUserId,
                        ]
                    );
                }

                $affectedIndicatorIds[] = $item->rka_indicator_id;
            }

            foreach (array_unique(array_filter($affectedIndicatorIds)) as $indicatorId) {
                if ($indicator = RkaIndicator::find($indicatorId)) {
                    $indicator->recalculateMasukanTargetValue();
                }
            }

            if ($items->isNotEmpty()) {
                $destName = Department::whereKey($destinationUnitId)->value('name') ?? 'unit tujuan';

                app(RkaActivityLogService::class)->log(
                    action: RkaLog::ACTION_ADJUSTMENT_MOVE,
                    headerId: $record->id,
                    subject: $record,
                    description: "{$items->count()} item dipindahkan secara massal ke {$destName}.",
                    newValues: [
                        'item_ids' => $items->pluck('id')->all(),
                        'count' => $items->count(),
                        'destination_unit_id' => $destinationUnitId,
                    ],
                );

                if ($targetRkaHeader) {
                    $originName = $record->department->name ?? 'unit asal';
                    app(RkaActivityLogService::class)->log(
                        action: RkaLog::ACTION_ADJUSTMENT_MOVE,
                        headerId: $targetRkaHeader->id,
                        subject: $targetRkaHeader,
                        description: "Menerima pemindahan massal {$items->count()} item dari {$originName}.",
                        newValues: [
                            'item_ids' => $items->pluck('id')->all(),
                            'count' => $items->count(),
                            'source_department_id' => $record->department_id,
                        ],
                    );
                }
            }
        });
    }

    /**
     * Menambahkan baris Adjustment manual (Section A / B).
     */
    public function addAdjustment(RkaHeader $record, ?string $activeIndicatorTab, array $data): RkaAdjustment
    {
        $unitSymbol = trim((string) ($data['unit_symbol'] ?? ''));

        if ($unitSymbol === '' && ! empty($data['rka_item_id'])) {
            $unitSymbol = trim((string) (RkaItem::find($data['rka_item_id'])?->unit_symbol ?? ''));
        }

        if ($unitSymbol === '') {
            throw new InvalidArgumentException('Satuan (unit_symbol) wajib diisi untuk penyesuaian.');
        }

        $proposedVolume = isset($data['proposed_volume']) && is_numeric($data['proposed_volume'])
            ? (float) $data['proposed_volume']
            : 1.0;

        if ($proposedVolume <= 0) {
            throw new InvalidArgumentException('Volume penyesuaian harus lebih besar dari 0.');
        }

        $proposedAmount = (float) ($data['proposed_amount'] ?? 0);
        $unitPrice = isset($data['unit_price']) && is_numeric($data['unit_price'])
            ? (float) $data['unit_price']
            : ($proposedAmount / $proposedVolume);

        return DB::transaction(function () use ($record, $activeIndicatorTab, $data, $proposedVolume, $unitSymbol, $unitPrice, $proposedAmount): RkaAdjustment {
            $adjustment = RkaAdjustment::create([
                'rka_header_id' => $record->id,
                'rka_indicator_id' => $activeIndicatorTab ? (int) $activeIndicatorTab : null,
                'rka_item_id' => $data['rka_item_id'] ?? null,
                'section_type' => $data['section_type'],
                'section_name' => $data['section_type'] === 'A' ? 'Penyesuaian Unit Asal' : 'Penyesuaian Unit Pengelola',
                'action_type' => $data['action_type'],
                'account_id' => $data['account_id'],
                'description' => $data['description'],
                'destination_unit_id' => $data['destination_unit_id'] ?? null,
                'target_unit_id' => $data['target_unit_id'] ?? null,
                'proposed_volume' => $proposedVolume,
                'unit_symbol' => $unitSymbol,
                'unit_price' => $unitPrice,
                'proposed_amount' => $proposedAmount,
                'approved_amount' => $data['approved_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'adjusted_by' => Auth::id(),
            ]);

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_ADD_ADJUSTMENT,
                headerId: $record->id,
                subject: $adjustment,
                description: "Penyesuaian anggaran ({$adjustment->action_type}): \"{$adjustment->description}\" ditambahkan.",
                newValues: [
                    'adjustment_id' => $adjustment->id,
                    'section_type' => $adjustment->section_type,
                    'action_type' => $adjustment->action_type,
                ],
            );

            return $adjustment;
        });
    }

    /**
     * Menghapus Adjustment berdasarkan ID.
     */
    public function deleteAdjustment(int $adjustmentId): void
    {
        DB::transaction(function () use ($adjustmentId): void {
            $adj = RkaAdjustment::findOrFail($adjustmentId);
            $headerId = $adj->rka_header_id;
            $oldValues = [
                'adjustment_id' => $adj->id,
                'action_type' => $adj->action_type,
                'description' => $adj->description,
            ];

            $adj->delete();

            app(RkaActivityLogService::class)->log(
                action: RkaLog::ACTION_DELETE_ADJUSTMENT,
                headerId: $headerId,
                subject: $adj,
                description: "Penyesuaian anggaran \"{$oldValues['description']}\" dihapus.",
                oldValues: $oldValues,
            );
        });
    }
}
