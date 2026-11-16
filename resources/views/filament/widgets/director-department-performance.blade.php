<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Performa Penyerapan Anggaran Departemen (Top 5 & Lowest 5)
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Top 5 Penyerapan Terbesar -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-brand-accent flex items-center gap-1">
                    <x-heroicon-m-arrow-trending-up class="w-4 h-4" /> Top 5 Penyerapan Tertinggi
                </h4>
                <div class="space-y-2">
                    @foreach($top5 as $item)
                        <div class="p-3 bg-brand-accent/5 dark:bg-brand-accent/10 rounded-lg border border-brand-accent/20 dark:border-brand-accent/30">
                            <div class="flex justify-between items-center mb-1 text-xs">
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $item['name'] }} ({{ $item['code'] }})</span>
                                <span class="font-mono font-bold text-brand-accent">{{ $item['percentage'] }}%</span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5">
                                <div class="bg-brand-accent h-1.5 rounded-full" style="width: {{ min($item['percentage'], 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Bottom 5 Penyerapan Terkecil -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400 flex items-center gap-1">
                    <x-heroicon-m-arrow-trending-down class="w-4 h-4" /> Top 5 Penyerapan Terendah
                </h4>
                <div class="space-y-2">
                    @foreach($bottom5 as $item)
                        <div class="p-3 bg-rose-50/50 dark:bg-rose-950/20 rounded-lg border border-rose-200/70 dark:border-rose-800/40">
                            <div class="flex justify-between items-center mb-1 text-xs">
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $item['name'] }} ({{ $item['code'] }})</span>
                                <span class="font-mono font-bold text-rose-700 dark:text-rose-400">{{ $item['percentage'] }}%</span>
                            </div>
                            <div class="w-full bg-rose-200 dark:bg-rose-900 rounded-full h-1.5">
                                <div class="bg-rose-700 h-1.5 rounded-full" style="width: {{ min($item['percentage'], 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>