@props([
    'summary' => [],
    'rekap' => [],
])

@php
    $sum = $summary;
    $rekapByKelompok = $rekap;

    $rupiah = fn($value) => number_format((float) ($value ?? 0), 0, ',', '.');
    $rupiahAbs = fn($value) => number_format(abs((float) ($value ?? 0)), 0, ',', '.');
@endphp

<div class="space-y-6 text-slate-800 dark:text-slate-100">

    {{-- A. POSISI USULAN --}}
    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">

        {{-- Section Header --}}
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/60">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-sm font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                    A
                </div>
                <div>
                    <h3 class="text-sm font-bold tracking-tight text-slate-900 dark:text-white">
                        Posisi Usulan
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Ringkasan usulan awal, penyesuaian, dan hasil review
                    </p>
                </div>
            </div>

            <span
                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                Ringkasan Anggaran
            </span>
        </div>

        {{-- Summary Table --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[650px] text-sm">
                <thead class="sr-only">
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Uraian</th>
                        <th scope="col">Nilai</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">

                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="w-14 px-5 py-3.5 text-center text-xs font-semibold text-slate-400">01</td>
                        <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-200">
                            Usulan awal unit
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-semibold tabular-nums text-slate-900 dark:text-white">
                            <span
                                class="mr-1 text-xs font-normal text-slate-400">Rp</span>{{ $rupiahAbs($sum['usulan_awal'] ?? 0) }}
                        </td>
                    </tr>

                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3.5 text-center text-xs font-semibold text-slate-400">02</td>
                        <td class="px-4 py-3.5 text-slate-700 dark:text-slate-200">
                            <div class="font-medium">Dipindah ke pos pusat / unit lain</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Lihat Daftar Penyesuaian
                            </div>
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-3.5 text-right font-mono tabular-nums text-slate-600 dark:text-slate-300">
                            <span
                                class="mr-1 text-xs font-normal text-slate-400">Rp</span>{{ $rupiahAbs($sum['dipindahkan'] ?? 0) }}
                        </td>
                    </tr>

                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3.5 text-center text-xs font-semibold text-slate-400">03</td>
                        <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-200">
                            Tidak dianggarkan / sudah dianggarkan di tempat lain
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-3.5 text-right font-mono tabular-nums text-slate-600 dark:text-slate-300">
                            <span
                                class="mr-1 text-xs font-normal text-slate-400">Rp</span>{{ $rupiahAbs($sum['pos_cost'] ?? 0) }}
                        </td>
                    </tr>

                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3.5 text-center text-xs font-semibold text-slate-400">04</td>
                        <td class="px-4 py-3.5 text-slate-700 dark:text-slate-200">
                            <div class="font-medium">Tambahan dari pos/unit lain atau penambahan saat review</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Daftar Penyesuaian Bagian B
                            </div>
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                            <span class="mr-1 text-xs font-normal">Rp</span>{{ $rupiahAbs($sum['tambahan_pusat'] ?? 0) }}
                        </td>
                    </tr>

                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3.5 text-center text-xs font-semibold text-slate-400">05</td>
                        <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-200">
                            Penyesuaian harga satuan / volume usulan saat review
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-3.5 text-right font-mono tabular-nums text-slate-600 dark:text-slate-300">
                            <span
                                class="mr-1 text-xs font-normal text-slate-400">Rp</span>{{ $rupiahAbs($sum['adjustment_price'] ?? 0) }}
                        </td>
                    </tr>

                    {{-- Total usulan dibahas --}}
                    <tr
                        class="border-y border-amber-200 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/[0.07]">
                        <td class="px-5 py-4 text-center text-xs font-bold text-amber-700 dark:text-amber-300">06</td>
                        <td class="px-4 py-4">
                            <div class="font-bold text-slate-900 dark:text-white">
                                Usulan yang dibahas dalam RKA unit
                            </div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Akumulasi komponen 1 sampai 5
                            </div>
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-4 text-right font-mono text-base font-bold tabular-nums text-amber-700 dark:text-amber-300">
                            <span class="mr-1 text-xs font-normal">Rp</span>{{ $rupiahAbs($sum['usulan_dibahas'] ?? 0) }}
                        </td>
                    </tr>

                    {{-- Total disetujui --}}
                    <tr
                        class="border-b border-emerald-200 bg-emerald-50/70 dark:border-emerald-500/20 dark:bg-emerald-500/[0.07]">
                        <td class="px-5 py-4 text-center text-xs font-bold text-emerald-700 dark:text-emerald-300">07
                        </td>
                        <td class="px-4 py-4 font-bold text-emerald-800 dark:text-emerald-300">
                            Disetujui setelah review
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-4 text-right font-mono text-base font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                            <span
                                class="mr-1 text-xs font-normal">Rp</span>{{ $rupiahAbs($sum['disetujui_setelah_review'] ?? 0) }}
                        </td>
                    </tr>

                    {{-- Persentase persetujuan --}}
                    <tr class="bg-slate-50 dark:bg-slate-800/60">
                        <td class="px-5 py-4 text-center text-xs font-bold text-slate-500 dark:text-slate-400">08</td>
                        <td class="px-4 py-4 font-semibold text-slate-700 dark:text-slate-200">
                            Persentase disetujui terhadap usulan yang dibahas
                        </td>
                        <td
                            class="whitespace-nowrap px-5 py-4 text-right font-mono text-base font-bold tabular-nums text-slate-900 dark:text-white">
                            {{ $sum['percentage'] ?? 0 }}<span class="ml-1 text-sm text-slate-500">%</span>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </section>


    {{-- B. RINCIAN PER KELOMPOK BELANJA --}}
    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">

        {{-- Section Header --}}
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/60">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sm font-bold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                    B
                </div>
                <div>
                    <h3 class="text-sm font-bold tracking-tight text-slate-900 dark:text-white">
                        Rincian per Kelompok Belanja
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Perbandingan usulan dalam review dan nilai yang disetujui
                    </p>
                </div>
            </div>

            <span
                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                {{ count($rekapByKelompok['items'] ?? []) }} kelompok
            </span>
        </div>

        {{-- Detail Table --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-400">
                        <th scope="col" class="w-24 px-5 py-3 text-center">Kode</th>
                        <th scope="col" class="px-5 py-3 text-left">Kelompok Belanja</th>
                        <th scope="col" class="w-48 px-5 py-3 text-right">Usulan In Review (Rp)</th>
                        <th scope="col" class="w-48 px-5 py-3 text-right">Disetujui (Rp)</th>
                        <th scope="col" class="w-24 px-5 py-3 text-center">%</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse (($rekapByKelompok['items'] ?? []) as $item)
                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td
                                class="whitespace-nowrap px-5 py-3.5 text-center font-mono text-xs font-semibold text-slate-500 dark:text-slate-400">
                                {{ $item['code'] ?? '—' }}
                            </td>

                            <td class="px-5 py-3.5 font-medium text-slate-700 dark:text-slate-200">
                                {{ $item['label'] ?? '—' }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-3.5 text-right font-mono tabular-nums text-amber-700 dark:text-amber-300">
                                {{ $rupiah($item['usulan_review'] ?? 0) }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                                {{ $rupiah($item['approved'] ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-3.5 text-center">
                                <span
                                    class="inline-flex min-w-14 justify-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                    {{ $item['percentage'] ?? 0 }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-slate-500 dark:text-slate-400">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.7" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8 8h8M8 12h8M8 16h4" />
                                        </svg>
                                    </div>
                                    <p class="font-semibold text-slate-700 dark:text-slate-200">
                                        Belum ada rincian kelompok belanja
                                    </p>
                                    <p class="text-xs">
                                        Rincian akan ditampilkan setelah data tersedia.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr class="border-t border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/80">
                        <td colspan="2"
                            class="px-5 py-4 text-right text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                            Jumlah
                        </td>

                        <td
                            class="whitespace-nowrap px-5 py-4 text-right font-mono font-bold tabular-nums text-amber-700 dark:text-amber-300">
                            <span
                                class="mr-1 text-xs font-normal">Rp</span>{{ $rupiah($rekapByKelompok['total_usulan_review'] ?? 0) }}
                        </td>

                        <td
                            class="whitespace-nowrap px-5 py-4 text-right font-mono font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                            <span
                                class="mr-1 text-xs font-normal">Rp</span>{{ $rupiah($rekapByKelompok['total_approved'] ?? 0) }}
                        </td>

                        <td
                            class="whitespace-nowrap px-5 py-4 text-center font-mono font-bold tabular-nums text-slate-700 dark:text-slate-200">
                            {{ $rekapByKelompok['total_percentage'] ?? 0 }}%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Nilai ditampilkan dalam Rupiah. Persentase mengikuti nilai yang disediakan oleh data komponen.
            </p>
        </div>
    </section>
</div>
