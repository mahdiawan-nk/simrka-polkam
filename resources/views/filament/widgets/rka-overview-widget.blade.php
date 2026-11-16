<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Ringkasan RKA — {{ $scopeLabel }} (TA {{ $fiscalYear }})
        </x-slot>

        @if (! $hasData)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Unit kerja aktif belum tersedia untuk menampilkan ringkasan RKA.
            </p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Draft</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $summary['draft'] }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Diajukan</div>
                    <div class="mt-1 text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $summary['submitted'] }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Dalam Tinjauan</div>
                    <div class="mt-1 text-lg font-semibold text-amber-600 dark:text-amber-400">{{ $summary['in_review'] }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Perlu Perbaikan</div>
                    <div class="mt-1 text-lg font-semibold text-rose-600 dark:text-rose-400">{{ $summary['revision_required'] }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Disetujui</div>
                    <div class="mt-1 text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ $summary['approved'] }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total RKA Disetujui</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        Rp {{ number_format($summary['total_approved'], 0, ',', '.') }}
                    </div>
                    <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                        Usulan: Rp {{ number_format($summary['total_proposed'], 0, ',', '.') }}
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
