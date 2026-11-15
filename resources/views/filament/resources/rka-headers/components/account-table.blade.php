{{-- 
    Shared Account Hierarchy Table
    Mode: form | review

    Props:
    - mode: 'form' atau 'review'
    - data: hasil budgetHierarchy atau data review
    - status: status RKA, khusus mode form
    - activeIndicator: indikator aktif, khusus mode form
    - allItemIds: daftar ID item, khusus mode review
--}}

@php
    $mode = $mode ?? 'form';
    $isReview = $mode === 'review';
    $isDraft = ($status ?? null) === 'draft';
    $isWadirKeuangan = auth()->user()?->hasRole('wadir_keuangan') ?? false;

    if ($isReview) {
        $level1Accounts = $data['level1Accounts'] ?? collect();
        $allAccounts = $data['allAccounts'] ?? collect();
        $itemsByAccount = $data['itemsByAccount'] ?? [];
        $calculateTotals = $data['calculateAccountTotals'];
        $totalUsulan = $data['totalUsulan'] ?? 0;
        $totalApproved = $data['totalApproved'] ?? 0;
    } else {
        $level1Accounts = $data['level1Accounts'] ?? collect();
        $allAccounts = $data['allAccounts'] ?? collect();
        $itemsByAccount = $data['itemsByAccount'] ?? [];
        $calculateTotals = $data['calculateAccountTotal'];
        $totalUsulan = $data['totalUsulan'] ?? 0;
        $totalApproved = 0;
    }

    $hasActions = ($isReview && $isWadirKeuangan) || (!$isReview && $isDraft);
    $hasSelection = $isReview && $isWadirKeuangan;
    $columnCount = ($hasSelection ? 1 : 0) + 2 + 4 + ($isReview ? 2 : 0) + ($hasActions ? 1 : 0);

    $cell = 'border border-slate-200 px-3 py-2 align-middle dark:border-white/10';
    $numberCell = $cell . ' whitespace-nowrap text-right tabular-nums';
    $headingCell =
        'border border-slate-300 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-700 dark:border-white/15 dark:text-slate-200';
@endphp

<div
    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-950/5 dark:border-white/10 dark:bg-slate-950 dark:ring-white/5">
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-900">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ $isReview ? 'Rincian dan Hasil Review Anggaran' : 'Rincian Usulan Anggaran' }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                {{ $isReview ? 'Perbandingan nilai usulan dengan nilai yang disetujui.' : 'Format tabel terstruktur seperti lembar kerja spreadsheet.' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span
                class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 font-medium text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                <span class="h-2 w-2 rounded-sm bg-amber-500"></span>
                Usulan Unit
            </span>
            @if ($isReview)
                <span
                    class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-medium text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                    <span class="h-2 w-2 rounded-sm bg-emerald-500"></span>
                    Hasil Review
                </span>
            @endif
        </div>
    </div>

    <div class="max-h-[70vh] overflow-auto">
        <table class="w-full min-w-[1050px] border-collapse text-sm text-slate-700 dark:text-slate-200">
            <thead class="sticky top-0 z-20">
                <tr class="bg-slate-100 dark:bg-slate-800">
                    @if ($hasSelection)
                        <th rowspan="2" class="{{ $headingCell }} w-10 bg-slate-100 dark:bg-slate-800">
                            <input type="checkbox" x-model="selectAll"
                                @change="toggleAll($event, {{ json_encode($allItemIds ?? []) }})"
                                aria-label="Pilih semua item"
                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 dark:border-slate-600 dark:bg-slate-900" />
                        </th>
                    @endif

                    <th rowspan="2" class="{{ $headingCell }} w-36 bg-slate-100 text-left dark:bg-slate-800">
                        Kode Rekening
                    </th>
                    <th rowspan="2"
                        class="{{ $headingCell }} min-w-[280px] bg-slate-100 text-left dark:bg-slate-800">
                        Uraian
                    </th>
                    <th colspan="4"
                        class="border border-amber-200 bg-amber-100 px-3 py-2 text-center text-xs font-bold uppercase tracking-wider text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                        Usulan Unit
                    </th>

                    @if ($isReview)
                        <th colspan="2"
                            class="border border-emerald-200 bg-emerald-100 px-3 py-2 text-center text-xs font-bold uppercase tracking-wider text-emerald-900 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                            Hasil Review
                        </th>
                    @endif

                    @if ($hasActions)
                        <th rowspan="2" class="{{ $headingCell }} w-24 bg-slate-100 dark:bg-slate-800">
                            Aksi
                        </th>
                    @endif
                </tr>

                <tr class="bg-slate-50 dark:bg-slate-800/95">
                    <th class="{{ $headingCell }} w-24">Volume</th>
                    <th class="{{ $headingCell }} w-24">Satuan</th>
                    <th class="{{ $headingCell }} w-40">Harga Satuan (Rp)</th>
                    <th class="{{ $headingCell }} w-44">Jumlah Usulan (Rp)</th>

                    @if ($isReview)
                        <th class="{{ $headingCell }} w-24 border-emerald-200 dark:border-emerald-400/20">Vol.
                            Disetujui</th>
                        <th class="{{ $headingCell }} w-44 border-emerald-200 dark:border-emerald-400/20">Nilai
                            Disetujui (Rp)</th>
                    @endif
                </tr>

                @unless ($isReview)
                    <tr class="bg-slate-50 text-center text-[11px] text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        @if ($hasSelection)
                            <td class="{{ $cell }}"></td>
                        @endif
                        <td class="{{ $cell }}">1</td>
                        <td class="{{ $cell }}">2</td>
                        <td class="{{ $cell }}">3</td>
                        <td class="{{ $cell }}">4</td>
                        <td class="{{ $cell }}">5</td>
                        <td class="{{ $cell }}">6 = 3 × 5</td>
                        @if ($hasActions)
                            <td class="{{ $cell }}"></td>
                        @endif
                    </tr>
                @endunless
            </thead>

            <tbody class="font-mono text-xs">
                @forelse ($level1Accounts as $accL1)
                    @php
                        $totalsL1 = $calculateTotals($accL1);
                        $usulanL1 = $isReview ? $totalsL1['usulan'] ?? 0 : $totalsL1;
                        $approvedL1 = $isReview ? $totalsL1['approved'] ?? 0 : 0;
                        $level2Accounts = $allAccounts->where('parent_id', $accL1->id)->sortBy('code');
                    @endphp

                    <tr
                        class="border-t-2 border-slate-300 bg-slate-200 font-bold uppercase text-slate-900 dark:border-white/20 dark:bg-slate-800 dark:text-white">
                        @if ($hasSelection)
                            <td class="{{ $cell }}"></td>
                        @endif
                        <td class="{{ $cell }} whitespace-nowrap">{{ $accL1->code }}</td>
                        <td class="{{ $cell }} font-sans">{{ $accL1->name }}</td>
                        <td colspan="3" class="{{ $cell }} bg-slate-100/70 dark:bg-slate-900/40"></td>
                        <td class="{{ $numberCell }} bg-slate-100/70 dark:bg-slate-900/40">
                            {{ number_format($usulanL1, 0, ',', '.') }}</td>
                        @if ($isReview)
                            <td class="{{ $cell }}"></td>
                            <td
                                class="{{ $numberCell }} bg-emerald-50 font-bold text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300">
                                {{ number_format($approvedL1, 0, ',', '.') }}</td>
                        @endif
                        @if ($hasActions)
                            <td class="{{ $cell }}"></td>
                        @endif
                    </tr>

                    @foreach ($level2Accounts as $accL2)
                        @php
                            $totalsL2 = $calculateTotals($accL2);
                            $usulanL2 = $isReview ? $totalsL2['usulan'] ?? 0 : $totalsL2;
                            $approvedL2 = $isReview ? $totalsL2['approved'] ?? 0 : 0;
                            $level3Accounts = $allAccounts->where('parent_id', $accL2->id)->sortBy('code');
                        @endphp

                        <tr
                            class="border-t border-blue-200 bg-blue-50 font-semibold text-blue-950 dark:border-blue-400/20 dark:bg-blue-400/10 dark:text-blue-200">
                            @if ($hasSelection)
                                <td class="{{ $cell }}"></td>
                            @endif
                            <td class="{{ $cell }} whitespace-nowrap">{{ $accL2->code }}</td>
                            <td class="{{ $cell }} pl-6 font-sans">{{ $accL2->name }}</td>
                            <td colspan="3" class="{{ $cell }}"></td>
                            <td class="{{ $numberCell }}">{{ number_format($usulanL2, 0, ',', '.') }}</td>
                            @if ($isReview)
                                <td class="{{ $cell }}"></td>
                                <td
                                    class="{{ $numberCell }} bg-emerald-50/70 font-bold text-emerald-800 dark:bg-emerald-400/5 dark:text-emerald-300">
                                    {{ number_format($approvedL2, 0, ',', '.') }}</td>
                            @endif
                            @if ($hasActions)
                                <td class="{{ $cell }}"></td>
                            @endif
                        </tr>

                        @foreach ($level3Accounts as $accL3)
                            @php
                                $totalsL3 = $calculateTotals($accL3);
                                $usulanL3 = $isReview ? $totalsL3['usulan'] ?? 0 : $totalsL3;
                                $approvedL3 = $isReview ? $totalsL3['approved'] ?? 0 : 0;
                                $childItems = $itemsByAccount[$accL3->id] ?? collect();
                            @endphp

                            <tr
                                class="border-t border-emerald-200 bg-emerald-50 font-semibold text-emerald-950 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                                @if ($hasSelection)
                                    <td class="{{ $cell }}"></td>
                                @endif
                                <td class="{{ $cell }} whitespace-nowrap">{{ $accL3->code }}</td>
                                <td class="{{ $cell }} pl-9 font-sans">{{ $accL3->name }}</td>
                                <td colspan="3" class="{{ $cell }}"></td>
                                <td class="{{ $numberCell }}">{{ number_format($usulanL3, 0, ',', '.') }}</td>
                                @if ($isReview)
                                    <td class="{{ $cell }}"></td>
                                    <td
                                        class="{{ $numberCell }} bg-emerald-50/70 font-bold text-emerald-800 dark:bg-emerald-400/5 dark:text-emerald-300">
                                        {{ number_format($approvedL3, 0, ',', '.') }}</td>
                                @endif
                                @if ($hasActions)
                                    <td class="{{ $cell }}"></td>
                                @endif
                            </tr>

                            @foreach ($childItems as $item)
                                <tr @class([
                                    'border-b border-slate-200 transition-colors dark:border-white/10',
                                    'bg-orange-50 text-orange-950 dark:bg-orange-400/10 dark:text-orange-100' =>
                                        $isReview && $item->is_trimmed,
                                    'bg-white hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-900/70' => !(
                                        $isReview && $item->is_trimmed
                                    ),
                                ])>
                                    @if ($hasSelection)
                                        <td class="{{ $cell }} text-center">
                                            <input type="checkbox" wire:model.live="selectedItems"
                                                value="{{ $item->id }}"
                                                aria-label="Pilih item {{ $item->description }}"
                                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 dark:border-slate-600 dark:bg-slate-900" />
                                        </td>
                                    @endif

                                    <td class="{{ $cell }} text-slate-400 dark:text-slate-500">
                                        @php
                                            $statusConfig = [
                                                'MOVED' => [
                                                    'label' => 'Dipindahkan ke Unit Lain',
                                                    'class' =>
                                                        'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/20 dark:bg-blue-400/10 dark:text-blue-300',
                                                ],
                                                'BUDGETED_ELSEWHERE' => [
                                                    'label' => 'Dianggarkan di Unit Lain',
                                                    'class' =>
                                                        'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-400/20 dark:bg-indigo-400/10 dark:text-indigo-300',
                                                ],
                                                'REJECTED_WITH_ADJUSTMENT' => [
                                                    'label' => 'Disesuaikan lalu Ditolak',
                                                    'class' =>
                                                        'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-400/20 dark:bg-orange-400/10 dark:text-orange-300',
                                                ],
                                                'REJECTED' => [
                                                    'label' => 'Ditolak',
                                                    'class' =>
                                                        'border-red-200 bg-red-50 text-red-700 dark:border-red-400/20 dark:bg-red-400/10 dark:text-red-300',
                                                ],
                                                'REMOVED' => [
                                                    'label' => 'Dipangkas / Dihapus',
                                                    'class' =>
                                                        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300',
                                                ],
                                                'PENDING' => [
                                                    'label' => 'Ditunda',
                                                    'class' =>
                                                        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300',
                                                ],
                                                'SHARED_COST' => [
                                                    'label' => 'Dialokasikan untuk Unit Lain',
                                                    'class' =>
                                                        'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-400/20 dark:bg-cyan-400/10 dark:text-cyan-300',
                                                ],
                                                'ADDED_BY_REVIEWER' => [
                                                    'label' => 'Ditambahkan Reviewer',
                                                    'class' =>
                                                        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/20 dark:bg-violet-400/10 dark:text-violet-300',
                                                ],
                                            ];

                                            $itemStatus = $item->status ?? null;
                                            $statusInfo = $statusConfig[$itemStatus] ?? [
                                                'label' => $itemStatus ?: 'Belum Ditentukan',
                                                'class' =>
                                                    'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300',
                                            ];
                                        @endphp

                                        @if ($isReview && filled($itemStatus))
                                            <span title="{{ $statusInfo['label'] }}"
                                                class="ml-2 inline-flex items-center gap-1 rounded-md border px-2 py-1 font-sans text-[10px] font-semibold leading-tight tracking-wide {{ $statusInfo['class'] }}">
                                                {{ $statusInfo['label'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="{{ $cell }} pl-12 font-sans leading-5">
                                        {{ $item->description }}

                                    </td>
                                    <td class="{{ $numberCell }} text-center">
                                        {{ number_format($item->proposed_volume, 0, ',', '.') }}</td>
                                    <td class="{{ $cell }} text-center font-sans">{{ $item->unit_symbol }}
                                    </td>
                                    <td class="{{ $numberCell }}">
                                        {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td class="{{ $numberCell }} font-semibold text-slate-900 dark:text-slate-100">
                                        {{ number_format($item->proposed_total, 0, ',', '.') }}</td>

                                    @if ($isReview)
                                        <td
                                            class="{{ $numberCell }} text-center text-emerald-700 dark:text-emerald-300">
                                            {{ $item->approved_volume ?? '—' }}</td>
                                        <td
                                            class="{{ $numberCell }} font-semibold text-emerald-800 dark:text-emerald-300">
                                            {{ number_format($item->approved_total ?? 0, 0, ',', '.') }}</td>
                                    @endif

                                    @if ($isReview && $isWadirKeuangan)
                                        <td class="{{ $cell }} text-center whitespace-nowrap">
                                            {{ ($this->reviewItemAction)(['item_id' => $item->id]) }}
                                        </td>
                                    @elseif (!$isReview && $isDraft)
                                        <td class="{{ $cell }} text-center whitespace-nowrap">
                                            {{ ($this->editItemAction)(['item_id' => $item->id]) }}
                                            {{ ($this->deleteItemAction)(['item_id' => $item->id]) }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}"
                            class="border border-slate-200 px-4 py-12 text-center font-sans dark:border-white/10">
                            <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                                    </svg>
                                </span>
                                <p class="font-semibold text-slate-700 dark:text-slate-200">Belum ada rincian anggaran
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $isReview ? 'Belum ada rincian item belanja untuk ditinjau.' : 'Tambahkan item belanja pada program ini untuk mulai menyusun anggaran.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>

            <tfoot>
                <tr
                    class="border-t-2 border-slate-400 bg-slate-100 font-bold text-slate-900 dark:border-white/20 dark:bg-slate-800 dark:text-white">
                    <td colspan="{{ $isReview ? ($hasSelection ? 6 : 5) : 5 }}"
                        class="border border-slate-300 px-3 py-3 text-right text-xs uppercase tracking-wide dark:border-white/15">
                        {{ $isReview ? 'Jumlah Total Belanja' : 'Jumlah Belanja ' . strtoupper($activeIndicator->activity_name ?? '') }}
                    </td>
                    <td
                        class="border border-slate-300 px-3 py-3 text-right font-mono tabular-nums dark:border-white/15">
                        Rp {{ number_format($totalUsulan, 0, ',', '.') }}
                    </td>
                    @if ($isReview)
                        <td class="border border-slate-300 dark:border-white/15"></td>
                        <td
                            class="border border-emerald-200 bg-emerald-100 px-3 py-3 text-right font-mono tabular-nums text-emerald-900 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                            Rp {{ number_format($totalApproved, 0, ',', '.') }}
                        </td>
                    @endif
                    @if ($hasActions)
                        <td class="border border-slate-300 dark:border-white/15"></td>
                    @endif
                </tr>
            </tfoot>
        </table>
    </div>

    <div
        class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-4 py-2 text-[11px] text-slate-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-400">
        <span>Angka menggunakan pemisah ribuan Indonesia (.).</span>
        @if ($isReview)
            <span><span class="font-semibold text-emerald-700 dark:text-emerald-300">Hijau</span> = nilai
                review/disetujui</span>
        @else
            <span><span class="font-semibold text-amber-700 dark:text-amber-300">Kuning</span> = kelompok nilai
                usulan</span>
        @endif
    </div>
</div>
