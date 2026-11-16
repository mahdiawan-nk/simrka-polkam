<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Ringkasan Status Pengajuan &amp; Master Data
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- System Quick Health & Volume Status -->
            <div class="space-y-3">
                <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Distribusi Status Pengajuan</h4>
                <div class="grid grid-cols-5 gap-2 text-center">
                    <div class="bg-slate-100 dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Draft</span>
                        <span class="text-lg font-bold font-mono text-slate-700 dark:text-slate-300">{{ $statusCounts['draft'] }}</span>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-950/40 p-3 rounded-lg border border-blue-200 dark:border-blue-800">
                        <span class="block text-xs text-blue-600 dark:text-blue-400">Diajukan</span>
                        <span class="text-lg font-bold font-mono text-blue-700 dark:text-blue-300">{{ $statusCounts['submitted'] }}</span>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-950/40 p-3 rounded-lg border border-amber-200 dark:border-amber-800">
                        <span class="block text-xs text-amber-700 dark:text-amber-400">Verifikasi</span>
                        <span class="text-lg font-bold font-mono text-amber-700 dark:text-amber-300">{{ $statusCounts['verified'] }}</span>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-950/40 p-3 rounded-lg border border-emerald-200 dark:border-emerald-800">
                        <span class="block text-xs text-emerald-700 dark:text-emerald-400">Disetujui</span>
                        <span class="text-lg font-bold font-mono text-emerald-700 dark:text-emerald-300">{{ $statusCounts['approved'] }}</span>
                    </div>
                    <div class="bg-rose-50 dark:bg-rose-950/40 p-3 rounded-lg border border-rose-200 dark:border-rose-800">
                        <span class="block text-xs text-rose-700 dark:text-rose-400">Ditolak</span>
                        <span class="text-lg font-bold font-mono text-rose-700 dark:text-rose-300">{{ $statusCounts['rejected'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Master Data Summary Widget -->
            <div class="space-y-3">
                <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Ringkasan Master Data</h4>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
                        <span class="text-xs text-slate-500 block">Total Departemen</span>
                        <span class="text-xl font-bold font-mono text-slate-800 dark:text-slate-200">{{ $masterCounts['departments'] }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
                        <span class="text-xs text-slate-500 block">Kode Akun (COA)</span>
                        <span class="text-xl font-bold font-mono text-slate-800 dark:text-slate-200">{{ $masterCounts['accounts'] }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
                        <span class="text-xs text-slate-500 block">Plot Anggaran Aktif</span>
                        <span class="text-xl font-bold font-mono text-slate-800 dark:text-slate-200">{{ $masterCounts['budgets'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>