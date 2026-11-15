<x-filament-panels::page>
    <div class="space-y-6">
        <!-- HEADER METADATA & STATUS -->
        <div class="p-6 bg-gray-900 border border-gray-800 rounded-xl shadow-md text-white">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-xl font-bold tracking-tight uppercase">
                        RINGKASAN HASIL REVIEW RKA TA {{ $record->schedule?->academic_year ?? '2026/2027' }}
                    </h1>
                    <p class="text-sm text-gray-400 font-medium">
                        POLITEKNIK KAMPAR — {{ strtoupper($record->department?->name) }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span
                        class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        Status: {{ strtoupper($record->status) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- NAVIGASI EXCEL SHEET TABS -->
        <div class="space-y-4">
            <!-- 1. Filter Department Mode Tabs Sheet (Responsive Horizontal Scroll) -->
            <div
                class="bg-gray-900/60 p-3 rounded-xl border border-gray-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <!-- Dropdown + Quick Search -->
                <div class="w-full md:w-96 space-y-1.5">
                    <label for="department-select"
                        class="text-xs font-semibold uppercase tracking-wider text-amber-500 flex items-center gap-1.5">
                        <x-heroicon-m-building-office-2 class="w-4 h-4" />
                        <span>Pilih Department / Unit Work</span>
                    </label>

                    <x-filament::input.wrapper>
                        <x-filament::input.select id="department-select" wire:model.live="selectedDepartmentId"
                            class="font-medium">
                            @foreach ($this->departments as $dept)
                                @php
                                    $isSubmitted = $dept->rka_status === 'submitted';
                                @endphp
                                <option value="{{ $dept->id }}">
                                    {{ $isSubmitted ? '🟢' : '🔴' }} {{ $dept->name }}
                                    ({{ $isSubmitted ? 'Submitted' : 'Draft' }})
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <!-- Ringkasan Quick-Status Progress -->
                <div class="flex items-center gap-2 self-start md:self-end text-xs">
                    <span class="text-gray-400 font-medium">Status Pengajuan:</span>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        {{ $this->departments->where('rka_status', 'submitted')->count() }} Submitted
                    </span>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-rose-500/10 text-rose-400 border border-rose-500/30 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                        {{ $this->departments->where('rka_status', '!=', 'submitted')->count() }} Draft
                    </span>
                </div>
            </div>

            <!-- 2. Navigasi Excel Sheet Tabs Utama (Review RKA) -->
            <div class="border-b border-gray-800">
                <nav class="flex overflow-x-auto space-x-2 text-sm font-semibold scrollbar-none py-1">
                    <button wire:click="setActiveTab('ringkasan')"
                        class="shrink-0 px-4 py-2.5 rounded-t-lg transition-all flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'ringkasan' ? 'bg-amber-500 text-gray-950 font-bold border-b-2 border-amber-400' : 'bg-gray-800/60 text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <x-heroicon-o-chart-bar class="w-4 h-4" />
                        <span>Ringkasan Hasil Review</span>
                    </button>

                    <button wire:click="setActiveTab('sheet_rka')"
                        class="shrink-0 px-4 py-2.5 rounded-t-lg transition-all flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'sheet_rka' ? 'bg-amber-500 text-gray-950 font-bold border-b-2 border-amber-400' : 'bg-gray-800/60 text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <x-heroicon-o-document-text class="w-4 h-4" />
                        <span>Sheet RKA (Review Item)</span>
                    </button>

                    <button wire:click="setActiveTab('penyesuaian')"
                        class="shrink-0 px-4 py-2.5 rounded-t-lg transition-all flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'penyesuaian' ? 'bg-amber-500 text-gray-950 font-bold border-b-2 border-amber-400' : 'bg-gray-800/60 text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <x-heroicon-o-arrows-right-left class="w-4 h-4" />
                        <span>Daftar Penyesuaian Usulan</span>
                    </button>

                    <button wire:click="setActiveTab('rekap')"
                        class="shrink-0 px-4 py-2.5 rounded-t-lg transition-all flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'rekap' ? 'bg-amber-500 text-gray-950 font-bold border-b-2 border-amber-400' : 'bg-gray-800/60 text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <x-heroicon-o-calculator class="w-4 h-4" />
                        <span>Rekapitulasi RKA</span>
                    </button>
                </nav>
            </div>
        </div>

        <div>
            @php
                // Ambil status RKA dari department yang sedang aktif
                $currentDepartmentStatus = $this->record->status ?? 'draft';
                $isCurrentSubmitted = $currentDepartmentStatus === 'submitted';
            @endphp

            @if (!$isCurrentSubmitted)
                <!-- Alert Banner: RKA Masih Draft -->
                <div
                    class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-200 shadow-lg backdrop-blur-sm">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-amber-500/20 p-2 text-amber-400">
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold text-amber-300">
                                    RKA Belum Di-submit (Status: Draft)
                                </h4>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-500/20 px-2.5 py-0.5 text-xs font-semibold text-amber-400 border border-amber-500/40">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                    DRAFT
                                </span>
                            </div>
                            <p class="text-xs text-amber-200/80 leading-relaxed">
                                Department <span
                                    class="font-semibold text-amber-300 underline underline-offset-2">{{ $this->record->department->name ?? 'Unit Work' }}</span>
                                belum melakukan *submission* akhir untuk RKA ini. Data usulan anggaran, indikator,
                                maupun penyesuaian yang tampil di bawah ini masih dapat berubah sewaktu-waktu oleh unit
                                pengusul.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <!-- ================================================================= -->
                <!-- TAB 1: RINGKASAN HASIL REVIEW -->
                <!-- ================================================================= -->
                @if ($activeTab === 'ringkasan')
                    @php
                        $sum = $this->summaryData;
                        $rekapByKelompok = $this->rekapByKelompok;
                    @endphp
                    <div class="space-y-6">
                        <!-- A. POSISI USULAN -->
                        <div class="rounded-lg border border-gray-800 bg-gray-900 shadow-sm overflow-hidden">
                            <div
                                class="bg-gray-800/80 px-4 py-2.5 font-bold text-amber-400 text-sm border-b border-gray-800">
                                A. POSISI USULAN
                            </div>
                            <table class="w-full text-xs text-left divide-y divide-gray-800 font-mono">
                                <tr class="hover:bg-gray-800/40">
                                    <td class="py-2.5 px-4 w-12 text-center text-gray-500">1</td>
                                    <td class="py-2.5 px-4 font-sans text-gray-300">Usulan awal unit</td>
                                    <td class="py-2.5 px-4 text-right font-bold text-white">Rp
                                        {{ number_format($sum['usulan_awal'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="hover:bg-gray-800/40">
                                    <td class="py-2.5 px-4 text-center text-gray-500">2</td>
                                    <td class="py-2.5 px-4 font-sans text-gray-300">Dipindah ke pos pusat / unit lain
                                        (lihat
                                        Daftar Penyesuaian)</td>
                                    <td class="py-2.5 px-4 text-right text-gray-400">Rp
                                        {{ number_format($sum['dipindahkan'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="hover:bg-gray-800/40">
                                    <td class="py-2.5 px-4 text-center text-gray-500">3</td>
                                    <td class="py-2.5 px-4 font-sans text-gray-300">Tidak dianggarkan / sudah
                                        dianggarkan di
                                        tempat lain</td>
                                    <td class="py-2.5 px-4 text-right text-gray-400">Rp
                                        {{ number_format($sum['pos_cost'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="hover:bg-gray-800/40">
                                    <td class="py-2.5 px-4 text-center text-gray-500">3</td>
                                    <td class="py-2.5 px-4 font-sans text-gray-300">Penyesuaian harga satuan / volume
                                        usulan saat review</td>
                                    <td class="py-2.5 px-4 text-right text-gray-400">Rp
                                        {{ number_format($sum['adjustment_price'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="hover:bg-gray-800/40">
                                    <td class="py-2.5 px-4 text-center text-gray-500">4</td>
                                    <td class="py-2.5 px-4 font-sans text-gray-300">Tambahan dari pos/unit lain atau
                                        penambahan
                                        saat review (Daftar Penyesuaian Bagian B)</td>
                                    <td class="py-2.5 px-4 text-right text-emerald-400 font-semibold">Rp
                                        {{ number_format($sum['tambahan_pusat'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="bg-gray-800/50 font-bold border-t border-gray-700">
                                    <td class="py-2.5 px-4 text-center">6</td>
                                    <td class="py-2.5 px-4 font-sans text-white">Usulan yang dibahas dalam RKA unit (1
                                        s.d. 5)
                                    </td>
                                    <td class="py-2.5 px-4 text-right text-amber-400 text-sm">Rp
                                        {{ number_format($sum['usulan_dibahas'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="bg-emerald-950/30 font-bold">
                                    <td class="py-2.5 px-4 text-center text-emerald-400">7</td>
                                    <td class="py-2.5 px-4 font-sans text-emerald-300">Disetujui setelah review</td>
                                    <td class="py-2.5 px-4 text-right text-emerald-400 text-sm">Rp
                                        {{ number_format($sum['disetujui_setelah_review'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="bg-gray-800/80 font-bold">
                                    <td class="py-2.5 px-4 text-center">8</td>
                                    <td class="py-2.5 px-4 font-sans text-gray-300">Persentase disetujui terhadap usulan
                                        yang
                                        dibahas</td>
                                    <td class="py-2.5 px-4 text-right text-amber-300">{{ $sum['percentage'] }}%</td>
                                </tr>
                            </table>
                        </div>

                        <!-- B. RINCIAN PER KELOMPOK BELANJA -->
                        <div class="rounded-lg border border-gray-800 bg-gray-900 shadow-sm overflow-hidden">
                            <div
                                class="bg-gray-800/80 px-4 py-2.5 font-bold text-amber-400 text-sm border-b border-gray-800">
                                B. RINCIAN PER KELOMPOK BELANJA
                            </div>
                            <table class="w-full text-xs text-left divide-y divide-gray-800 font-mono">
                                <thead>
                                    <tr class="bg-gray-800 text-gray-300 uppercase font-bold text-center">
                                        <th class="py-2 px-3 w-16">Kode</th>
                                        <th class="py-2 px-3 text-left">Kelompok Belanja</th>
                                        <th class="py-2 px-3 w-32 text-right">Usulan Awal (Rp)</th>
                                        <th class="py-2 px-3 w-32 text-right">Usulan In Review (Rp)</th>
                                        <th class="py-2 px-3 w-32 text-right">Disetujui (Rp)</th>
                                        <th class="py-2 px-3 w-20 text-center">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @foreach ($rekapByKelompok['items'] as $item)
                                        <tr class="hover:bg-gray-800/40">
                                            <td class="py-2 px-3 text-center font-bold text-gray-400">
                                                {{ $item['code'] }}
                                            </td>
                                            <td class="py-2 px-3 font-sans text-gray-200">
                                                {{ $item['label'] }}
                                            </td>
                                            <td class="py-2 px-3 text-right text-gray-400">
                                                {{ number_format($item['usulan_awal'], 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 text-right text-amber-300 font-medium">
                                                {{ number_format($item['usulan_review'], 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 text-right font-semibold text-emerald-400">
                                                {{ number_format($item['approved'], 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 text-center text-amber-400">
                                                {{ $item['percentage'] }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-800/90 font-bold text-white uppercase">
                                        <td colspan="2" class="py-2.5 px-4 text-right">JUMLAH</td>
                                        <td class="py-2.5 px-3 text-right text-gray-300">
                                            Rp {{ number_format($rekapByKelompok['total_usulan_awal'], 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right text-amber-400">
                                            Rp
                                            {{ number_format($rekapByKelompok['total_usulan_review'], 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right text-emerald-400">
                                            Rp {{ number_format($rekapByKelompok['total_approved'], 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center text-amber-300">
                                            {{ $rekapByKelompok['total_percentage'] }}%
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- ================================================================= -->
                <!-- TAB 2: SHEET RKA (DETAIL REVIEW PER ITEM) -->
                <!-- ================================================================= -->
                @if ($activeTab === 'sheet_rka')
                    <div class="space-y-4" x-data="{
                        selectAll: false,
                        toggleAll(event, items) {
                            this.selectAll = event.target.checked;
                            $wire.set('selectedItems', this.selectAll ? items : []);
                        }
                    }">
                        <!-- Baris Atas: Tab Indikator & Action Bulk Approve / Bulk Move -->
                        <div class="flex items-center justify-between gap-2 border-b border-gray-800 pb-2">
                            <div class="flex gap-2 overflow-x-auto">
                                @foreach ($record->indicators as $ind)
                                    <button wire:click="setActiveIndicatorTab('{{ $ind->id }}')"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $activeIndicatorTab == $ind->id ? 'bg-amber-500 text-gray-950 font-bold' : 'bg-gray-800 text-gray-400 hover:text-white' }}">
                                        {{ $ind->activity_name }}
                                    </button>
                                @endforeach
                            </div>

                            <!-- Tombol Aksi Massal (Bulk Actions) -->
                            @if (count($selectedItems) > 0)
                                <div class="flex items-center gap-2">
                                    {{-- Tombol Bulk Approve --}}
                                    @php $bulkAction = $this->bulkApproveAction(); @endphp
                                    <button type="button" wire:click="{{ $bulkAction->getLivewireClickHandler() }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-md shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                        <x-heroicon-o-check-circle class="w-4 h-4" />
                                        <span>Approve Terpilih ({{ count($selectedItems) }})</span>
                                    </button>

                                    {{-- Tombol Bulk Move / Pos Terpusat --}}
                                    @php $bulkActionMoved = $this->bulkMoveAction(); @endphp
                                    <button type="button"
                                        wire:click="{{ $bulkActionMoved->getLivewireClickHandler() }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-500 rounded-md shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                        <x-heroicon-o-arrow-right-circle class="w-4 h-4" />
                                        <span>Pindahkan Terpilih ({{ count($selectedItems) }})</span>
                                    </button>
                                </div>
                            @endif
                        </div>

                        @php
                            $data = $this->budgetHierarchy;
                            // Kumpulkan seluruh ID item untuk fitur Select All
                            $allItemIds = collect($data['itemsByAccount'] ?? [])
                                ->flatten()
                                ->pluck('id')
                                ->toArray();
                        @endphp

                        <div class="overflow-x-auto rounded-lg border border-gray-800 bg-gray-900 shadow-sm">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr
                                        class="bg-gray-800 text-gray-200 font-bold text-center uppercase border-b border-gray-700">
                                        <th rowspan="2"
                                            class="py-2 px-2 w-10 text-center border-r border-gray-700">
                                            <input type="checkbox" x-model="selectAll"
                                                @change="toggleAll($event, {{ json_encode($allItemIds) }})"
                                                class="rounded border-gray-700 bg-gray-900 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-gray-900" />
                                        </th>
                                        <th rowspan="2" class="py-2 px-3 w-32 border-r border-gray-700">Kode
                                            Rekening</th>
                                        <th rowspan="2" class="py-2 px-3 border-r border-gray-700">Uraian</th>
                                        <th colspan="4"
                                            class="py-1.5 px-3 border-r border-gray-700 bg-amber-900/40 text-amber-200">
                                            USULAN UNIT
                                        </th>
                                        <!-- DITAMBAHKAN: colspan disesuaikan menjadi 3 -->
                                        <th colspan="3"
                                            class="py-1.5 px-3 border-r border-gray-700 bg-emerald-900/40 text-emerald-200">
                                            HASIL REVIEW
                                        </th>
                                        <th rowspan="2" class="py-2 px-2 w-16 text-center">Aksi</th>
                                    </tr>
                                    <tr
                                        class="bg-gray-800/80 text-gray-300 font-bold border-b border-gray-700 text-center">
                                        <th class="py-1.5 px-2 w-14 border-r border-gray-700">Vol</th>
                                        <th class="py-1.5 px-2 w-16 border-r border-gray-700">Satuan</th>
                                        <th class="py-1.5 px-2 w-24 border-r border-gray-700">Harga (Rp)</th>
                                        <th class="py-1.5 px-2 w-28 border-r border-gray-700">Jumlah (Rp)</th>
                                        <th class="py-1.5 px-2 w-14 border-r border-gray-700">Vol</th>
                                        <!-- DITAMBAHKAN: Sub-header Harga (Rp) pada Hasil Review -->
                                        <th class="py-1.5 px-2 w-24 border-r border-gray-700">Harga (Rp)</th>
                                        <th class="py-1.5 px-2 w-28 border-r border-gray-700">Disetujui (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800 font-mono">
                                    @forelse($data['level1Accounts'] as $accL1)
                                        @php $totalsL1 = $data['calculateAccountTotals']($accL1); @endphp
                                        <!-- LEVEL 1: KELOMPOK -->
                                        <tr
                                            class="bg-gray-800 font-extrabold text-white uppercase border-t-2 border-gray-700">
                                            <td></td>
                                            <td class="py-2 px-3 border-r border-gray-700">{{ $accL1->code }}</td>
                                            <td class="py-2 px-3 font-sans border-r border-gray-700">
                                                {{ $accL1->name }}</td>
                                            <td colspan="3" class="border-r border-gray-700"></td>
                                            <td class="py-2 px-2 text-right border-r border-gray-700">
                                                {{ number_format($totalsL1['usulan'], 0, ',', '.') }}
                                            </td>
                                            <td colspan="2" class="border-r border-gray-700"></td>
                                            <td
                                                class="py-2 px-2 text-right text-emerald-400 border-r border-gray-700 font-bold">
                                                {{ number_format($totalsL1['approved'], 0, ',', '.') }}
                                            </td>
                                            <td></td>
                                        </tr>

                                        <!-- LEVEL 2: JENIS -->
                                        @php $level2Accounts = $data['allAccounts']->where('parent_id', $accL1->id)->sortBy('code'); @endphp
                                        @foreach ($level2Accounts as $accL2)
                                            @php $totalsL2 = $data['calculateAccountTotals']($accL2); @endphp
                                            <tr
                                                class="bg-blue-950/40 font-bold text-blue-200 border-t border-blue-900">
                                                <td></td>
                                                <td class="py-2 px-3 border-r border-gray-800">{{ $accL2->code }}
                                                </td>
                                                <td class="py-2 px-3 font-sans border-r border-gray-800 pl-4">
                                                    {{ $accL2->name }}</td>
                                                <td colspan="3" class="border-r border-gray-800"></td>
                                                <td class="py-2 px-2 text-right border-r border-gray-800">
                                                    {{ number_format($totalsL2['usulan'], 0, ',', '.') }}
                                                </td>
                                                <td colspan="2" class="border-r border-gray-800"></td>
                                                <td
                                                    class="py-2 px-2 text-right text-emerald-400 border-r border-gray-800 font-bold">
                                                    {{ number_format($totalsL2['approved'], 0, ',', '.') }}
                                                </td>
                                                <td></td>
                                            </tr>

                                            <!-- LEVEL 3: RINCIAN -->
                                            @php $level3Accounts = $data['allAccounts']->where('parent_id', $accL2->id)->sortBy('code'); @endphp
                                            @foreach ($level3Accounts as $accL3)
                                                @php $totalsL3 = $data['calculateAccountTotals']($accL3); @endphp
                                                <tr
                                                    class="bg-slate-800/90 font-bold text-slate-200 border-t border-b border-slate-700/80">
                                                    <td></td>
                                                    <td
                                                        class="py-2 px-3 border-r border-slate-700/80 font-mono text-slate-300">
                                                        {{ $accL3->code }}
                                                    </td>
                                                    <td
                                                        class="py-2 px-3 font-sans border-r border-slate-700/80 pl-6 uppercase tracking-wide text-[11px] font-extrabold text-slate-100">
                                                        {{ $accL3->name }}
                                                    </td>
                                                    <td colspan="3" class="border-r border-slate-700/80"></td>
                                                    <td
                                                        class="py-2 px-2 text-right border-r border-slate-700/80 font-mono text-slate-300">
                                                        {{ number_format($totalsL3['usulan'], 0, ',', '.') }}
                                                    </td>
                                                    <td colspan="2" class="border-r border-slate-700/80"></td>
                                                    <td
                                                        class="py-2 px-2 text-right text-emerald-400 border-r border-slate-700/80 font-mono font-bold">
                                                        {{ number_format($totalsL3['approved'], 0, ',', '.') }}
                                                    </td>
                                                    <td></td>
                                                </tr>

                                                <!-- ITEM BELANJA UNDER LEVEL 3 -->
                                                @php $childItems = $data['itemsByAccount'][$accL3->id] ?? collect(); @endphp
                                                @foreach ($childItems as $item)
                                                    @php
                                                        $review = $item->review;
                                                        $status = $review?->status ?? 'PENDING';

                                                        $rowClass = match ($status) {
                                                            'APPROVED',
                                                            'ACCEPTED'
                                                                => 'bg-emerald-950/20 hover:bg-emerald-950/30 text-emerald-200',
                                                            'REDUCED'
                                                                => 'bg-amber-950/40 hover:bg-amber-950/50 text-amber-200',
                                                            'MOVED'
                                                                => 'bg-blue-950/40 hover:bg-blue-950/50 text-blue-200',
                                                            'REJECTED_WITH_ADJUSTMENT'
                                                                => 'bg-rose-950/40 hover:bg-rose-950/50 text-rose-300 line-through decoration-rose-500/60',
                                                            'REJECTED',
                                                            'REMOVED',
                                                            'BUDGETED_ELSEWHERE'
                                                                => 'bg-rose-950/30 hover:bg-rose-950/40 text-rose-300 line-through decoration-rose-500/60',
                                                            'PENDING' => 'hover:bg-gray-800/40 text-gray-300',
                                                            default => 'hover:bg-gray-800/30 text-gray-400',
                                                        };

                                                        $badgeClass = match ($status) {
                                                            'APPROVED',
                                                            'ACCEPTED'
                                                                => 'bg-emerald-950 text-emerald-400 border-emerald-700/60',
                                                            'REDUCED'
                                                                => 'bg-amber-950 text-amber-400 border-amber-700/60',
                                                            'MOVED' => 'bg-blue-950 text-blue-400 border-blue-700/60',
                                                            'REJECTED',
                                                            'REJECTED_WITH_ADJUSTMENT',
                                                            'REMOVED',
                                                            'BUDGETED_ELSEWHERE'
                                                                => 'bg-rose-950 text-rose-400 border-rose-700/60',
                                                            'PENDING' => 'bg-gray-800 text-gray-400 border-gray-700',
                                                            default => 'bg-gray-800 text-gray-400 border-gray-700',
                                                        };

                                                        $statusLabel = match ($status) {
                                                            'APPROVED', 'ACCEPTED' => 'APPROVED',
                                                            'MOVED' => 'MOVED',
                                                            'BUDGETED_ELSEWHERE' => 'TERPUSAT / UNIT LAIN',
                                                            'REJECTED_WITH_ADJUSTMENT' => 'ADJUSTED & REJECTED',
                                                            'REJECTED' => 'REJECTED',
                                                            'REMOVED' => 'REMOVED',
                                                            'PENDING' => 'PENDING',
                                                            default => $status,
                                                        };
                                                    @endphp
                                                    <tr class="{{ $rowClass }} transition-colors">
                                                        <!-- CHECKBOX SELEKSI PER BARIS -->
                                                        <td class="py-2 px-2 text-center border-r border-gray-800">
                                                            <input type="checkbox" wire:model.live="selectedItems"
                                                                value="{{ $item->id }}"
                                                                class="rounded border-gray-700 bg-gray-900 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-gray-900" />
                                                        </td>
                                                        <td class="py-2 px-3 border-r border-gray-800"></td>
                                                        <td class="py-2 px-3 pl-10 font-sans border-r border-gray-800">
                                                            <span>{{ $item->description }}</span>

                                                            <!-- Badge Status / Catatan Reviewer jika ada -->
                                                            @if ($review && $review->status !== 'PENDING')
                                                                <div
                                                                    class="mt-0.5 flex items-center gap-1.5 font-mono text-[10px] normal-case">
                                                                    <span
                                                                        class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase border shadow-sm {{ $badgeClass }}">
                                                                        {{ $statusLabel }}
                                                                    </span>
                                                                    @if ($review->review_notes)
                                                                        <span
                                                                            class="text-gray-400 italic truncate max-w-xs"
                                                                            title="{{ $review->review_notes }}">
                                                                            — {{ $review->review_notes }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td class="py-2 px-2 text-center border-r border-gray-800">
                                                            {{ number_format((float) $item->proposed_volume, 0, ',', '.') }}
                                                        </td>
                                                        <td
                                                            class="py-2 px-2 text-center font-sans border-r border-gray-800">
                                                            {{ $item->unit_symbol ?? 'Satuan' }}
                                                        </td>
                                                        <td class="py-2 px-2 text-right border-r border-gray-800">
                                                            {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                                                        </td>
                                                        <td
                                                            class="py-2 px-2 text-right border-r border-gray-800 font-bold">
                                                            {{ number_format((float) $item->proposed_total, 0, ',', '.') }}
                                                        </td>

                                                        <!-- HASIL REVIEW: VOL -->
                                                        <td
                                                            class="py-2 px-2 text-center border-r border-gray-800 text-emerald-400">
                                                            {{ $review && $review->approved_volume !== null ? number_format((float) $review->approved_volume, 0, ',', '.') : '-' }}
                                                        </td>

                                                        <!-- DITAMBAHKAN: HASIL REVIEW: HARGA SATUAN (UNIT PRICE) -->
                                                        <td
                                                            class="py-2 px-2 text-right border-r border-gray-800 text-emerald-400">
                                                            {{ $review && $review->approved_unit_price !== null ? number_format((float) $review->approved_unit_price, 0, ',', '.') : number_format((float) $item->unit_price, 0, ',', '.') }}
                                                        </td>

                                                        <!-- HASIL REVIEW: DISETUJUI (TOTAL) -->
                                                        <td
                                                            class="py-2 px-2 text-right text-emerald-400 font-bold bg-emerald-950/20 border-r border-gray-800">
                                                            {{ number_format((float) ($review?->approved_total ?? 0), 0, ',', '.') }}
                                                        </td>

                                                        <td class="py-2 px-2 text-center">
                                                            {{ ($this->reviewItemAction)(['item_id' => $item->id]) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="11" class="py-8 text-center text-gray-500 font-sans">
                                                Belum ada rincian item belanja.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                <!-- FOOTER TOTAL REKAPITULASI REVIEW -->
                                <!-- FOOTER TOTAL REKAPITULASI REVIEW -->
                                <tfoot>
                                    @php
                                        // Hitung akumulasi total harga review dengan logika fallback volume
                                        $totalReviewPriceSum = $data['level1Accounts']->reduce(function (
                                            $carry,
                                            $accL1,
                                        ) use ($data) {
                                            $items = $data['itemsByAccount'][$accL1->id] ?? collect();

                                            // Tambahkan juga dari turunan akun level 2 & 3
                                            $level2Accounts = $data['allAccounts']->where('parent_id', $accL1->id);
                                            foreach ($level2Accounts as $accL2) {
                                                $level3Accounts = $data['allAccounts']->where('parent_id', $accL2->id);
                                                foreach ($level3Accounts as $accL3) {
                                                    $l3Items = $data['itemsByAccount'][$accL3->id] ?? collect();
                                                    $items = $items->merge($l3Items);
                                                }
                                            }

                                            return $carry +
                                                $items->sum(function ($item) {
                                                    $review = $item->review;
                                                    $unitPrice =
                                                        (float) ($review?->approved_unit_price ?? $item->unit_price);

                                                    // Jika approved_volume null atau 0, gunakan proposed_volume
                                                    $approvedVol = (float) ($review?->approved_volume ?? 0);
                                                    $effectiveVol =
                                                        $approvedVol > 0
                                                            ? $approvedVol
                                                            : (float) $item->proposed_volume;

                                                    return $unitPrice * $effectiveVol;
                                                });
                                        }, 0);
                                    @endphp

                                    <tr class="bg-gray-800 font-bold border-t-2 border-gray-700 text-white">
                                        <td colspan="6"
                                            class="py-3 px-3 text-right uppercase border-r border-gray-700">
                                            JUMLAH TOTAL BELANJA
                                        </td>
                                        <!-- TOTAL JUMLAH USULAN UNIT -->
                                        <td
                                            class="py-3 px-2 text-right font-mono text-amber-400 bg-amber-900/20 border-r border-gray-700 text-sm">
                                            Rp {{ number_format((float) ($data['totalUsulan'] ?? 0), 0, ',', '.') }}
                                        </td>

                                        <!-- HASIL REVIEW: VOL (KOSONG) -->
                                        <td class="border-r border-gray-700"></td>

                                        <!-- HASIL REVIEW: TOTAL HARGA SATUAN / UNIT PRICE (BARU) -->
                                        <td
                                            class="py-3 px-2 text-right font-mono text-emerald-300 bg-emerald-950/20 border-r border-gray-700 text-sm">
                                            Rp {{ number_format($totalReviewPriceSum, 0, ',', '.') }}
                                        </td>

                                        <!-- HASIL REVIEW: TOTAL DISETUJUI -->
                                        <td
                                            class="py-3 px-2 text-right font-mono text-emerald-400 bg-emerald-900/30 border-r border-gray-700 text-sm">
                                            Rp {{ number_format((float) ($data['totalApproved'] ?? 0), 0, ',', '.') }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- ================================================================= -->
                <!-- TAB 3: DAFTAR PENYESUAIAN USULAN -->
                <!-- ================================================================= -->
                @if ($activeTab === 'penyesuaian')
                    <div class="space-y-6">
                        <div
                            class="flex justify-between items-center bg-gray-900 p-4 border border-gray-800 rounded-lg">
                            <p class="text-xs text-gray-300 italic">
                                Usulan asli unit yang tidak lagi tercantum di RKA unit ini, beserta status dan tempat
                                penganggarannya.
                            </p>
                            {{ ($this->addAdjustmentAction)([]) }}
                        </div>

                        <!-- BAGIAN A -->
                        <div class="rounded-lg border border-gray-800 bg-gray-900 overflow-hidden mb-6">
                            <div class="bg-gray-800 px-4 py-2 font-bold text-amber-400 text-xs uppercase">
                                A. USULAN UNIT YANG DIPINDAH ATAU TIDAK DIANGGARKAN DI RKA UNIT INI
                            </div>
                            <table class="w-full text-xs text-left font-mono">
                                <thead>
                                    <tr class="bg-gray-800/60 text-gray-400 border-b border-gray-800 text-center">
                                        <th class="py-2 px-2 w-8">No</th>
                                        <th class="py-2 px-2 w-20">Kode</th>
                                        <th class="py-2 px-3 text-left">Uraian Usulan</th>
                                        <th class="py-2 px-2 w-16 text-center">Vol</th>
                                        <th class="py-2 px-2 w-16 text-center">Satuan</th>
                                        <th class="py-2 px-3 w-24 text-right">Harga (Rp)</th>
                                        <th class="py-2 px-3 w-28 text-right">Jumlah (Rp)</th>
                                        <th class="py-2 px-2 w-24">Status / Aksi</th>
                                        <th class="py-2 px-2 w-28">Unit Tujuan</th>
                                        <th class="py-2 px-3 text-left">Keterangan</th>
                                        <th class="py-2 px-2 w-12 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @forelse($record->adjustments->where('section_type', 'A') as $adj)
                                        <tr class="hover:bg-gray-800/40">
                                            <td class="py-2 px-2 text-center text-gray-500">{{ $loop->iteration }}
                                            </td>
                                            <td class="py-2 px-2 text-center font-bold text-gray-300">
                                                {{ $adj->account?->code ?? '-' }}
                                            </td>
                                            <td class="py-2 px-3 font-sans text-gray-200">{{ $adj->description }}</td>
                                            <td class="py-2 px-2 text-center text-gray-300 font-bold">
                                                {{ number_format($adj->proposed_volume ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-2 text-center text-gray-400 font-sans text-[11px]">
                                                {{ $adj->unit_symbol ?? '-' }}
                                            </td>
                                            <td class="py-2 px-3 text-right text-gray-300">
                                                {{ number_format($adj->unit_price ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 text-right text-gray-300 font-bold">
                                                {{ number_format($adj->proposed_amount ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-2 text-center font-bold text-amber-400">
                                                <span
                                                    class="px-2 py-0.5 text-[10px] rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                    {{ $adj->action_label }}
                                                </span>
                                            </td>
                                            <td class="py-2 px-2 text-center font-bold text-sky-400">
                                                {{ $adj->destinationUnit?->name ?? '-' }}
                                            </td>
                                            <td class="py-2 px-3 font-sans text-gray-400 text-[11px]">
                                                {{ $adj->notes ?? '-' }}
                                            </td>
                                            <td class="py-2 px-2 text-center">
                                                {{ ($this->deleteAdjustmentAction)(['adjustment_id' => $adj->id]) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11"
                                                class="py-4 text-center text-gray-500 font-sans italic">
                                                Tidak ada usulan yang dipindah atau dihapus.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- BAGIAN B -->
                        <div class="rounded-lg border border-gray-800 bg-gray-900 overflow-hidden">
                            <div class="bg-gray-800 px-4 py-2 font-bold text-amber-400 text-xs uppercase">
                                B. POS YANG DIKELOLA UNIT INI UNTUK UNIT LAIN / TAMBAHAN SAAT REVIEW
                            </div>
                            <table class="w-full text-xs text-left font-mono">
                                <thead>
                                    <tr class="bg-gray-800/60 text-gray-400 border-b border-gray-800 text-center">
                                        <th class="py-2 px-2 w-8">No</th>
                                        <th class="py-2 px-2 w-20">Kode</th>
                                        <th class="py-2 px-3 text-left">Uraian</th>
                                        <th class="py-2 px-2 w-28">Unit Asal / Sumber</th>
                                        <th class="py-2 px-2 w-16 text-center">Vol</th>
                                        <th class="py-2 px-2 w-16 text-center">Satuan</th>
                                        <th class="py-2 px-3 w-24 text-right">Harga (Rp)</th>
                                        <th class="py-2 px-3 w-28 text-right">Usulan (Rp)</th>
                                        <th class="py-2 px-3 w-28 text-right">Disetujui (Rp)</th>
                                        <th class="py-2 px-3 text-left">Keterangan</th>
                                        <th class="py-2 px-2 w-20 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @forelse($record->adjustments->where('section_type', 'B') as $adj)
                                        @php
                                            $sourceUnitName = $adj->targetUnit?->code ?? 'Internal / Pusat';
                                        @endphp
                                        <tr class="hover:bg-gray-800/40">
                                            <td class="py-2 px-2 text-center text-gray-500">{{ $loop->iteration }}
                                            </td>
                                            <td class="py-2 px-2 text-center font-bold text-gray-300">
                                                {{ $adj->account?->code ?? '-' }}
                                            </td>
                                            <td class="py-2 px-3 font-sans text-gray-200">
                                                {{ $adj->description }}
                                            </td>
                                            <td class="py-2 px-2 text-center font-bold text-amber-400">
                                                {{ $sourceUnitName }}
                                            </td>
                                            <td class="py-2 px-2 text-center text-gray-300 font-bold">
                                                {{ number_format($adj->proposed_volume ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-2 text-center text-gray-400 font-sans text-[11px]">
                                                {{ $adj->unit_symbol ?? '-' }}
                                            </td>
                                            <td class="py-2 px-3 text-right text-gray-300">
                                                {{ number_format($adj->unit_price ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 text-right text-gray-300">
                                                {{ number_format($adj->proposed_amount ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 text-right font-bold text-emerald-400">
                                                {{ number_format($adj->approved_amount ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 font-sans text-gray-400 text-[11px]">
                                                {{ $adj->notes ?? '-' }}
                                            </td>
                                            <td class="py-2 px-2 text-center">
                                                <div class="flex items-center justify-center space-x-1 gap-2">
                                                    {{-- @if (!$adj->is_imported) --}}
                                                    {{ ($this->importAdjustmentToItemAction)(['adjustment_id' => $adj->id]) }}
                                                    {{ ($this->deleteAdjustmentAction)(['adjustment_id' => $adj->id]) }}
                                                    {{-- @endif --}}
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11"
                                                class="py-4 text-center text-gray-500 font-sans italic">
                                                Tidak ada penambahan pos terpusat atau kiriman untuk unit ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- ================================================================= -->
                <!-- TAB 4: REKAPITULASI RKA & KONTROL PAGU -->
                <!-- ================================================================= -->
                @if ($activeTab === 'rekap')
                    @php $rekap = $this->rekapByKelompok; @endphp
                    <div class="space-y-6">
                        <div class="rounded-lg border border-gray-800 bg-gray-900 overflow-hidden shadow-sm">
                            <div
                                class="bg-gray-800 px-4 py-2.5 font-bold text-amber-400 text-sm uppercase text-center">
                                REKAPITULASI RKA {{ strtoupper($record->department?->name) }} — TA
                                {{ $record->schedule?->academic_year ?? '2026/2027' }}
                            </div>
                            <table class="w-full text-xs text-left font-mono">
                                <thead>
                                    <tr
                                        class="bg-gray-800/80 text-gray-300 font-bold border-b border-gray-700 text-center">
                                        <th class="py-2 px-3 w-16">Kode</th>
                                        <th class="py-2 px-3 text-left">Uraian</th>
                                        <th class="py-2 px-3 w-36 text-right">Usulan Unit (Rp)</th>
                                        <th class="py-2 px-3 w-36 text-right">Disetujui (Rp)</th>
                                        <th class="py-2 px-3 w-32 text-right">Selisih (Rp)</th>
                                        <th class="py-2 px-3 w-28 text-center">% Disetujui</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @foreach ($rekap['items'] as $item)
                                        @php $selisih = $item['approved'] - $item['usulan']; @endphp
                                        <tr class="hover:bg-gray-800/40">
                                            <td class="py-2 px-3 font-bold text-gray-400 text-center">
                                                {{ $item['code'] }}</td>
                                            <td class="py-2 px-3 font-sans font-bold text-gray-200">
                                                {{ $item['label'] }}</td>
                                            <td class="py-2 px-3 text-right text-gray-300">
                                                {{ number_format($item['usulan'], 0, ',', '.') }}</td>
                                            <td class="py-2 px-3 text-right font-semibold text-emerald-400">
                                                {{ number_format($item['approved'], 0, ',', '.') }}</td>
                                            <td
                                                class="py-2 px-3 text-right font-bold {{ $selisih < 0 ? 'text-red-400' : 'text-gray-400' }}">
                                                {{ $selisih < 0 ? '(' . number_format(abs($selisih), 0, ',', '.') . ')' : number_format($selisih, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-3 text-center text-amber-400 font-bold">
                                                {{ $item['percentage'] }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-800 font-extrabold text-white text-sm">
                                        <td colspan="2" class="py-3 px-4 text-right">TOTAL USULAN (BELANJA)</td>
                                        <td class="py-3 px-3 text-right text-amber-400">Rp
                                            {{ number_format($rekap['total_usulan'], 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right text-emerald-400">Rp
                                            {{ number_format($rekap['total_approved'], 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right text-red-400">
                                            Rp
                                            ({{ number_format(abs($rekap['total_approved'] - $rekap['total_usulan']), 0, ',', '.') }})
                                        </td>
                                        <td class="py-3 px-3 text-center text-amber-300">
                                            {{ $rekap['total_percentage'] }}%
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- BOX KONTROL PAGU & HASIL REVIEW -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="rounded-lg border border-gray-800 bg-gray-900 p-4 space-y-3">
                                <h3 class="font-bold text-amber-400 text-xs uppercase border-b border-gray-800 pb-2">
                                    KONTROL
                                    PAGU</h3>
                                <table class="w-full text-xs font-mono">
                                    <tr>
                                        <td class="py-1 text-gray-400 font-sans">Pagu Anggaran Unit</td>
                                        <td class="py-1 text-right text-white font-bold">Rp
                                            {{ number_format($record->pagu_budget, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-400 font-sans">Total Belanja (Disetujui)</td>
                                        <td class="py-1 text-right text-emerald-400 font-bold">Rp
                                            {{ number_format($rekap['total_approved'], 0, ',', '.') }}</td>
                                    </tr>
                                    <tr class="border-t border-gray-800 font-bold">
                                        <td class="py-1.5 text-gray-300 font-sans">Sisa Pagu (Pagu - Belanja)</td>
                                        <td
                                            class="py-1.5 text-right {{ $record->pagu_budget - $rekap['total_approved'] < 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                            Rp
                                            {{ number_format($record->pagu_budget - $rekap['total_approved'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="rounded-lg border border-gray-800 bg-gray-900 p-4 space-y-3">
                                <h3 class="font-bold text-amber-400 text-xs uppercase border-b border-gray-800 pb-2">
                                    HASIL
                                    REVIEW</h3>
                                <table class="w-full text-xs font-mono">
                                    <tr>
                                        <td class="py-1 text-gray-400 font-sans">Baris yang dipangkas reviewer</td>
                                        <td class="py-1 text-right text-amber-400 font-bold">
                                            {{ $record->items->where('is_trimmed', true)->count() }} Item
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-400 font-sans">Total nilai pemangkasan</td>
                                        <td class="py-1 text-right text-red-400 font-bold">
                                            Rp
                                            {{ number_format($rekap['total_usulan'] - $rekap['total_approved'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>


    </div>
</x-filament-panels::page>
