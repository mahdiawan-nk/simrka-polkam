@props(['record', 'activeTabGroup', 'isAllItems' => false, 'isPageForm' => false])

@php
    $activeIndicator = $record->indicators->firstWhere('id', $activeTabGroup) ?? $record->indicators->first();
    $currentGroupIndicators = $record->indicators->where('program_name', $activeIndicator?->program_name);

    // Filter items berdasarkan rka_indicator_id
    $currentItems = $record->items->where('rka_indicator_id', $activeIndicator?->id);

    // Filter status jika $isAllItems bernilai false
    if (!$isAllItems) {
        $currentItems = $currentItems->whereIn('status', ['APPROVED', 'REJECTED_WITH_ADJUSTMENT', 'REJECTED']);
    }

    $totalUsulan = $currentItems->sum('proposed_total');
    $totalDisetujui = $currentItems->sum('approved_total') ?? $totalUsulan;
@endphp
<div
    class="bg-white dark:bg-gray-800 rounded-b-xl rounded-tr-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm space-y-6 ">
    <!-- HEADER TEMPLATE KOP EXCEL RKA -->
    <div
        class="text-center font-bold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-4 border-gray-200 dark:border-gray-700">
        <h3 class="text-base">RENCANA KERJA DAN ANGGARAN</h3>
        <h2 class="text-xl text-primary-700 dark:text-primary-400">POLITEKNIK KAMPAR</h2>
        <h4 class="text-sm font-normal text-gray-600 dark:text-gray-300">UNIT KERJA:
            {{ strtoupper($record->department->name ?? 'UPT ICT') }}</h4>
        <p class="text-xs font-normal text-gray-500">TAHUN ANGGARAN
            {{ $record->schedule->fiscal_year ?? '2026/2027' }}
            ({{ $record->schedule->period_range ?? 'September 2026 – Agustus 2027' }})</p>
    </div>

    <!-- GRID INFORMASI DOKUMEN HEADER -->
    <div
        class="grid grid-cols-1 md:grid-cols-12 gap-2 text-sm bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="md:col-span-2 font-semibold text-gray-700 dark:text-gray-300">Program</div>
        <div class="md:col-span-7 font-bold text-gray-900 dark:text-white">:
            {{ $activeIndicator->program_name }}</div>
        <div class="md:col-span-3 text-right">
            <span
                class="inline-block border border-gray-400 dark:border-gray-600 text-xs px-3 py-1 font-mono font-bold text-gray-800 dark:text-gray-200">
                FORMULIR RKA-{{ $activeIndicator->account->code ?? '5.3' }}
            </span>
        </div>

        <div class="md:col-span-2 font-semibold text-gray-700 dark:text-gray-300">Kegiatan</div>
        <div class="md:col-span-10 font-medium text-gray-900 dark:text-white">:
            {{ $activeIndicator->activity_name }}</div>

        <div class="md:col-span-2 font-semibold text-gray-700 dark:text-gray-300">Lokasi Kegiatan</div>
        <div class="md:col-span-10 text-gray-900 dark:text-white">: {{ $activeIndicator->location }}</div>

        <div class="md:col-span-2 font-semibold text-gray-700 dark:text-gray-300">Jumlah Usulan</div>
        <div class="md:col-span-10 font-bold text-emerald-600 dark:text-emerald-400">
            : Rp {{ number_format($totalUsulan, 0, ',', '.') }}
            <span
                class="font-normal italic text-xs text-gray-500 ml-2">({{ \Illuminate\Support\Str::title(\Illuminate\Support\Number::spell($totalUsulan, 'id')) }}
                Rupiah)</span>
        </div>

        <div class="md:col-span-2 font-semibold text-gray-700 dark:text-gray-300">Jumlah Disetujui</div>
        <div class="md:col-span-10 font-bold text-blue-600 dark:text-blue-400">
            : Rp {{ number_format($totalDisetujui, 0, ',', '.') }}
            <span
                class="font-normal italic text-xs text-gray-500 ml-2">({{ \Illuminate\Support\Str::title(\Illuminate\Support\Number::spell($totalDisetujui, 'id')) }}
                Rupiah)</span>
        </div>
    </div>

    <!-- TABLE 1: MATRIKS INDIKATOR DAN TOLAK UKUR KINERJA -->
    <!-- TABLE 1: MATRIKS INDIKATOR DAN TOLAK UKUR KINERJA -->
    <div class="overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-700">
        <table class="w-full text-xs text-left border-collapse">
            <thead>
                <tr
                    class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-center uppercase border-b border-gray-300 dark:border-gray-600">
                    <th colspan="4" class="py-2.5 px-3">INDIKATOR DAN TOLAK UKUR KINERJA BELANJA</th>
                </tr>
                <tr
                    class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-300 dark:border-gray-600">
                    <th class="py-2 px-3 w-1/5 border-r border-gray-300 dark:border-gray-600">Indikator</th>
                    <th class="py-2 px-3 border-r border-gray-300 dark:border-gray-600">Tolak Ukur Kinerja</th>
                    <th class="py-2 px-3 w-28 text-center border-r border-gray-300 dark:border-gray-600">Target</th>
                    <th class="py-2 px-3 w-28 text-center">Satuan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                @forelse($activeIndicator?->indicator_matrix ?? [] as $matrix)
                    @php
                        $category = strtolower($matrix['indicator_category'] ?? '');
                        $isMasukan = $category === 'masukan';
                        $targetValue = $isMasukan
                            ? ($isPageForm
                                ? $totalUsulan
                                : $totalDisetujui)
                            : $matrix['target_value'] ?? null;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td
                            class="py-2 px-3 font-semibold text-gray-800 dark:text-gray-200 border-r border-gray-200 dark:border-gray-700">
                            {{ $matrix['indicator_category'] ?? '-' }}
                        </td>
                        <td
                            class="py-2 px-3 text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">
                            {{ $matrix['performance_benchmark'] ?? '-' }}
                        </td>
                        <td
                            class="py-2 px-3 text-center text-gray-900 dark:text-white font-medium border-r border-gray-200 dark:border-gray-700">
                            @if (is_numeric($targetValue))
                                {{ number_format((float) $targetValue, 0, ',', '.') }}
                            @else
                                {{ $targetValue ?? '-' }}
                            @endif
                        </td>
                        <td class="py-2 px-3 text-center text-gray-700 dark:text-gray-300">
                            {{ $matrix['unit_target'] ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-4 text-center font-sans text-gray-500 dark:text-gray-400">
                            Belum ada matriks indikator kinerja untuk program ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $slot }}
</div>
