<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Performa Penyerapan Anggaran Departemen (Top 5 & Lowest 5)
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Top 5 Penyerapan Terbesar -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                    <x-heroicon-m-arrow-trending-up class="w-4 h-4" /> Top 5 Penyerapan Tertinggi
                </h4>
                <div class="space-y-2">
                    @foreach($top5 as $item)
                        <div class="p-3 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-lg border border-emerald-200/60 dark:border-emerald-800/40">
                            <div class="flex justify-between items-center mb-1 text-xs">
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $item['name'] }} ({{ $item['code'] }})</span>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $item['percentage'] }}%</span>
                            </div>
                            <div class="w-full bg-emerald-200 dark:bg-emerald-900 rounded-full h-1.5">
                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ min($item['percentage'], 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Bottom 5 Penyerapan Terkecil -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1">
                    <x-heroicon-m-arrow-trending-down class="w-4 h-4" /> Top 5 Penyerapan Terendah
                </h4>
                <div class="space-y-2">
                    @foreach($bottom5 as $item)
                        <div class="p-3 bg-rose-50/50 dark:bg-rose-950/20 rounded-lg border border-rose-200/60 dark:border-rose-800/40">
                            <div class="flex justify-between items-center mb-1 text-xs">
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $item['name'] }} ({{ $item['code'] }})</span>
                                <span class="font-mono font-bold text-rose-600 dark:text-rose-400">{{ $item['percentage'] }}%</span>
                            </div>
                            <div class="w-full bg-rose-200 dark:bg-rose-900 rounded-full h-1.5">
                                <div class="bg-rose-500 h-1.5 rounded-full" style="width: {{ min($item['percentage'], 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>