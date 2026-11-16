{{-- Sumber Anggaran RKA Approved --}}
@if ($this->rkaSourceError)
    <div class="rounded-xl border border-red-200 bg-red-50/90 p-4 text-sm text-red-800 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-200">
        <div class="font-semibold">Sumber RKA Approved Tidak Tersedia</div>
        <p class="mt-1 text-xs">{{ $this->rkaSourceError }}</p>
    </div>
@elseif ($this->rkaHeader)
    <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs text-emerald-950 dark:border-emerald-800/70 dark:bg-emerald-950/30 dark:text-emerald-200">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="font-semibold text-emerald-900 dark:text-emerald-100">Sumber Anggaran: {{ $this->rkaSourceName }}</div>
                <div class="mt-0.5 text-emerald-800 dark:text-emerald-300">
                    Periode Aktif: <strong>{{ $this->activePeriodLabel }}</strong> |
                    Unit Pemilik RKA: <strong>{{ $this->rkaHeader->department?->name ?? '-' }}</strong>
                </div>
            </div>
            <x-filament::badge color="success">STATUS RKA: APPROVED</x-filament::badge>
        </div>
    </div>
@endif

{{-- Section B: RINCIAN KEBUTUHAN (BERBASIS ITEM RKA APPROVED) --}}
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
    <div class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
        <div>
            <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">
                B. Rincian Kebutuhan (Sumber: RKA Approved)
            </h3>
            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                Pilih item langsung dari RKA yang telah disahkan. Harga satuan dan pagu mengacu pada RKA Approved.
            </p>
        </div>
        <button
            type="button"
            wire:click="openItemPickerModal"
            @disabled(! $this->rkaHeaderId)
            class="inline-flex items-center gap-x-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
        >
            + Pilih Item RKA
        </button>
    </div>
    <div class="p-4 overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                    <th class="p-2.5 w-10 text-center">No</th>
                    <th class="p-2.5 w-28">Kode Rekening</th>
                    <th class="p-2.5 min-w-[200px]">Uraian Item</th>
                    <th class="p-2.5 w-24 text-center">Vol Disetujui</th>
                    <th class="p-2.5 w-28 text-center">Vol Dimohonkan</th>
                    <th class="p-2.5 w-20 text-center">Satuan</th>
                    <th class="p-2.5 w-32 text-right">Harga Satuan (Rp)</th>
                    <th class="p-2.5 w-36 text-right">Jumlah Dimohonkan (Rp)</th>
                    <th class="p-2.5 w-36 text-right">Sisa Anggaran Item (Rp)</th>
                    <th class="p-2.5 w-12 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($items as $index => $item)
                    @php
                        $itemRem = (float) ($item['remaining_amount'] ?? 0);
                        $itemReq = (float) ($item['total_price'] ?? 0);
                        $isItemOver = $itemReq > $itemRem;
                    @endphp
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                        <td class="p-2 text-center font-medium text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                        <td class="p-2 font-mono font-semibold text-gray-900 dark:text-white">{{ $item['account_code'] ?? '-' }}</td>
                        <td class="p-2 text-gray-800 dark:text-gray-200">
                            <div class="font-medium">{{ $item['description'] ?? '-' }}</div>
                            <div class="text-[11px] text-gray-500">{{ $item['account_name'] ?? '' }}</div>
                        </td>
                        <td class="p-2 text-center font-mono text-gray-600 dark:text-gray-300">
                            {{ number_format((float) ($item['approved_volume'] ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="p-1.5">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                wire:model.live="items.{{ $index }}.volume"
                                class="w-full text-center rounded-md border-0 py-1.5 px-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset {{ $isItemOver ? 'ring-red-500 focus:ring-red-600' : 'ring-gray-300 dark:ring-gray-700 focus:ring-blue-600' }} text-xs transition"
                            />
                        </td>
                        <td class="p-2 text-center text-gray-600 dark:text-gray-300">{{ $item['unit'] ?? '-' }}</td>
                        <td class="p-2 text-right font-mono text-gray-700 dark:text-gray-300">
                            Rp {{ number_format((float) ($item['unit_price'] ?? 0), 0, ',', '.') }}
                        </td>
                        <td class="p-2 text-right font-mono font-semibold {{ $isItemOver ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                            Rp {{ number_format($itemReq, 0, ',', '.') }}
                        </td>
                        <td class="p-2 text-right font-mono {{ $isItemOver ? 'text-red-600 font-semibold' : 'text-emerald-700 dark:text-emerald-400' }}">
                            Rp {{ number_format($itemRem, 0, ',', '.') }}
                        </td>
                        <td class="p-2 text-center">
                            <button
                                type="button"
                                wire:click="removeItemRow({{ $index }})"
                                class="p-1 text-red-600 hover:text-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-6 text-center text-gray-400 italic">
                            Belum ada item RKA yang dipilih. Klik tombol <strong>+ Pilih Item RKA</strong> untuk menambahkan rincian kebutuhan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-gray-50/80 dark:bg-gray-800/50 font-bold border-t border-gray-200 dark:border-gray-800">
                    <td colspan="7" class="p-3 text-right uppercase tracking-wider text-gray-600 dark:text-gray-400">
                        JUMLAH YANG DIMOHONKAN:
                    </td>
                    <td class="p-3 text-right text-sm text-blue-600 dark:text-blue-400 font-extrabold font-mono">
                        Rp {{ number_format($this->totalRequested, 0, ',', '.') }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Section C: REKAP PER KODE AKUN --}}
<div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
    <div class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3.5 border-b border-gray-200 dark:border-gray-800">
        <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">
            C. Rekap Per Kode Akun (Otomatis dari Item RKA)
        </h3>
    </div>
    <div class="p-4 overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                    <th class="p-2.5 w-10 text-center">No</th>
                    <th class="p-2.5 w-28">Kode</th>
                    <th class="p-2.5">Nama Akun</th>
                    <th class="p-2.5">Catatan</th>
                    <th class="p-2.5 w-36 text-right">Jumlah Dimohonkan</th>
                    <th class="p-2.5 w-36 text-right">Pagu Unit</th>
                    <th class="p-2.5 w-36 text-right">Sisa Pagu</th>
                    <th class="p-2.5 w-44 text-center">Status Validasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($this->accountSummaries as $i => $summary)
                    @php
                        $statusBadge = match ($summary['status']) {
                            'VALID' => 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 ring-green-600/20',
                            default => 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 ring-red-600/20',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                        <td class="p-2.5 text-center text-gray-500">{{ $i + 1 }}</td>
                        <td class="p-2.5 font-mono font-semibold text-gray-900 dark:text-white">{{ $summary['account_code'] }}</td>
                        <td class="p-2.5 text-gray-800 dark:text-gray-200">{{ $summary['account_name'] }}</td>
                        <td class="p-2.5 text-gray-500 italic">{{ $summary['note'] }}</td>
                        <td class="p-2.5 text-right font-mono font-semibold text-gray-900 dark:text-white">
                            Rp {{ number_format($summary['total_requested'], 0, ',', '.') }}
                        </td>
                        <td class="p-2.5 text-right font-mono text-gray-600 dark:text-gray-400">
                            Rp {{ number_format($summary['pagu_unit'], 0, ',', '.') }}
                        </td>
                        <td class="p-2.5 text-right font-mono {{ $summary['remaining_after_request'] < 0 ? 'text-red-600 font-semibold' : 'text-emerald-700 dark:text-emerald-400' }}">
                            Rp {{ number_format($summary['remaining_after_request'], 0, ',', '.') }}
                        </td>
                        <td class="p-2.5 text-center">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $statusBadge }}">
                                {{ $summary['status'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-6 text-center text-gray-400 italic">
                            Pilih item RKA pada Rincian Kebutuhan di atas untuk melihat rekapitulasi pagu rekening.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Slide-over Pemilihan Item RKA Approved --}}
<x-filament::modal
    id="rka-item-picker-modal"
    :slide-over="true"
    :sticky-header="true"
    :sticky-footer="true"
    :restores-focus="true"
    width="6xl"
    icon="heroicon-o-document-magnifying-glass"
    icon-color="primary"
>
    <x-slot name="heading">
        Pilih Item dari RKA Approved
    </x-slot>
    <x-slot name="description">
        <span>Pilih item belanja dari RKA yang telah disahkan pada periode anggaran aktif.</span>
        @if ($this->rkaSourceName || $this->activePeriodLabel)
            <span class="mt-1 block text-xs font-medium text-primary-700 dark:text-primary-300">
                {{ $this->rkaSourceName }}{{ $this->activePeriodLabel ? ' • ' . $this->activePeriodLabel : '' }}
            </span>
        @endif
    </x-slot>

    @php
        $filteredItems = $this->selectableRkaItems;
        $totalAvailableCount = $this->totalAvailableRkaItemsCount;
        $hasActiveFilter = $itemSearch !== '' || $itemFormTypeFilter !== '' || $itemAccountFilter !== '';
        $selectedIds = collect($items)->pluck('rka_item_id')->filter()->map(fn($id) => (int) $id)->all();
    @endphp

    <div class="space-y-4">
        {{-- Sticky Filter Area --}}
        <div class="sticky top-0 z-10 -mx-1 space-y-3 rounded-xl border border-gray-200 bg-gray-50/95 p-3.5 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
            {{-- Baris 1: Search Item (Full Width) --}}
            <div>
                <label for="rka-item-search" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Cari Item RKA
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4" />
                    </span>
                    <input
                        id="rka-item-search"
                        type="text"
                        wire:model.live.debounce.300ms="itemSearch"
                        placeholder="Cari uraian item, program, atau kode rekening..."
                        class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-9 pr-9 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                    @if ($itemSearch !== '')
                        <button
                            type="button"
                            wire:click="$set('itemSearch', '')"
                            title="Hapus pencarian"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        >
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            </div>

            {{-- Baris 2: Kelompok Belanja, Kode Rekening, Reset Filter --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:items-end">
                <div>
                    <label for="rka-form-type-filter" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Kelompok Belanja
                    </label>
                    <select
                        id="rka-form-type-filter"
                        wire:model.live="itemFormTypeFilter"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-2.5 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">Semua Kelompok Belanja</option>
                        <option value="5.1">5.1 — Belanja Pegawai</option>
                        <option value="5.2">5.2 — Belanja Barang dan Jasa</option>
                        <option value="5.3">5.3 — Belanja Modal</option>
                        <option value="5.4">5.4 — Belanja Hibah</option>
                        <option value="5.5">5.5 — Belanja Pajak</option>
                        <option value="2.1">2.1 — Pembayaran Kewajiban Jangka Pendek</option>
                    </select>
                </div>

                <div>
                    <label for="rka-account-filter" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Kode Rekening
                    </label>
                    <select
                        id="rka-account-filter"
                        wire:model.live="itemAccountFilter"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-2.5 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">Semua Kode Rekening</option>
                        @foreach ($this->selectableAccounts as $accOpt)
                            <option value="{{ $accOpt['id'] }}">{{ $accOpt['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-between gap-2 lg:justify-end">
                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        icon="heroicon-o-arrow-path"
                        wire:click="resetItemFilters"
                        :disabled="! $hasActiveFilter"
                        class="w-full lg:w-auto"
                    >
                        Reset Filter
                    </x-filament::button>
                </div>
            </div>

            {{-- Filter Summary & Loading Indicator --}}
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200/80 pt-2 text-[11px] text-gray-600 dark:border-gray-700 dark:text-gray-400">
                <span>
                    Menampilkan <strong>{{ $filteredItems->count() }}</strong> item dari <strong>{{ $totalAvailableCount }}</strong> item tersedia
                </span>
                <span
                    wire:loading
                    wire:target="itemSearch,itemFormTypeFilter,itemAccountFilter,resetItemFilters,selectRkaItem"
                    class="inline-flex items-center gap-1.5 font-medium text-primary-600 dark:text-primary-400"
                >
                    <x-filament::loading-indicator class="h-3.5 w-3.5" />
                    Memuat item RKA...
                </span>
            </div>
        </div>

        {{-- Content Area --}}
        <div
            wire:loading.class="opacity-60 pointer-events-none transition-opacity"
            wire:target="itemSearch,itemFormTypeFilter,itemAccountFilter,resetItemFilters"
        >
            @if ($filteredItems->isNotEmpty())
                {{-- Desktop / Tablet Table --}}
                <div class="hidden overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 md:block">
                    <table class="w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="p-3 min-w-[240px]">Kode & Uraian Item</th>
                                <th class="p-3 min-w-[160px]">Program / Kegiatan</th>
                                <th class="p-3 whitespace-nowrap text-right">Disetujui</th>
                                <th class="p-3 whitespace-nowrap text-right">Sudah Digunakan</th>
                                <th class="p-3 whitespace-nowrap text-right">Sisa Anggaran</th>
                                <th class="p-3 text-center">Status</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($filteredItems as $rkaOpt)
                                @php
                                    $isPicked = in_array($rkaOpt['rka_item_id'], $selectedIds, true);
                                    $hasRemaining = (float) $rkaOpt['remaining_amount'] > 0;
                                @endphp
                                <tr class="transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                    <td class="p-3 align-top">
                                        <div class="font-mono text-[11px] font-semibold text-primary-700 dark:text-primary-400">
                                            {{ $rkaOpt['account_code'] }}
                                        </div>
                                        <div class="mt-0.5 font-medium leading-snug text-gray-900 dark:text-white break-words">
                                            {{ $rkaOpt['description'] }}
                                        </div>
                                        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ number_format($rkaOpt['approved_volume'], 2, ',', '.') }} {{ $rkaOpt['unit'] }} × Rp {{ number_format($rkaOpt['approved_unit_price'], 0, ',', '.') }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-top leading-snug text-gray-600 dark:text-gray-300 break-words">
                                        {{ $rkaOpt['program_activity'] ?: '-' }}
                                    </td>
                                    <td class="p-3 align-top whitespace-nowrap text-right font-mono text-gray-800 dark:text-gray-200">
                                        Rp {{ number_format($rkaOpt['approved_total'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-3 align-top whitespace-nowrap text-right font-mono text-gray-500 dark:text-gray-400">
                                        Rp {{ number_format($rkaOpt['used_amount'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-3 align-top whitespace-nowrap text-right font-mono font-semibold {{ $hasRemaining ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                                        Rp {{ number_format($rkaOpt['remaining_amount'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-3 align-top text-center">
                                        <x-filament::badge size="sm" color="{{ $rkaOpt['status'] === 'TRIMMED' ? 'warning' : 'success' }}">
                                            {{ $rkaOpt['status'] }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="p-3 align-top whitespace-nowrap text-center">
                                        @if ($isPicked)
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                Sudah dipilih
                                            </span>
                                        @else
                                            <x-filament::button
                                                type="button"
                                                size="xs"
                                                color="primary"
                                                wire:click="selectRkaItem({{ $rkaOpt['rka_item_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="selectRkaItem({{ $rkaOpt['rka_item_id'] }})"
                                            >
                                                Pilih Item
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Card List --}}
                <div class="space-y-3 md:hidden">
                    @foreach ($filteredItems as $rkaOpt)
                        @php
                            $isPicked = in_array($rkaOpt['rka_item_id'], $selectedIds, true);
                            $hasRemaining = (float) $rkaOpt['remaining_amount'] > 0;
                        @endphp
                        <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-900 space-y-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-mono text-[11px] font-semibold text-primary-700 dark:text-primary-400">
                                        {{ $rkaOpt['account_code'] }}
                                    </div>
                                    <div class="mt-0.5 text-xs font-semibold text-gray-900 dark:text-white">
                                        {{ $rkaOpt['description'] }}
                                    </div>
                                </div>
                                <x-filament::badge size="sm" color="{{ $rkaOpt['status'] === 'TRIMMED' ? 'warning' : 'success' }}">
                                    {{ $rkaOpt['status'] }}
                                </x-filament::badge>
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $rkaOpt['program_activity'] ?: '-' }} • {{ number_format($rkaOpt['approved_volume'], 2, ',', '.') }} {{ $rkaOpt['unit'] }} × Rp {{ number_format($rkaOpt['approved_unit_price'], 0, ',', '.') }}
                            </div>
                            <div class="grid grid-cols-3 gap-2 rounded-lg bg-gray-50 p-2 text-[11px] dark:bg-gray-800/70">
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Disetujui</div>
                                    <div class="font-mono font-medium text-gray-900 dark:text-white">Rp {{ number_format($rkaOpt['approved_total'], 0, ',', '.') }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Digunakan</div>
                                    <div class="font-mono text-gray-600 dark:text-gray-300">Rp {{ number_format($rkaOpt['used_amount'], 0, ',', '.') }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400">Sisa</div>
                                    <div class="font-mono font-semibold {{ $hasRemaining ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-500' }}">Rp {{ number_format($rkaOpt['remaining_amount'], 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <div>
                                @if ($isPicked)
                                    <span class="block w-full rounded-lg bg-gray-100 py-1.5 text-center text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        Sudah dipilih
                                    </span>
                                @else
                                    <x-filament::button
                                        type="button"
                                        size="sm"
                                        color="primary"
                                        class="w-full"
                                        wire:click="selectRkaItem({{ $rkaOpt['rka_item_id'] }})"
                                    >
                                        Pilih Item
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty State --}}
                <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                    <x-heroicon-o-magnifying-glass class="mx-auto h-8 w-8 text-gray-400" />
                    <h4 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">Item tidak ditemukan</h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Tidak ada item RKA yang sesuai dengan pencarian atau filter yang dipilih.
                    </p>
                    @if ($hasActiveFilter)
                        <div class="mt-4">
                            <x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="resetItemFilters">
                                Reset Filter
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <x-slot name="footerActions">
        <div class="flex w-full flex-wrap items-center justify-between gap-2">
            <span class="text-xs text-gray-600 dark:text-gray-400">
                <strong>{{ count($items) }}</strong> item terpilih dalam pengajuan saat ini
            </span>
            <x-filament::button
                type="button"
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'rka-item-picker-modal' })"
            >
                Tutup
            </x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>
