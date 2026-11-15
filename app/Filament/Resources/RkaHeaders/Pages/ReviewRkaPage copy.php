<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\Account;
use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaItemReview;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Hidden;

class ReviewRkaPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RkaHeaderResource::class;

    protected string $view = 'filament.resources.rka-headers.pages.review-rka-page';

    public string $activeTab = 'ringkasan'; // Options: ringkasan, sheet_rka, penyesuaian, rekap

    public ?string $activeIndicatorTab = null;
    public array $selectedItems = [];

    // 1. Tambahkan properti untuk menyimpan ID Department yang dipilih
    public ?int $selectedDepartmentId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Set default filter ke department dari record RKA saat ini
        $this->selectedDepartmentId = $this->record->department_id;

        $this->loadRkaRecord();
        // dd($this->summaryLevel1Accounts());
    }

    // 2. Buat method terpisah untuk memuat data RKA berdasarkan department yang dipilih
    public function loadRkaRecord(): void
    {
        // Cari RkaHeader berdasarkan schedule dan department yang dipilih
        $header = RkaHeader::where('schedule_id', $this->record->schedule_id)
            ->where('department_id', $this->selectedDepartmentId)
            ->first() ?? $this->record;

        $this->record = $header->load([
            'schedule',
            'department',
            'setting',
            'indicators.account',
            'items.account',
            'items.review.reviewer', // Eager load relasi RkaItemReview dan Reviewer
            'adjustments.account',
            'adjustments.destinationUnit',
            'adjustments.targetUnit',
        ]);

        $firstIndicator = $this->record->indicators->first();
        if ($firstIndicator) {
            $this->activeIndicatorTab = (string) $firstIndicator->id;
        } else {
            $this->activeIndicatorTab = null;
        }
    }

    // 3. Hook yang otomatis dipanggil Livewire saat $selectedDepartmentId berubah
    // 3. Hook yang dipanggil saat $selectedDepartmentId berubah
    public function updatedSelectedDepartmentId(?int $value): void
    {
        if (! $value) {
            return;
        }

        // Cari RkaHeader milik department yang dipilih pada schedule yang sama
        $targetRkaHeader = RkaHeader::where('schedule_id', $this->record->schedule_id)
            ->where('department_id', $value)
            ->first();

        if ($targetRkaHeader) {
            // Redirect ke route ReviewRkaPage dengan ID record RKA yang baru
            $this->redirect(
                static::getResource()::getUrl('review', ['record' => $targetRkaHeader->id])
            );
        } else {
            // Notifikasi opsional jika department belum memiliki RkaHeader pada schedule ini
            Notification::make()
                ->warning()
                ->title('RKA Belum Dibuat')
                ->body('Department terpilih belum memiliki dokumen RKA pada jadwal ini.')
                ->send();

            // Reset kembali nilai dropdown ke department record saat ini
            $this->selectedDepartmentId = $this->record->department_id;
        }
    }

    // 4. Method Computed untuk mendapatkan opsi daftar Department untuk Select Dropdown
    #[computed]
    public function departments()
    {
        // Mengambil daftar department yang terhubung dengan RkaHeader pada schedule_id record aktif
        // Mengabaikan department 'direktorat' dan 'bidang'
        return Department::query()
            ->select(['departments.id', 'departments.name', 'rka_headers.status as rka_status', 'rka_headers.id as rka_id'])
            ->leftJoin('rka_headers', function ($join) {
                $join->on('departments.id', '=', 'rka_headers.department_id')
                    ->where('rka_headers.schedule_id', '=', $this->record->schedule_id);
            })
            ->whereNotIn('departments.type', ['direktorat', 'bidang'])
            ->get();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Review RKA - ' . ($this->record->department->name ?? 'Unit Work');
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setActiveIndicatorTab(string $indicatorId): void
    {
        $this->activeIndicatorTab = $indicatorId;
    }

    /* =========================================================================
     | COMPUTED PROPERTIES
     | ========================================================================= */

    #[Computed]
    public function summaryData(): array
    {
        $usulanAwal = (float) $this->record->pagu_budget;

        // 1. Penyesuaian Bagian B (Tambahan dari pos/unit lain)
        $tambahanPusat = (float) $this->record->adjustments()
            ->where('section_type', 'B')
            ->sum('proposed_amount');

        // 2. Dipindah ke unit lain / tidak dianggarkan (Bagian A)
        $dipindahkan = (float) $this->record->adjustments()
            ->where('section_type', 'A')
            ->where('action_type', 'MOVED')
            ->sum('proposed_amount');

        $postCost = (float) $this->record->adjustments()
            ->where('section_type', 'A')
            ->where('action_type', 'BUDGETED_ELSEWHERE')
            ->sum('proposed_amount');

        $adjustReject = (float) $this->record->adjustments()
            ->where('section_type', 'A')
            ->whereIn('action_type', ['REJECTED_WITH_ADJUSTMENT', 'REJECTED'])
            ->sum('proposed_amount');

        // 3. Usulan Dibahas (Item yang disetujui, disesuaikan, atau ditolak via RkaItemReview)
        $usulanDibahas = (float) $this->record->items->filter(function ($item) {
            $status = $item->review?->status;
            return in_array($status, ['APPROVED', 'ACCEPTED', 'REJECTED', 'REJECTED_WITH_ADJUST']);
        })->sum('proposed_total');

        // 4. Perhitungan Selisih Harga & Volume Adjustment
        $adjustmentPriceAndVolume = $usulanAwal + $tambahanPusat - $dipindahkan - $postCost - $usulanDibahas;

        // 5. Total Disetujui Setelah Review
        $disetujuiSetelahReview = (float) $this->record->items->sum(function ($item) {
            return (float) ($item->review?->approved_total ?? 0);
        });

        $percentage = $usulanDibahas > 0 ? ($disetujuiSetelahReview / $usulanDibahas) * 100 : 0;

        return [
            'usulan_awal'              => $usulanAwal,
            'dipindahkan'              => $dipindahkan,
            'pos_cost'                 => $postCost,
            'adjustment_price'         => $adjustmentPriceAndVolume,
            'tambahan_pusat'           => $tambahanPusat,
            'usulan_dibahas'           => $usulanDibahas,
            'disetujui_setelah_review' => $disetujuiSetelahReview,
            'percentage'               => round($percentage, 1),
        ];
    }

    public function summaryLevel1Accounts(): array
    {
        // 1. Ambil item RKA beserta relasi Account & Review
        $items = RkaItem::where('rka_header_id', $this->record->id)
            ->with(['account.parent.parent', 'review'])
            ->get();

        // Helper Closure untuk menghitung nominal 'Approved' berdasar Filter Status Review
        $calculateItemApprovedAmount = function ($item): float {
            $review = $item->review;
            if (! $review) {
                return 0.00;
            }

            $status = $review->status;

            // Disesuaikan Lalu Ditolak: (approved_unit_price * proposed_volume)
            if ($status === 'REJECTED_WITH_ADJUST') {
                return (float) ($review->approved_unit_price * $item->proposed_volume);
            }

            // Accepted, Approved, Reduced, Moved, atau status aktif lainnya
            if (in_array($status, ['ACCEPTED', 'APPROVED', 'REDUCED', 'MOVED'])) {
                return (float) $review->approved_total;
            }

            return 0.00;
        };

        // 2. Hitung Grand Total Disetujui Keseluruhan
        $grandTotalApproved = (float) $items->sum(fn($item) => $calculateItemApprovedAmount($item));
        $grandTotalUsulan   = (float) $items->sum('proposed_total');

        // 3. Kumpulkan seluruh ID Akun Level 1
        $level1Accounts = collect();
        foreach ($items as $item) {
            if (! $item->account) {
                continue;
            }

            $curr = $item->account;
            while ($curr->parent && $curr->level > 1) {
                $curr = $curr->parent;
            }

            if ($curr->level === 1) {
                $level1Accounts->push($curr);
            }
        }

        $level1Accounts = $level1Accounts->unique('id')->sortBy('code');

        // 4. Hitung Agregat per Level 1 Account
        $result = $level1Accounts->map(function ($accL1) use ($items, $calculateItemApprovedAmount, $grandTotalApproved) {
            $filteredItems = $items->filter(function ($item) use ($accL1) {
                if (! $item->account) {
                    return false;
                }
                $curr = $item->account;
                while ($curr) {
                    if ($curr->id === $accL1->id) {
                        return true;
                    }
                    $curr = $curr->parent;
                }
                return false;
            });

            $totalUsulan   = (float) $filteredItems->sum('proposed_total');
            $totalApproved = (float) $filteredItems->sum(fn($item) => $calculateItemApprovedAmount($item));

            $selisih    = $totalApproved - $totalUsulan;
            $percentage = $grandTotalApproved > 0 ? ($totalApproved / $grandTotalApproved) * 100 : 0;

            return [
                'account_id' => $accL1->id,
                'code'       => $accL1->code,
                'name'       => $accL1->name,
                'usulan'     => $totalUsulan,
                'approved'   => $totalApproved,
                'selisih'    => $selisih,
                'percentage' => round($percentage, 1),
            ];
        })->values();

        return [
            'rows'               => $result,
            'grandTotalUsulan'   => $grandTotalUsulan,
            'grandTotalApproved' => $grandTotalApproved,
            'grandTotalSelisih'  => $grandTotalApproved - $grandTotalUsulan,
        ];
    }

    #[Computed]
    public function rekapByKelompok(): array
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
        $totalUsulanAwal   = 0;
        $totalUsulanReview = 0;
        $totalApproved     = 0;

        // Muat relasi adjustments agar query lebih efisien dan ringkas
        $this->record->loadMissing(['items.account', 'adjustments']);

        foreach ($categories as $code => $label) {
            // Filter item berdasarkan kode akun
            $categoryItems = $this->record->items->filter(function ($item) use ($code) {
                return str_starts_with($item->account?->code ?? '', $code);
            });

            // 1. Usulan Awal (Murni sebelum review/trimming):
            // Jika item disesuaikan (is_trimmed), kita ambil proposed_amount awal dari RkaAdjustment Section A jika ada,
            // atau kita hitung dari proposed_volume * unit_price asli pemohon.
            $usulanAwal = $categoryItems->sum(function ($item) {
                $adjustment = $this->record->adjustments
                    ->where('rka_item_id', $item->id)
                    ->where('section_type', \App\Models\RkaAdjustment::SECTION_A)
                    ->first();

                if ($adjustment && $adjustment->proposed_amount > 0) {
                    // Ambil proposed_amount murni usulan awal sebelum trimming
                    return $adjustment->proposed_amount;
                }

                return $item->proposed_total;
            });

            // 2. Usulan In Review: Total proposed_total khusus status APPROVED & REJECTED_WITH_ADJUSTMENT
            // (Nilai yang sudah disesuaikan harganya oleh reviewer)
            $inReviewItems = $categoryItems->whereIn('status', ['APPROVED', 'REJECTED_WITH_ADJUSTMENT']);
            $usulanReview  = $inReviewItems->sum('proposed_total');

            // 3. Disetujui: Total approved_total
            $approved = $categoryItems->sum('approved_total');

            // 4. Persentase Capaian Disetujui vs Usulan In Review
            $pct = $usulanReview > 0 ? ($approved / $usulanReview) * 100 : 0;

            $totalUsulanAwal   += $usulanAwal;
            $totalUsulanReview += $usulanReview;
            $totalApproved     += $approved;

            $rekap[] = [
                'code'          => $code,
                'label'         => $label,
                'usulan_awal'   => $usulanAwal,
                'usulan_review' => $usulanReview,
                'approved'      => $approved,
                'percentage'    => round($pct, 1),
            ];
        }

        return [
            'items'               => $rekap,
            'total_usulan_awal'   => $totalUsulanAwal,
            'total_usulan_review' => $totalUsulanReview,
            'total_approved'      => $totalApproved,
            'total_percentage'    => $totalUsulanReview > 0 ? round(($totalApproved / $totalUsulanReview) * 100, 1) : 0,
        ];
    }

    #[Computed]
    public function budgetHierarchy(): array
    {
        if (! $this->activeIndicatorTab) {
            return [
                'level1Accounts'         => collect(),
                'allAccounts'            => collect(),
                'itemsByAccount'         => collect(),
                'calculateAccountTotals' => fn() => ['usulan' => 0, 'approved' => 0],
                'totalUsulan'            => 0,
                'totalUsulanAfterReview' => 0,
                'totalApproved'          => 0,
            ];
        }

        // 1. Muat seluruh RkaItem beserta relasi Account & Review
        $allItems = RkaItem::where('rka_indicator_id', $this->activeIndicatorTab)
            ->with(['account.parent.parent', 'review'])
            ->get();

        // 2. FILTER ITEM YANG AKAN DITAMPILKAN DI TABEL
        // Hanya item yang berstatus ACCEPTED, APPROVED, PENDING, REJECTED_WITH_ADJUSTMENT, atau belum direview (null)
        $allowedStatuses = ['ACCEPTED', 'APPROVED', 'PENDING', 'REJECTED_WITH_ADJUST','REJECTED', null];

        $filteredItems = $allItems->filter(function ($item) use ($allowedStatuses) {
            $status = $item->review?->status;
            return in_array($status, $allowedStatuses, true);
        });

        // 3. Kumpulkan ID Akun yang terlibat berdasarkan item yang telah difilter
        $accountIds = collect();
        foreach ($filteredItems as $item) {
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

        // Kelompokkan item per account_id dari item yang sudah difilter
        $itemsByAccount = $filteredItems->groupBy('account_id');

        // 4. Helper Closure kalkulasi nominal Approved
        $getItemApprovedAmount = function ($item): float {
            $review = $item->review;
            if (! $review) {
                return (float) $item->proposed_total;
            }

            $status = $review->status;

            if ($status === 'REJECTED_WITH_ADJUSTMENT') {
                return (float) ($review->approved_unit_price * $item->proposed_volume);
            }

            if (in_array($status, ['ACCEPTED', 'APPROVED', 'REDUCED', 'MOVED'])) {
                return (float) $review->approved_total;
            }

            if ($status === 'PENDING') {
                return $review->approved_total !== null
                    ? (float) $review->approved_total
                    : (float) $item->proposed_total;
            }

            return 0.00;
        };

        // 5. Closure Rekursif Perhitungan Total per Akun (Subtotal Header Level 1-3)
        $calculateAccountTotals = function ($account) use (&$calculateAccountTotals, $allAccounts, $itemsByAccount, $getItemApprovedAmount) {
            $accountItems = $itemsByAccount[$account->id] ?? collect();

            $directUsulan   = (float) $accountItems->sum('proposed_total');
            $directApproved = (float) $accountItems->sum(fn($item) => $getItemApprovedAmount($item));

            $children = $allAccounts->where('parent_id', $account->id);
            $childrenUsulan   = 0;
            $childrenApproved = 0;

            foreach ($children as $child) {
                $res = $calculateAccountTotals($child);
                $childrenUsulan   += $res['usulan'];
                $childrenApproved += $res['approved'];
            }

            return [
                'usulan'   => $directUsulan + $childrenUsulan,
                'approved' => $directApproved + $childrenApproved,
            ];
        };

        // 6. Kalkulasi Grand Total Rekapitulasi Footer
        $totalUsulan = (float) $allItems->sum('proposed_total');
        $totalUsulanAfterReview = (float) $filteredItems->sum('proposed_total');
        $totalApproved = (float) $filteredItems->sum(fn($item) => $getItemApprovedAmount($item));

        return [
            'level1Accounts'         => $allAccounts->where('level', 1)->sortBy('code'),
            'allAccounts'            => $allAccounts,
            'itemsByAccount'         => $itemsByAccount,
            'calculateAccountTotals' => $calculateAccountTotals,
            'totalUsulan'            => $totalUsulan,
            'totalUsulanAfterReview' => $totalUsulanAfterReview,
            'totalApproved'          => $totalApproved,
        ];
    }

    /* =========================================================================
     | FILAMENT ACTIONS (REVIEW & ADJUSTMENTS)
     | ========================================================================= */

    /**
     * Action Modal: Review / Pemangkasan Item Belanja
     */
    public function reviewItemAction(): Action
    {
        return Action::make('reviewItem')
            ->label('Review')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->iconButton()
            ->modalHeading('Review / Pemangkasan & Adjustment Item Belanja')
            ->modalWidth('2xl')
            ->fillForm(function (array $arguments) {
                $item = RkaItem::with('review')->find($arguments['item_id'] ?? null);
                if (! $item) {
                    return [];
                }

                $review = $item->review;

                return [
                    'item_id'         => $item->id,
                    'unit_price'      => $item->unit_price,
                    'proposed_volume' => $item->proposed_volume,
                    'proposed_total'  => $item->proposed_total,
                    'action_type'     => $review?->status ?? 'APPROVED',
                    'approved_volume' => $review?->approved_volume ?? $item->proposed_volume,
                    'unit_symbol'     => $item->unit_symbol ?? 'Satuan',
                    'approved_price'  => $review?->approved_unit_price ?? $item->unit_price,
                    'approved_total'  => $review?->approved_total ?? $item->proposed_total,
                    'review_notes'    => $review?->review_notes,
                ];
            })
            ->form([
                Hidden::make('item_id'),
                Hidden::make('unit_price'),
                Hidden::make('proposed_volume'),
                Hidden::make('proposed_total'),

                Section::make('Informasi Item Asli Usulan')
                    ->description('Rincian item usulan sebelum dilakukan review/penyesuaian.')
                    ->compact()
                    ->schema([
                        Placeholder::make('item_info_description')
                            ->label('Uraian Item')
                            ->content(function (callable $get) {
                                $item = RkaItem::find($get('item_id'));
                                return $item ? $item->description : '-';
                            })
                            ->columnSpanFull(),

                        Placeholder::make('item_info_account')
                            ->label('Kode & Rekening')
                            ->content(function (callable $get) {
                                $item = RkaItem::find($get('item_id'));
                                return $item?->account ? "{$item->account->code} - {$item->account->name}" : '-';
                            }),

                        Placeholder::make('item_info_proposed')
                            ->label('Usulan Awal')
                            ->content(function (callable $get) {
                                $item = RkaItem::find($get('item_id'));
                                if (! $item) return '-';

                                $vol   = number_format((float) $item->proposed_volume, 0, ',', '.');
                                $sat   = $item->unit_symbol ?? 'Satuan';
                                $harga = number_format((float) $item->unit_price, 0, ',', '.');
                                $total = number_format((float) $item->proposed_total, 0, ',', '.');

                                return "{$vol} {$sat} @ Rp {$harga} (Total: Rp {$total})";
                            }),
                    ])->columns(2),

                Grid::make(2)->schema([
                    Select::make('action_type')
                        ->label('Hasil Review / Status Aksi')
                        ->options([
                            'ACCEPTED'                 => 'Disetujui (Approved)',
                            'MOVED'                    => 'Dipindahkan / Pos Terpusat (Membuat Section B)',
                            'BUDGETED_ELSEWHERE'       => 'Sudah Dianggarkan di Unit Lain / Terpusat',
                            'REJECTED_WITH_ADJUSTMENT' => 'Disesuaikan Lalu Ditolak (Ada Adjustment Harga/Vol)',
                            'REJECTED'                 => 'Dibatalkan / Ditolak Langsung (Tanpa Penyesuaian)',
                            'PENDING'                  => 'Ditunda / Menunggu Keputusan',
                            'REMOVED'                  => 'Dipangkas / Dihapus',
                        ])
                        ->default('APPROVED')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if (in_array($state, ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'])) {
                                $set('approved_volume', 0);
                                $set('approved_total', 0);

                                if ($state === 'REJECTED') {
                                    $set('approved_price', $get('unit_price'));
                                }
                            } elseif ($state === 'APPROVED') {
                                $proposedVol = $get('proposed_volume') ?? 0;
                                $proposedTot = $get('proposed_total') ?? 0;
                                $unitPrice   = $get('unit_price') ?? 0;

                                $set('approved_volume', $proposedVol);
                                $set('approved_price', $unitPrice);
                                $set('approved_total', $proposedTot);
                            }
                        })
                        ->columnSpanFull(),

                    TextInput::make('approved_volume')
                        ->label('Volume Disetujui')
                        ->numeric()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $price = (float) ($get('approved_price') ?? $get('unit_price') ?? 0);
                            if (is_numeric($state) && ! in_array($get('action_type'), ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'])) {
                                $set('approved_total', (float) $state * $price);
                            }
                        }),

                    TextInput::make('unit_symbol')
                        ->label('Satuan (Unit Symbol)')
                        ->required(),

                    TextInput::make('approved_price')
                        ->label('Harga Satuan / Adjustment (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->required()
                        ->disabled(fn(callable $get) => $get('action_type') === 'REJECTED')
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $volume = (float) ($get('approved_volume') ?? 0);
                            if (is_numeric($state) && ! in_array($get('action_type'), ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'])) {
                                $set('approved_total', (float) $state * $volume);
                            }
                        }),

                    TextInput::make('approved_total')
                        ->label('Total Disetujui (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->required(),

                    Select::make('destination_unit_id')
                        ->label('Unit Tujuan / Pengalih')
                        ->options(\App\Models\Department::whereNotIn('type', ['direktorat', 'bidang'])->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn(callable $get) => $get('action_type') === 'MOVED')
                        ->visible(fn(callable $get) => in_array($get('action_type'), ['MOVED', 'PENDING'])),

                    Textarea::make('review_notes')
                        ->label('Catatan Reviewer / Alasan Adjustment')
                        ->placeholder('Misal: Penyesuaian harga acuan laboratorium lalu ditolak / Dianggarkan di unit lain')
                        ->columnSpanFull(),
                ]),
            ])
            ->action(function (array $data, array $arguments): void {
                $itemId = $data['item_id'] ?? $arguments['item_id'] ?? null;
                $item   = RkaItem::findOrFail($itemId);
                $actionType = $data['action_type'];

                // 1. Tentukan Unit Price Baru Hasil Penyesuaian Reviewer
                if ($actionType === 'REJECTED') {
                    $newUnitPrice = (float) $item->unit_price;
                } else {
                    $newUnitPrice = ! empty($data['approved_price']) ? (float) $data['approved_price'] : (float) $item->unit_price;
                }

                $approvedVolume = in_array($actionType, ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'])
                    ? 0.00
                    : (float) $data['approved_volume'];

                $approvedTotal = $approvedVolume * $newUnitPrice;
                $proposedTotal = (float) $item->proposed_total;

                $priceTrimmed = max(0, $proposedTotal - $approvedTotal);
                $isTrimmed    = $actionType !== 'APPROVED' || ($approvedTotal < $proposedTotal);

                // 2. Simpan / Perbarui Data pada Model RkaItemReview
                RkaItemReview::updateOrCreate(
                    ['rka_item_id' => $item->id],
                    [
                        'reviewer_id'         => Auth::id(),
                        'approved_volume'     => $approvedVolume,
                        'approved_unit_price' => $newUnitPrice,
                        'approved_total'      => $approvedTotal,
                        'status'              => $actionType,
                        'is_trimmed'          => $isTrimmed,
                        'price_trimmed'       => $priceTrimmed,
                        'review_notes'        => $data['review_notes'] ?? null,
                    ]
                );

                // Update satuan pada RkaItem jika diperbarui saat review
                if (! empty($data['unit_symbol']) && $data['unit_symbol'] !== $item->unit_symbol) {
                    $item->update(['unit_symbol' => $data['unit_symbol']]);
                }

                // 3. Kelola Sinkronisasi RkaAdjustment
                $proposedVolume         = (float) $item->proposed_volume;
                $unitSymbol             = $data['unit_symbol'] ?? $item->unit_symbol ?? 'Satuan';
                $adjustedProposedAmount = $proposedVolume * $newUnitPrice;

                if ($actionType !== 'ACCEPTED') {
                    if ($actionType === 'MOVED' || $actionType == 'PENDING') {
                        $destinationUnitId = $data['destination_unit_id'] ?? null;

                        // Section A: Unit Asal
                        RkaAdjustment::updateOrCreate(
                            [
                                'rka_header_id' => $this->record->id,
                                'rka_item_id'   => $item->id,
                                'section_type'  => 'A',
                            ],
                            [
                                'rka_indicator_id'    => $item->rka_indicator_id,
                                'account_id'          => $item->account_id,
                                'section_name'        => 'Penyesuaian Unit Asal (Dialihkan)',
                                'action_type'         => 'MOVED',
                                'description'         => $item->description,
                                'destination_unit_id' => $destinationUnitId,
                                'target_unit_id'      => null,
                                'proposed_volume'     => $proposedVolume,
                                'unit_symbol'         => $unitSymbol,
                                'unit_price'          => $newUnitPrice,
                                'proposed_amount'     => $adjustedProposedAmount,
                                'approved_amount'     => 0,
                                'notes'               => $data['review_notes'],
                                'adjusted_by'         => Auth::id(),
                            ]
                        );

                        // Section B: Unit Tujuan
                        if ($destinationUnitId) {
                            $targetRkaHeader = RkaHeader::where('department_id', $destinationUnitId)
                                ->where('schedule_id', $this->record->schedule_id)
                                ->where('setting_id', $this->record->setting_id)
                                ->first();

                            if ($targetRkaHeader) {
                                RkaAdjustment::updateOrCreate(
                                    [
                                        'rka_header_id' => $targetRkaHeader->id,
                                        'rka_item_id'   => $item->id,
                                        'section_type'  => 'B',
                                    ],
                                    [
                                        'rka_indicator_id'    => null,
                                        'account_id'          => $item->account_id,
                                        'section_name'        => 'Penyesuaian Unit Pengelola',
                                        'action_type'         => 'MOVED',
                                        'description'         => $item->description,
                                        'destination_unit_id' => null,
                                        'target_unit_id'      => $this->record->department_id,
                                        'proposed_volume'     => $proposedVolume,
                                        'unit_symbol'         => $unitSymbol,
                                        'unit_price'          => $newUnitPrice,
                                        'proposed_amount'     => $adjustedProposedAmount,
                                        'approved_amount'     => $approvedTotal,
                                        'notes'               => $data['review_notes'],
                                        'adjusted_by'         => Auth::id(),
                                    ]
                                );
                            }
                        }
                    } else {
                        // Status REJECTED, REJECTED_WITH_ADJUSTMENT, REMOVED, BUDGETED_ELSEWHERE, PENDING
                        RkaAdjustment::where('rka_item_id', $item->id)
                            ->where('section_type', 'B')
                            ->delete();

                        $rejectedStatuses = ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'];
                        $sectionType      = in_array($actionType, $rejectedStatuses) ? 'A' : 'B';
                        $sectionName      = $sectionType === 'A' ? 'Penyesuaian Unit Asal' : 'Penyesuaian Unit Pengelola';

                        RkaAdjustment::updateOrCreate(
                            [
                                'rka_header_id' => $this->record->id,
                                'rka_item_id'   => $item->id,
                                'section_type'  => $sectionType,
                            ],
                            [
                                'rka_indicator_id'    => $item->rka_indicator_id,
                                'account_id'          => $item->account_id,
                                'section_name'        => $sectionName,
                                'action_type'         => $actionType,
                                'description'         => $item->description,
                                'destination_unit_id' => null,
                                'target_unit_id'      => null,
                                'proposed_volume'     => $proposedVolume,
                                'unit_symbol'         => $unitSymbol,
                                'unit_price'          => $newUnitPrice,
                                'proposed_amount'     => $adjustedProposedAmount,
                                'approved_amount'     => in_array($actionType, $rejectedStatuses) ? 0 : $approvedTotal,
                                'notes'               => $data['review_notes'],
                                'adjusted_by'         => Auth::id(),
                            ]
                        );
                    }
                } else {
                    // Jika APPROVED, bersihkan data adjustment terkait
                    RkaAdjustment::where('rka_item_id', $item->id)->delete();
                }

                // 4. Hitung Ulang Indikator & Refresh Record
                if ($indicator = RkaIndicator::find($item->rka_indicator_id)) {
                    $indicator->recalculateMasukanTargetValue();
                }

                $this->record->refresh();

                Notification::make()
                    ->title('Hasil Review & Adjustment Item Disimpan')
                    ->success()
                    ->send();
            });
    }
    public function importAdjustmentToItemAction(): Action
    {
        return Action::make('importAdjustmentToItem')
            ->label('Masukkan ke RKA')
            ->icon('heroicon-o-arrow-down-on-square')
            ->color('success')
            ->iconButton()
            ->tooltip('Masukkan data adjustment ini ke Item Belanja Unit')
            ->requiresConfirmation()
            ->modalHeading('Masukkan Adjustment ke Item Belanja')
            ->modalDescription('Apakah Anda yakin ingin memasukkan data adjustment ini ke dalam daftar Item Belanja (RKA) unit ini?')
            ->action(function (array $arguments): void {
                $adjustmentId = $arguments['adjustment_id'] ?? null;
                $adjustment   = RkaAdjustment::find($adjustmentId);

                if (! $adjustment) {
                    Notification::make()
                        ->title('Data Adjustment tidak ditemukan')
                        ->danger()
                        ->send();
                    return;
                }

                if ($adjustment->is_imported) {
                    Notification::make()
                        ->title('Data adjustment ini sudah pernah dimasukkan ke RKA Item')
                        ->warning()
                        ->send();
                    return;
                }

                // -----------------------------------------------------------------
                // Cari rka_indicator_id berdasarkan Account Parent Level 1
                // -----------------------------------------------------------------
                $rkaIndicatorId = $adjustment->rka_indicator_id;

                if (! $rkaIndicatorId && $adjustment->account_id) {
                    $account = Account::find($adjustment->account_id);

                    if ($account) {
                        $parentLevel1 = $account;
                        while ($parentLevel1->parent && $parentLevel1->level > 1) {
                            $parentLevel1 = $parentLevel1->parent;
                        }

                        if ($parentLevel1) {
                            $indicator = RkaIndicator::where('rka_header_id', $this->record->id)
                                ->where('account_id', $parentLevel1->id)
                                ->first();

                            $rkaIndicatorId = $indicator?->id;
                        }
                    }
                }

                // 1. Buat / Perbarui data murni usulan pada RkaItem
                $newItem = RkaItem::updateOrCreate(
                    [
                        'id' => $adjustment->imported_rka_item_id,
                    ],
                    [
                        'rka_header_id'    => $this->record->id,
                        'rka_indicator_id' => $rkaIndicatorId,
                        'account_id'       => $adjustment->account_id,
                        'description'      => $adjustment->description,
                        'proposed_volume'  => $adjustment->proposed_volume,
                        'unit_symbol'      => $adjustment->unit_symbol,
                        'unit_price'       => $adjustment->unit_price,
                        'proposed_total'   => $adjustment->proposed_amount,
                        'is_imported'      => true,
                    ]
                );

                // 2. Buat / Perbarui data hasil review pada RkaItemReview
                $approvedVolume    = (float) $adjustment->proposed_volume;
                $approvedUnitPrice = (float) $adjustment->unit_price;
                $approvedTotal     = (float) $adjustment->approved_amount;
                $proposedTotal     = (float) $adjustment->proposed_amount;

                $priceTrimmed = max(0, $proposedTotal - $approvedTotal);
                $isTrimmed    = $approvedTotal < $proposedTotal;

                RkaItemReview::updateOrCreate(
                    [
                        'rka_item_id' => $newItem->id,
                    ],
                    [
                        'reviewer_id'         => Auth::id(),
                        'approved_volume'     => $approvedVolume,
                        'approved_unit_price' => $approvedUnitPrice,
                        'approved_total'      => $approvedTotal,
                        'status'              => RkaItemReview::STATUS_ACCEPTED,
                        'is_trimmed'          => $isTrimmed,
                        'price_trimmed'       => $priceTrimmed,
                        'review_notes'        => $adjustment->notes ?? 'Hasil limpahan / adjustment Section B',
                    ]
                );

                // 3. Simpan/Update log relasi pada RkaAdjustment
                $adjustment->update([
                    'is_imported'          => true,
                    'imported_rka_item_id' => $newItem->id,
                ]);

                // 4. Hitung Ulang Indikator & Refresh Record
                if ($newItem->rka_indicator_id && ($indicator = RkaIndicator::find($newItem->rka_indicator_id))) {
                    $indicator->recalculateMasukanTargetValue();
                }

                $this->record->refresh();

                Notification::make()
                    ->title('Berhasil Dimasukkan ke Item Belanja Unit')
                    ->success()
                    ->send();
            });
    }
    public function bulkApproveAction(): Action
    {
        return Action::make('bulkApprove')
            ->label('Approve Terpilih (' . count($this->selectedItems) . ')')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi Auto Approve Massal')
            ->modalDescription('Apakah Anda yakin ingin menyetujui (Auto Approve) semua item yang dipilih? Nilai disetujui akan disesuaikan dengan nilai usulan.')
            ->action(function (): void {
                if (empty($this->selectedItems)) {
                    Notification::make()
                        ->title('Tidak ada item yang dipilih')
                        ->warning()
                        ->send();
                    return;
                }

                $items = RkaItem::whereIn('id', $this->selectedItems)->get();
                $affectedIndicatorIds = [];

                foreach ($items as $item) {
                    $proposedVolume    = (float) $item->proposed_volume;
                    $approvedUnitPrice = (float) $item->unit_price;
                    $proposedTotal     = (float) $item->proposed_total;

                    // 1. Simpan/Perbarui hasil review pada RkaItemReview
                    RkaItemReview::updateOrCreate(
                        [
                            'rka_item_id' => $item->id,
                        ],
                        [
                            'reviewer_id'         => Auth::id(),
                            'approved_volume'     => $proposedVolume,
                            'approved_unit_price' => $approvedUnitPrice,
                            'approved_total'      => $proposedTotal,
                            'status'              => RkaItemReview::STATUS_ACCEPTED,
                            'is_trimmed'          => false,
                            'price_trimmed'       => 0.00,
                            'review_notes'        => 'Disetujui secara massal (Auto Approve)',
                        ]
                    );

                    // 2. Hapus baris adjustment jika sebelumnya pernah dimodifikasi/ditrim
                    RkaAdjustment::where('rka_item_id', $item->id)->delete();

                    if ($item->rka_indicator_id) {
                        $affectedIndicatorIds[] = $item->rka_indicator_id;
                    }
                }

                // 3. Recalculate Indikator terkait
                $uniqueIndicatorIds = array_unique($affectedIndicatorIds);
                foreach ($uniqueIndicatorIds as $indicatorId) {
                    if ($indicator = RkaIndicator::find($indicatorId)) {
                        $indicator->recalculateMasukanTargetValue();
                    }
                }

                // 4. Reset seleksi & refresh record
                $this->selectedItems = [];
                $this->record->refresh();

                Notification::make()
                    ->title('Item berhasil disetujui secara massal')
                    ->success()
                    ->send();
            });
    }
    public function bulkMoveAction(): Action
    {
        return Action::make('bulkMove')
            ->label('Pindahkan Terpilih (' . count($this->selectedItems) . ')')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi Pemindahan Item Massal (Pos Terpusat)')
            ->modalDescription('Semua item terpilih akan dipindahkan statusnya menjadi MOVED ke Unit Pengelola/Tujuan yang ditentukan.')
            ->form([
                Select::make('destination_unit_id')
                    ->label('Unit Tujuan / Pengalih')
                    ->options(Department::whereNotIn('type', ['direktorat', 'bidang'])->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Textarea::make('review_notes')
                    ->label('Catatan Reviewer / Alasan Pemindahan')
                    ->placeholder('Misal: Dialihkan ke Pos Terpusat Unit Pengelola ICT')
                    ->default('Dipindahkan secara massal ke Pos Terpusat'),
            ])
            ->action(function (array $data): void {
                if (empty($this->selectedItems)) {
                    Notification::make()
                        ->title('Tidak ada item yang dipilih')
                        ->warning()
                        ->send();
                    return;
                }

                $destinationUnitId = $data['destination_unit_id'];
                $reviewNotes       = $data['review_notes'] ?? 'Dipindahkan secara massal';
                $authUserId        = Auth::id();

                // 1. Cari RkaHeader untuk Unit Tujuan (Section B)
                $targetRkaHeader = RkaHeader::where('department_id', $destinationUnitId)
                    ->where('schedule_id', $this->record->schedule_id)
                    ->where('setting_id', $this->record->setting_id)
                    ->first();

                $items = RkaItem::whereIn('id', $this->selectedItems)->get();
                $affectedIndicatorIds = [];

                foreach ($items as $item) {
                    $proposedVolume = (float) $item->proposed_volume;
                    $unitPrice      = (float) $item->unit_price;
                    $proposedTotal  = (float) $item->proposed_total;

                    // 2. Simpan Status MOVED ke RkaItemReview (Unit Asal diset 0)
                    RkaItemReview::updateOrCreate(
                        [
                            'rka_item_id' => $item->id,
                        ],
                        [
                            'reviewer_id'         => $authUserId,
                            'approved_volume'     => 0,
                            'approved_unit_price' => $unitPrice,
                            'approved_total'      => 0,
                            'status'              => 'MOVED',
                            'is_trimmed'          => true,
                            'price_trimmed'       => $proposedTotal,
                            'review_notes'        => $reviewNotes,
                        ]
                    );

                    $unitSymbol = $item->unit_symbol ?? 'Satuan';

                    // 3A. Buat/Update RkaAdjustment untuk Unit Asal (Section A)
                    RkaAdjustment::updateOrCreate(
                        [
                            'rka_header_id' => $this->record->id,
                            'rka_item_id'   => $item->id,
                            'section_type'  => 'A',
                        ],
                        [
                            'rka_indicator_id'    => $item->rka_indicator_id,
                            'account_id'          => $item->account_id,
                            'section_name'        => 'Penyesuaian Unit Asal',
                            'action_type'         => 'MOVED',
                            'description'         => $item->description,
                            'destination_unit_id' => $destinationUnitId,
                            'target_unit_id'      => null,
                            'proposed_volume'     => $proposedVolume,
                            'unit_symbol'         => $unitSymbol,
                            'unit_price'          => $unitPrice,
                            'proposed_amount'     => $proposedTotal,
                            'approved_amount'     => 0, // Nilai diset 0 di unit asal
                            'notes'               => $reviewNotes,
                            'adjusted_by'         => $authUserId,
                        ]
                    );

                    // 3B. Buat/Update RkaAdjustment untuk Unit Tujuan (Section B) jika RkaHeader tujuan ditemukan
                    if ($targetRkaHeader) {
                        RkaAdjustment::updateOrCreate(
                            [
                                'rka_header_id' => $targetRkaHeader->id,
                                'rka_item_id'   => $item->id,
                                'section_type'  => 'B',
                            ],
                            [
                                'rka_indicator_id'    => null,
                                'account_id'          => $item->account_id,
                                'section_name'        => 'Penyesuaian Unit Pengelola',
                                'action_type'         => 'MOVED',
                                'description'         => $item->description,
                                'destination_unit_id' => null,
                                'target_unit_id'      => $this->record->department_id,
                                'proposed_volume'     => $proposedVolume,
                                'unit_symbol'         => $unitSymbol,
                                'unit_price'          => $unitPrice,
                                'proposed_amount'     => $proposedTotal,
                                'approved_amount'     => $proposedTotal, // Disetujui sebesar usulan di unit pengelola
                                'notes'               => $reviewNotes,
                                'adjusted_by'         => $authUserId,
                            ]
                        );
                    }

                    if ($item->rka_indicator_id) {
                        $affectedIndicatorIds[] = $item->rka_indicator_id;
                    }
                }

                // 4. Recalculate Indikator terkait
                $uniqueIndicatorIds = array_unique(array_filter($affectedIndicatorIds));
                foreach ($uniqueIndicatorIds as $indicatorId) {
                    if ($indicator = RkaIndicator::find($indicatorId)) {
                        $indicator->recalculateMasukanTargetValue();
                    }
                }

                // 5. Reset seleksi & Refresh Record
                $this->selectedItems = [];
                $this->record->refresh();

                Notification::make()
                    ->title('Item berhasil dipindahkan (MOVED) secara massal')
                    ->success()
                    ->send();
            });
    }

    /**
     * Action Modal: Tambah Penyesuaian Usulan (Section A & B)
     */
    public function addAdjustmentAction(): Action
    {
        return Action::make('addAdjustment')
            ->label('Tambah Penyesuaian')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->modalHeading('Tambah Daftar Penyesuaian RKA')
            ->modalWidth('3xl')
            ->form([
                Grid::make(2)->schema([
                    Select::make('section_type')
                        ->label('Tipe Penyesuaian')
                        ->options([
                            'A' => 'A - Usulan Dipindah / Tidak Dianggarkan di Unit Ini',
                            'B' => 'B - Pos Dikelola Unit Ini untuk Unit Lain (Shared Cost / Usulan Reviewer)',
                        ])
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state === 'A') {
                                $set('section_name', 'Penyesuaian Unit Asal');
                            } else {
                                $set('section_name', 'Penyesuaian Unit Pengelola');
                            }
                        }),

                    Select::make('action_type')
                        ->label('Aksi Reviewer')
                        ->options([
                            'MOVED'             => 'MOVED (Dipindahkan)',
                            'REJECTED'          => 'REJECTED (Tidak Dianggarkan)',
                            'SHARED_COST'       => 'SHARED_COST (Dialokasikan ke Unit Lain)',
                            'ADDED_BY_REVIEWER' => 'ADDED_BY_REVIEWER (Tambahan Saat Review)',
                            'REMOVED'           => 'REMOVED (Dihapus)',
                        ])
                        ->required(),

                    Select::make('account_id')
                        ->label('Kode Rekening')
                        ->options(Account::all()->pluck('full_account_label', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('description')
                        ->label('Uraian Penyesuaian')
                        ->placeholder('Misal: Pembelian Akun Turnitin / Lisensi')
                        ->required(),

                    Select::make('destination_unit_id')
                        ->label('Unit Tujuan / Pengalih')
                        ->options(Department::pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn(callable $get) => $get('section_type') === 'A'),

                    Select::make('target_unit_id')
                        ->label('Unit Penerima Manfaat')
                        ->options(Department::pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn(callable $get) => $get('section_type') === 'B'),

                    TextInput::make('proposed_amount')
                        ->label('Nominal Usulan (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make('approved_amount')
                        ->label('Nominal Disetujui (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Textarea::make('notes')
                        ->label('Keterangan / Alasan Penyesuaian')
                        ->columnSpanFull(),
                ]),
            ])
            ->action(function (array $data): void {
                RkaAdjustment::create([
                    'rka_header_id'       => $this->record->id,
                    'rka_indicator_id'    => $this->activeIndicatorTab,
                    'section_type'        => $data['section_type'],
                    'section_name'        => $data['section_type'] === 'A' ? 'Penyesuaian Unit Asal' : 'Penyesuaian Unit Pengelola',
                    'action_type'         => $data['action_type'],
                    'account_id'          => $data['account_id'],
                    'description'         => $data['description'],
                    'destination_unit_id' => $data['destination_unit_id'] ?? null,
                    'target_unit_id'      => $data['target_unit_id'] ?? null,
                    'proposed_amount'     => $data['proposed_amount'],
                    'approved_amount'     => $data['approved_amount'],
                    'notes'               => $data['notes'],
                    'adjusted_by'         => Auth::id(),
                ]);

                $this->record->refresh();

                Notification::make()
                    ->title('Penyesuaian Berhasil Ditambahkan')
                    ->success()
                    ->send();
            });
    }

    /**
     * Action Modal: Delete Penyesuaian
     */
    public function deleteAdjustmentAction(): Action
    {
        return Action::make('deleteAdjustment')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->iconButton()
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $adj = RkaAdjustment::findOrFail($arguments['adjustment_id']);
                $adj->delete();

                $this->record->refresh();

                Notification::make()
                    ->title('Penyesuaian Dihapus')
                    ->success()
                    ->send();
            });
    }
}
