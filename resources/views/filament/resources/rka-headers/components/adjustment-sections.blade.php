{{-- resources/views/filament/resources/rka-headers/components/adjustment-sections.blade.php
     Usage:
     @include('filament.resources.rka-headers.components.adjustment-sections', ['record' => $record])
--}}
@php
    $adjustments = $record->adjustments ?? collect();
    $sectionA = $adjustments->where('section_type', 'A');
    $sectionB = $adjustments->where('section_type', 'B');
    $isWadirKeuangan = auth()->user()?->hasRole('wadir_keuangan') ?? false;

    $table = 'w-full min-w-max border-collapse text-left text-xs text-slate-700 dark:text-slate-200';
    $th = 'sticky top-0 border border-slate-200 bg-slate-100 px-3 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:border-white/10 dark:bg-slate-800 dark:text-slate-300';
    $td = 'border border-slate-200 px-3 py-2 align-middle dark:border-white/10';
    $money = $td . ' whitespace-nowrap text-right font-mono tabular-nums';
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950 sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
            Usulan asli unit yang tidak lagi tercantum di RKA unit ini, beserta status dan tempat penganggarannya.
        </p>
        @role('wadir_keuangan')
            <div class="shrink-0">{{ ($this->addAdjustmentAction)([]) }}</div>
        @endrole
    </div>

    {{-- BAGIAN A --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-400/20 dark:bg-amber-400/10">
            <h3 class="text-xs font-bold uppercase tracking-wider text-amber-900 dark:text-amber-200">
                A. Usulan Unit yang Dipindah atau Tidak Dianggarkan di RKA Unit Ini
            </h3>
            <span class="text-xs text-amber-800/80 dark:text-amber-200/70">{{ $sectionA->count() }} data</span>
        </header>
        <div class="overflow-x-auto">
            <table class="{{ $table }}">
                <thead><tr>
                    <th class="{{ $th }} w-12">No</th>
                    <th class="{{ $th }} w-28">Kode</th>
                    <th class="{{ $th }} min-w-[240px] text-left">Uraian Usulan</th>
                    <th class="{{ $th }} w-20">Vol</th>
                    <th class="{{ $th }} w-20">Satuan</th>
                    <th class="{{ $th }} w-36 text-right">Harga (Rp)</th>
                    <th class="{{ $th }} w-36 text-right">Jumlah (Rp)</th>
                    <th class="{{ $th }} min-w-[170px]">Status / Aksi</th>
                    <th class="{{ $th }} min-w-[170px]">Unit Tujuan</th>
                    <th class="{{ $th }} min-w-[220px] text-left">Keterangan</th>
                    @if ($isWadirKeuangan)<th class="{{ $th }} w-16">Aksi</th>@endif
                </tr></thead>
                <tbody>
                    @forelse ($sectionA as $adj)
                        <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-amber-50/60 dark:odd:bg-slate-950 dark:even:bg-white/[0.025] dark:hover:bg-amber-400/5">
                            <td class="{{ $td }} text-center text-slate-400">{{ $loop->iteration }}</td>
                            <td class="{{ $td }} whitespace-nowrap text-center font-mono font-semibold">{{ $adj->account?->code ?? '—' }}</td>
                            <td class="{{ $td }} min-w-[240px] font-medium text-slate-800 dark:text-slate-100">{{ $adj->description }}</td>
                            <td class="{{ $td }} text-center font-mono tabular-nums">{{ number_format($adj->proposed_volume ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ $td }} text-center text-slate-500 dark:text-slate-400">{{ $adj->unit_symbol ?? '—' }}</td>
                            <td class="{{ $money }}">{{ number_format($adj->unit_price ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ $money }} font-semibold text-slate-900 dark:text-slate-100">{{ number_format($adj->proposed_amount ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ $td }} text-center">
                                <span class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold leading-tight text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">{{ $adj->action_label }}</span>
                            </td>
                            <td class="{{ $td }} text-center font-medium text-sky-700 dark:text-sky-300">{{ $adj->destinationUnit?->name ?? '—' }}</td>
                            <td class="{{ $td }} min-w-[220px] whitespace-normal text-slate-500 dark:text-slate-400">{{ $adj->notes ?: '—' }}</td>
                            @if ($isWadirKeuangan)<td class="{{ $td }} text-center">{{ ($this->deleteAdjustmentAction)(['adjustment_id' => $adj->id]) }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isWadirKeuangan ? 11 : 10 }}" class="border border-slate-200 px-4 py-12 text-center dark:border-white/10">
                            <p class="font-semibold text-slate-600 dark:text-slate-300">Belum ada data pada Bagian A</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Usulan yang dipindahkan atau tidak dianggarkan akan ditampilkan di sini.</p>
                        </td></tr>
                    @endforelse
                </tbody>
                @if ($sectionA->isNotEmpty())
                    <tfoot><tr class="bg-slate-100 font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-100">
                        <td colspan="6" class="{{ $td }} text-right uppercase tracking-wide">Total Usulan Bagian A</td>
                        <td class="{{ $money }}">{{ number_format($sectionA->sum(fn ($adj) => (float) ($adj->proposed_amount ?? 0)), 0, ',', '.') }}</td>
                        <td colspan="{{ $isWadirKeuangan ? 4 : 3 }}" class="{{ $td }} text-xs font-normal text-slate-500 dark:text-slate-400">{{ $sectionA->count() }} item</td>
                    </tr></tfoot>
                @endif
            </table>
        </div>
    </section>

    {{-- BAGIAN B --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-400/20 dark:bg-emerald-400/10">
            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-200">
                B. Pos yang Dikelola Unit Ini untuk Unit Lain / Tambahan Saat Review
            </h3>
            <span class="text-xs text-emerald-800/80 dark:text-emerald-200/70">{{ $sectionB->count() }} data</span>
        </header>
        <div class="overflow-x-auto">
            <table class="{{ $table }}">
                <thead><tr>
                    <th class="{{ $th }} w-12">No</th>
                    <th class="{{ $th }} w-28">Kode</th>
                    <th class="{{ $th }} min-w-[240px] text-left">Uraian</th>
                    <th class="{{ $th }} min-w-[160px]">Unit Asal / Sumber</th>
                    <th class="{{ $th }} w-20">Vol</th>
                    <th class="{{ $th }} w-20">Satuan</th>
                    <th class="{{ $th }} w-36 text-right">Harga (Rp)</th>
                    <th class="{{ $th }} w-36 text-right">Usulan (Rp)</th>
                    <th class="{{ $th }} w-36 text-right">Disetujui (Rp)</th>
                    <th class="{{ $th }} min-w-[220px] text-left">Keterangan</th>
                    @if ($isWadirKeuangan)<th class="{{ $th }} w-20">Aksi</th>@endif
                </tr></thead>
                <tbody>
                    @forelse ($sectionB as $adj)
                        @php $sourceUnitName = $adj->targetUnit?->name ?? '—'; @endphp
                        <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-emerald-50/60 dark:odd:bg-slate-950 dark:even:bg-white/[0.025] dark:hover:bg-emerald-400/5">
                            <td class="{{ $td }} text-center text-slate-400">{{ $loop->iteration }}</td>
                            <td class="{{ $td }} whitespace-nowrap text-center font-mono font-semibold">{{ $adj->account?->code ?? '—' }}</td>
                            <td class="{{ $td }} min-w-[240px] font-medium text-slate-800 dark:text-slate-100">{{ $adj->description }}</td>
                            <td class="{{ $td }} text-center font-semibold text-amber-700 dark:text-amber-300">{{ $sourceUnitName }}</td>
                            <td class="{{ $td }} text-center font-mono tabular-nums">{{ number_format($adj->proposed_volume ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ $td }} text-center text-slate-500 dark:text-slate-400">{{ $adj->unit_symbol ?? '—' }}</td>
                            <td class="{{ $money }}">{{ number_format($adj->unit_price ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ $money }}">{{ number_format($adj->proposed_amount ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ $money }} bg-emerald-50/70 font-semibold text-emerald-800 dark:bg-emerald-400/5 dark:text-emerald-300">{{ number_format($adj->approved_amount ?? 0, 0, ',', '.') }}</td>
                            <td class="{{ $td }} min-w-[220px] whitespace-normal text-slate-500 dark:text-slate-400">{{ $adj->notes ?: '—' }}</td>
                            @if ($isWadirKeuangan)
                                <td class="{{ $td }} whitespace-nowrap text-center"><div class="flex items-center justify-center gap-2">
                                    {{ ($this->importAdjustmentToItemAction)(['adjustment_id' => $adj->id]) }}
                                    {{ ($this->deleteAdjustmentAction)(['adjustment_id' => $adj->id]) }}
                                </div></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isWadirKeuangan ? 11 : 10 }}" class="border border-slate-200 px-4 py-12 text-center dark:border-white/10">
                            <p class="font-semibold text-slate-600 dark:text-slate-300">Belum ada data pada Bagian B</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pos dari unit lain atau tambahan reviewer akan ditampilkan di sini.</p>
                        </td></tr>
                    @endforelse
                </tbody>
                @if ($sectionB->isNotEmpty())
                    <tfoot><tr class="bg-slate-100 font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-100">
                        <td colspan="7" class="{{ $td }} text-right uppercase tracking-wide">Total Bagian B</td>
                        <td class="{{ $money }}">{{ number_format($sectionB->sum(fn ($adj) => (float) ($adj->proposed_amount ?? 0)), 0, ',', '.') }}</td>
                        <td class="{{ $money }} bg-emerald-50 font-bold text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300">{{ number_format($sectionB->sum(fn ($adj) => (float) ($adj->approved_amount ?? 0)), 0, ',', '.') }}</td>
                        <td colspan="{{ $isWadirKeuangan ? 2 : 1 }}" class="{{ $td }} text-xs font-normal text-slate-500 dark:text-slate-400">{{ $sectionB->count() }} item</td>
                    </tr></tfoot>
                @endif
            </table>
        </div>
    </section>
</div>
