<x-filament-panels::page>
    <div class="space-y-6">
        <!-- BARIS TOMBOL AKSI UTAMA -->
        <div
            class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 divide-y divide-gray-200 dark:divide-gray-800">
            <!-- Header Section: Informational Summary -->
            <div
                class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50 dark:bg-gray-800/30 rounded-t-xl">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Badge Fiscal Year -->
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                            TA {{ $record->schedule->fiscal_year ?? '2026/2027' }}
                        </span>

                        <!-- Dynamic Status Badge -->
                        @php
                            $status = strtolower($record->rka_status ?? ($record->status ?? 'draft'));

                            [$statusBg, $statusText, $statusBorder, $statusDot, $statusLabel] = match ($status) {
                                'approved' => [
                                    'bg-emerald-500/10',
                                    'text-emerald-600 dark:text-emerald-400',
                                    'border-emerald-500/20',
                                    'bg-emerald-500',
                                    'Approved',
                                ],
                                'in_approved' => [
                                    'bg-blue-500/10',
                                    'text-blue-600 dark:text-blue-400',
                                    'border-blue-500/20',
                                    'bg-blue-500',
                                    'In Approved (Siap Disahkan)',
                                ],
                                'in_review' => [
                                    'bg-amber-500/10',
                                    'text-amber-600 dark:text-amber-400',
                                    'border-amber-500/20',
                                    'bg-amber-500',
                                    'In Review',
                                ],
                                'revision_required' => [
                                    'bg-rose-500/10',
                                    'text-rose-600 dark:text-rose-400',
                                    'border-rose-500/20',
                                    'bg-rose-500',
                                    'Revision Required',
                                ],
                                'submitted' => [
                                    'bg-cyan-500/10',
                                    'text-cyan-600 dark:text-cyan-400',
                                    'border-cyan-500/20',
                                    'bg-cyan-500',
                                    'Submitted',
                                ],
                                default => [
                                    'bg-gray-500/10',
                                    'text-gray-600 dark:text-gray-400',
                                    'border-gray-500/20',
                                    'bg-gray-500',
                                    'Draft',
                                ],
                            };
                        @endphp

                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBg }} {{ $statusText }} {{ $statusBorder }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}"></span>
                            {{ $statusLabel }}
                        </span>
                    </div>

                    <h2 class="text-base font-bold text-gray-900 dark:text-white tracking-tight">
                        Rencana Kerja & Anggaran (RKA)
                    </h2>

                    <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        <x-heroicon-m-calendar class="w-3.5 h-3.5 text-gray-400" />
                        Periode: <span
                            class="font-medium text-gray-700 dark:text-gray-300">{{ $record->schedule->period_range ?? 'September 2026 – Agustus 2027' }}</span>
                    </p>
                </div>

                <!-- Metric Highlight Badge -->
                <div
                    class="flex items-center gap-3 px-4 py-2.5 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-2xs self-start md:self-auto">
                    <div class="p-2 bg-emerald-500/10 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <x-heroicon-m-banknotes class="w-5 h-5" />
                    </div>
                    <div>
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400">Total Pagu
                            Unit</span>
                        <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">
                            Rp {{ number_format($record->pagu_budget, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Toolbar Section -->
            @if ($status == 'draft')
                <div
                    class="p-3.5 bg-white dark:bg-gray-900 rounded-b-xl flex flex-wrap items-center justify-between gap-3">
                    <!-- Group 1: Primary Data Entry Actions -->
                    <div class="flex items-center gap-2">
                        {{ $this->addProgramAction }}
                        {{ $this->importExcelAction }}
                    </div>

                    <!-- Group 2: Utilities & Danger Zone Actions -->
                    <div class="flex items-center gap-2">
                        {{ $this->calculatePaguAction }}
                        @if ($this->record->pagu_budget != 0)
                            <div class="h-4 w-px bg-gray-200 dark:bg-gray-700 mx-1"></div>
                            {{ $this->submitRkaAction }}
                        @endif


                        <div class="h-4 w-px bg-gray-200 dark:bg-gray-700 mx-1"></div>

                        {{ $this->clearAllItemsAction }}
                    </div>
                </div>
            @endif

        </div>

        <!-- TABS BENTUK SHEET/PROGRAM -->
        @php
            $indicatorGroups = $record->indicators->groupBy('activity_name');

        @endphp

        @if ($indicatorGroups->isEmpty())
            <div
                class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center border border-dashed border-gray-300 dark:border-gray-700">
                <x-heroicon-o-document-text class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Belum Ada Program & Indikator</h3>
                <p class="text-sm text-gray-500 mb-4">Klik tombol di atas untuk membuat program dan indikator kerja RKA
                    baru.</p>
            </div>
        @else
            <!-- TAB BAR -->
            <div class="border-b border-gray-200 dark:border-gray-700 flex space-x-2 overflow-x-auto">
                @foreach ($indicatorGroups as $progName => $groupIndicators)
                    @php
                        $firstInd = $groupIndicators->first();
                        $isActive = $activeTabGroup == $firstInd->id;
                        // Ambil nama akun dari relasi account, gunakan fallback ke program_name jika null
                        $accountName = $firstInd->account->name ?? $progName;
                        $accountCode = $firstInd->account->code ?? '';
                        $tabLabel = $accountCode ? "{$accountCode} - {$progName}" : $accountName;
                    @endphp
                    <button wire:click="setActiveTab('{{ $firstInd->id }}')"
                        class="px-5 py-3 text-sm font-semibold rounded-t-lg transition-all flex items-center space-x-2 border-b-2 {{ $isActive ? 'bg-white dark:bg-gray-800 border-primary-600 text-primary-600 dark:text-primary-400 shadow-sm' : 'bg-gray-100 dark:bg-gray-900 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        <x-heroicon-o-folder class="w-4 h-4" />
                        <span>{{ \Illuminate\Support\Str::limit($tabLabel, 40) }}</span>
                    </button>
                @endforeach
            </div>

            <!-- KONTEN TAB / SHEET FORMULIR EXCEL -->
            @php
                $activeIndicator =
                    $record->indicators->firstWhere('id', $activeTabGroup) ?? $record->indicators->first();
                $currentGroupIndicators = $record->indicators->where('program_name', $activeIndicator->program_name);
                $currentItems = $record->items->where('rka_indicator_id', $activeIndicator->id);
                $totalUsulan = $currentItems->sum('proposed_total');
                $totalDisetujui = $currentItems->sum('approved_total') ?? $totalUsulan;
            @endphp
            <x-rka-header-account :record="$record" :activeTabGroup="$activeTabGroup" :isAllItems=true :isPageForm=true>


                <!-- SECTION TABLE 2: RINCIAN ANGGARAN BELANJA -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wide">
                            RINCIAN ANGGARAN BELANJA MENURUT PROGRAM DAN KEGIATAN
                        </h4>
                        <div>
                            @if ($status == 'draft')
                                {{ $this->addItemAction }}
                            @endif

                        </div>
                    </div>

                    {{-- <div class="overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-700 shadow-sm">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <!-- HEADER EXCEL DUA TINGKAT -->
                                <tr
                                    class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-center uppercase border-b border-gray-300 dark:border-gray-600">
                                    <th rowspan="2"
                                        class="py-2 px-3 w-32 border-r border-gray-300 dark:border-gray-600">Kode
                                        Rekening</th>
                                    <th rowspan="2" class="py-2 px-3 border-r border-gray-300 dark:border-gray-600">
                                        Uraian</th>
                                    <th colspan="4"
                                        class="py-1.5 px-3 border-r border-gray-300 dark:border-gray-600 bg-amber-100/70 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200">
                                        USULAN UNIT</th>
                                    @if ($status == 'draft')
                                        <th rowspan="2" class="py-2 px-2 w-20 text-center">Aksi</th>
                                    @endif
                                </tr>
                                <tr
                                    class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-300 dark:border-gray-600 text-center">
                                    <th class="py-1.5 px-2 w-16 border-r border-gray-300 dark:border-gray-600">Volume
                                    </th>
                                    <th class="py-1.5 px-2 w-16 border-r border-gray-300 dark:border-gray-600">Satuan
                                    </th>
                                    <th class="py-1.5 px-2 w-28 border-r border-gray-300 dark:border-gray-600">Harga
                                        Satuan (Rp)</th>
                                    <th class="py-1.5 px-2 w-32 border-r border-gray-300 dark:border-gray-600">Jumlah
                                        Usulan (Rp)</th>
                                </tr>
                                <!-- BARIS NOMOR KOLOM EXCEL -->
                                <tr
                                    class="bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 italic text-center border-b border-gray-300 dark:border-gray-600">
                                    <td class="py-1 border-r border-gray-300 dark:border-gray-600">1</td>
                                    <td class="py-1 border-r border-gray-300 dark:border-gray-600">2</td>
                                    <td class="py-1 border-r border-gray-300 dark:border-gray-600">3</td>
                                    <td class="py-1 border-r border-gray-300 dark:border-gray-600">4</td>
                                    <td class="py-1 border-r border-gray-300 dark:border-gray-600">5</td>
                                    <td class="py-1 border-r border-gray-300 dark:border-gray-600">6 = 3 × 5</td>
                                    @if ($status == 'draft')
                                        <td></td>
                                    @endif
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 font-mono">
                                @php
                                    $hierarchy = $this->budgetHierarchy;
                                    $level1Accounts = $hierarchy['level1Accounts'];
                                    $allAccounts = $hierarchy['allAccounts'];
                                    $itemsByAccount = $hierarchy['itemsByAccount'];
                                    $calculateAccountTotal = $hierarchy['calculateAccountTotal'];
                                    $totalUsulan = $hierarchy['totalUsulan'];
                                @endphp
                                @forelse($level1Accounts as $accL1)
                                    @php $totalL1 = $calculateAccountTotal($accL1); @endphp

                                    <!-- LEVEL 1: KELOMPOK (misal: 5 - BELANJA) -->
                                    <tr
                                        class="bg-gray-200 dark:bg-gray-800 font-extrabold text-gray-900 dark:text-white uppercase border-t-2 border-gray-400 dark:border-gray-600">
                                        <td class="py-2 px-3 border-r border-gray-300 dark:border-gray-600">
                                            {{ $accL1->code }}</td>
                                        <td class="py-2 px-3 font-sans border-r border-gray-300 dark:border-gray-600">
                                            {{ $accL1->name }}</td>
                                        <td class="border-r border-gray-300 dark:border-gray-600"></td>
                                        <td class="border-r border-gray-300 dark:border-gray-600"></td>
                                        <td class="border-r border-gray-300 dark:border-gray-600"></td>
                                        <td class="py-2 px-2 text-right border-r border-gray-300 dark:border-gray-600">
                                            {{ number_format($totalL1, 0, ',', '.') }}
                                        </td>
                                        @if ($status == 'draft')
                                            <td></td>
                                        @endif
                                    </tr>

                                    <!-- LEVEL 2: JENIS (misal: 5.2 - Belanja Barang dan Jasa) -->
                                    @php $level2Accounts = $allAccounts->where('parent_id', $accL1->id)->sortBy('code'); @endphp
                                    @foreach ($level2Accounts as $accL2)
                                        @php $totalL2 = $calculateAccountTotal($accL2); @endphp
                                        <tr
                                            class="bg-blue-100/70 dark:bg-blue-950/40 font-bold text-blue-950 dark:text-blue-200 border-t border-blue-200 dark:border-blue-900">
                                            <td class="py-2 px-3 border-r border-gray-200 dark:border-gray-700">
                                                {{ $accL2->code }}</td>
                                            <td
                                                class="py-2 px-3 font-sans border-r border-gray-200 dark:border-gray-700 pl-4">
                                                {{ $accL2->name }}</td>
                                            <td class="border-r border-gray-200 dark:border-gray-700"></td>
                                            <td class="border-r border-gray-200 dark:border-gray-700"></td>
                                            <td class="border-r border-gray-200 dark:border-gray-700"></td>
                                            <td
                                                class="py-2 px-2 text-right border-r border-gray-200 dark:border-gray-700">
                                                {{ number_format($totalL2, 0, ',', '.') }}
                                            </td>
                                            @if ($status == 'draft')
                                                <td></td>
                                            @endif
                                        </tr>

                                        <!-- LEVEL 3: RINCIAN (misal: 5.2.1 - Belanja Bahan Habis Dipakai) -->
                                        @php $level3Accounts = $allAccounts->where('parent_id', $accL2->id)->sortBy('code'); @endphp
                                        @foreach ($level3Accounts as $accL3)
                                            @php $totalL3 = $calculateAccountTotal($accL3); @endphp
                                            <tr
                                                class="bg-emerald-50/70 dark:bg-emerald-950/30 font-semibold text-emerald-950 dark:text-emerald-300 border-t border-emerald-200 dark:border-emerald-800">
                                                <td class="py-2 px-3 border-r border-gray-200 dark:border-gray-700">
                                                    {{ $accL3->code }}</td>
                                                <td
                                                    class="py-2 px-3 font-sans border-r border-gray-200 dark:border-gray-700 pl-6">
                                                    {{ $accL3->name }}</td>
                                                <td class="border-r border-gray-200 dark:border-gray-700"></td>
                                                <td class="border-r border-gray-200 dark:border-gray-700"></td>
                                                <td class="border-r border-gray-200 dark:border-gray-700"></td>
                                                <td
                                                    class="py-2 px-2 text-right border-r border-gray-200 dark:border-gray-700">
                                                    {{ number_format($totalL3, 0, ',', '.') }}
                                                </td>
                                                @if ($status == 'draft')
                                                    <td></td>
                                                @endif
                                            </tr>

                                            <!-- ITEM BELANJA UNDER LEVEL 3 -->
                                            @php $childItems = $itemsByAccount[$accL3->id] ?? collect(); @endphp
                                            @foreach ($childItems as $item)
                                                <tr
                                                    class="hover:bg-amber-50/50 dark:hover:bg-gray-800/60 transition-colors">
                                                    <td class="py-2 px-3 border-r border-gray-200 dark:border-gray-700">
                                                    </td>
                                                    <td
                                                        class="py-2 px-3 pl-10 font-sans text-gray-800 dark:text-gray-200 border-r border-gray-200 dark:border-gray-700">
                                                        {{ $item->description }}
                                                    </td>
                                                    <td
                                                        class="py-2 px-2 text-center border-r border-gray-200 dark:border-gray-700">
                                                        {{ number_format($item->proposed_volume, 0, ',', '.') }}
                                                    </td>
                                                    <td
                                                        class="py-2 px-2 text-center font-sans border-r border-gray-200 dark:border-gray-700">
                                                        {{ $item->unit_symbol }}
                                                    </td>
                                                    <td
                                                        class="py-2 px-2 text-right border-r border-gray-200 dark:border-gray-700">
                                                        {{ number_format($item->unit_price, 0, ',', '.') }}
                                                    </td>
                                                    <td
                                                        class="py-2 px-2 text-right font-medium text-gray-900 dark:text-white bg-amber-50/20 dark:bg-amber-900/10 border-r border-gray-200 dark:border-gray-700">
                                                        {{ number_format($item->proposed_total, 0, ',', '.') }}
                                                    </td>
                                                    @if ($status == 'draft')
                                                        <td
                                                            class="py-2.5 px-2.5 text-center font-sans flex items-center justify-center gap-6">
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
                                        <td colspan="{{ $status == 'draft' ? '7' : '6' }}"
                                            class="py-8 text-center font-sans text-gray-500 dark:text-gray-400">
                                            Belum ada item belanja pada program ini. Klik <strong>Tambah Item
                                                Belanja</strong> untuk menginput data.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            <!-- FOOTER TOTAL ANGGARAN -->
                            <tfoot>
                                <tr
                                    class="bg-gray-200 dark:bg-gray-800 font-bold border-t-2 border-gray-400 dark:border-gray-600 text-gray-900 dark:text-white">
                                    <td colspan="5"
                                        class="py-3 px-3 text-right uppercase border-r border-gray-300 dark:border-gray-600">
                                        JUMLAH BELANJA {{ strtoupper($activeIndicator->activity_name ?? '') }}
                                    </td>
                                    <td
                                        class="py-3 px-2 text-right font-mono text-emerald-700 dark:text-emerald-400 bg-amber-100/50 dark:bg-amber-900/30 border-r border-gray-300 dark:border-gray-600 text-sm">
                                        Rp {{ number_format($totalUsulan, 0, ',', '.') }}
                                    </td>
                                    @if ($status == 'draft')
                                        <td></td>
                                    @endif
                                </tr>
                            </tfoot>
                        </table>
                    </div> --}}
                    @include('filament.resources.rka-headers.components.account-table', [
                        'mode' => 'form',
                        'data' => $this->budgetHierarchy,
                        'status' => $status,
                        'activeIndicator' => $activeIndicator,
                    ])
                </div>
            </x-rka-header-account>
        @endif
    </div>
</x-filament-panels::page>
