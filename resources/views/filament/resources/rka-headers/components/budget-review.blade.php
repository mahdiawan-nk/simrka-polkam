@props(['summary', 'kontrol'])

@php
    $formatRupiah = fn ($value) => $value > 0
        ? number_format($value, 0, ',', '.')
        : '—';

    $formatSelisih = function ($value) {
        if ($value < 0) {
            return '(' . number_format(abs($value), 0, ',', '.') . ')';
        }

        return $value > 0 ? number_format($value, 0, ',', '.') : '—';
    };

    $selisihClass = fn ($value) => $value < 0
        ? 'text-rose-600 dark:text-rose-400 font-semibold'
        : 'text-slate-700 dark:text-slate-200';
@endphp

<div class="space-y-8 text-slate-800 dark:text-slate-100">

    {{-- REKAPITULASI ANGGARAN --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-400">Ringkasan anggaran</p>
                <h2 class="mt-1 text-base font-semibold text-slate-900 dark:text-white">Rekapitulasi Usulan Anggaran</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Perbandingan nilai usulan unit, nilai disetujui, dan selisih per akun.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                Hierarki akun Level 1–3
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <th class="whitespace-nowrap px-4 py-3">Kode</th>
                        <th class="min-w-[260px] px-4 py-3">Uraian akun</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right">Usulan unit (Rp)</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right">Disetujui (Rp)</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right">Selisih (Rp)</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right">% disetujui</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($summary['level1Accounts'] as $accL1)
                        @php
                            $tL1 = $summary['calculateTotals']($accL1);
                            $level2Accounts = $summary['allAccounts']->where('parent_id', $accL1->id)->sortBy('code');
                        @endphp
                        <tr class="bg-sky-50/80 font-semibold text-sky-950 dark:bg-sky-950/30 dark:text-sky-100">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $accL1->code }}</td>
                            <td class="px-4 py-3">{{ $accL1->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono tabular-nums">{{ $formatRupiah($tL1['usulan']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono tabular-nums">{{ $formatRupiah($tL1['approved']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono tabular-nums {{ $selisihClass($tL1['selisih']) }}">{{ $formatSelisih($tL1['selisih']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono tabular-nums">{{ $tL1['approved'] > 0 ? number_format($tL1['percentage'], 1, ',', '.') . '%' : '—' }}</td>
                        </tr>

                        @foreach($level2Accounts as $accL2)
                            @php
                                $tL2 = $summary['calculateTotals']($accL2);
                                $level3Accounts = $summary['allAccounts']->where('parent_id', $accL2->id)->sortBy('code');
                            @endphp

                            @if($tL2['usulan'] > 0 || $tL2['approved'] > 0)
                                <tr class="bg-slate-50/70 dark:bg-slate-800/50">
                                    <td class="whitespace-nowrap px-4 py-2.5 pl-7 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $accL2->code }}</td>
                                    <td class="px-4 py-2.5 pl-8 font-medium text-slate-700 dark:text-slate-200">{{ $accL2->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums">{{ $formatRupiah($tL2['usulan']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums">{{ $formatRupiah($tL2['approved']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums {{ $selisihClass($tL2['selisih']) }}">{{ $formatSelisih($tL2['selisih']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums">{{ $tL2['approved'] > 0 ? number_format($tL2['percentage'], 1, ',', '.') . '%' : '—' }}</td>
                                </tr>

                                @foreach($level3Accounts as $accL3)
                                    @php $tL3 = $summary['calculateTotals']($accL3); @endphp
                                    @if($tL3['usulan'] > 0 || $tL3['approved'] > 0)
                                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                            <td class="whitespace-nowrap px-4 py-2.5 pl-10 font-mono text-xs text-slate-400 dark:text-slate-500">{{ $accL3->code }}</td>
                                            <td class="px-4 py-2.5 pl-12 text-slate-600 dark:text-slate-300">{{ $accL3->name }}</td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums">{{ $formatRupiah($tL3['usulan']) }}</td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums">{{ $formatRupiah($tL3['approved']) }}</td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums {{ $selisihClass($tL3['selisih']) }}">{{ $formatSelisih($tL3['selisih']) }}</td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono tabular-nums">{{ $tL3['approved'] > 0 ? number_format($tL3['percentage'], 1, ',', '.') . '%' : '—' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    <span class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H20v15H5.5A1.5 1.5 0 0 1 4 17.5z"/><path d="M4 17.5A1.5 1.5 0 0 1 5.5 16H20M8 8h8M8 11h6"/></svg>
                                    </span>
                                    <p class="font-medium text-slate-700 dark:text-slate-200">Belum ada data rekapitulasi</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Data usulan akan ditampilkan di bagian ini setelah tersedia.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-200 bg-slate-100 font-semibold dark:border-slate-700 dark:bg-slate-800">
                        <td colspan="2" class="px-4 py-4 text-xs uppercase tracking-wide text-slate-600 dark:text-slate-300">Total usulan (belanja + pembayaran hutang)</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right font-mono tabular-nums">{{ number_format($summary['grandTotalUsulan'], 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right font-mono tabular-nums">{{ number_format($summary['grandTotalApproved'], 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right font-mono tabular-nums {{ $selisihClass($summary['grandTotalSelisih']) }}">{{ $formatSelisih($summary['grandTotalSelisih']) }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right font-mono tabular-nums">100,0%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
            <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-sky-500"></span> Kelompok akun</span>
            <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-slate-400"></span> Rincian akun</span>
            <span>Nilai dalam Rupiah (Rp)</span>
        </div>
    </section>

    {{-- KONTROL PAGU DAN HASIL REVIEW --}}
    <section class="space-y-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-400">Evaluasi anggaran</p>
            <h2 class="mt-1 text-base font-semibold text-slate-900 dark:text-white">Kontrol Pagu & Hasil Review</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ringkasan penggunaan pagu dan perubahan yang dilakukan reviewer.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 xl:col-span-3">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Kontrol pagu</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Perbandingan sebelum dan sesudah review.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                                <th class="px-5 py-3 text-left">Indikator</th>
                                <th class="px-4 py-3 text-right">Usulan unit</th>
                                <th class="px-5 py-3 text-right">Setelah review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">Pagu anggaran unit</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format($summary['grandTotalApproved'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums">{{ number_format($summary['grandTotalApproved'], 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">Total belanja</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format($kontrol['total_belanja_usulan'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($kontrol['total_belanja_review'], 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">Sisa pagu (pagu − belanja)</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums {{ $kontrol['sisa_pagu_usulan'] < 0 ? 'font-semibold text-rose-600 dark:text-rose-400' : '' }}">{{ number_format($kontrol['total_belanja_usulan'] - $kontrol['total_belanja_review'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums {{ $kontrol['sisa_pagu_review'] < 0 ? 'font-semibold text-rose-600 dark:text-rose-400' : '' }}">{{ number_format($kontrol['total_belanja_review'] - $kontrol['total_belanja_review'], 0, ',', '.') }}</td>
                            </tr>
                            <tr class="bg-slate-50/80 dark:bg-slate-800/50">
                                <td class="px-5 py-3 font-semibold text-slate-700 dark:text-slate-200">Status anggaran</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $kontrol['sisa_pagu_usulan'] < 0 ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' }}">{{ $kontrol['status_usulan'] }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $kontrol['sisa_pagu_review'] < 0 ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' }}">{{ $kontrol['status_review'] }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 xl:col-span-2">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Hasil review</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Dampak penyesuaian oleh reviewer.</p>
                </div>
                <div class="space-y-4 p-5">
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/70">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Baris yang dipangkas reviewer</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ number_format($kontrol['count_dipangkas'], 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Jumlah rincian yang mengalami penyesuaian.</p>
                    </div>
                    <div class="rounded-xl border border-rose-100 bg-rose-50/70 p-4 dark:border-rose-900/60 dark:bg-rose-950/20">
                        <p class="text-sm text-rose-700 dark:text-rose-300">Total nilai pemangkasan</p>
                        <p class="mt-2 break-words text-xl font-semibold tracking-tight text-rose-700 dark:text-rose-300">Rp {{ number_format(abs($summary['grandTotalSelisih']), 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-rose-600/80 dark:text-rose-300/80">Akumulasi selisih pada rekapitulasi.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>