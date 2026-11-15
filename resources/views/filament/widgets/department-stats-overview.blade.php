<x-filament-widgets::widget>
    @if (!$hasDepartment)
        <x-filament::section>
            <div class="text-center py-4 text-gray-500">
                Tidak ada departemen aktif yang terhubung dengan akun Anda.
            </div>
        </x-filament::section>
    @else
        <div class="space-y-4">
            <!-- Header Ringkasan Unit -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        Anggaran {{ $departmentName }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tahun Anggaran {{ $fiscalYear }}</p>
                </div>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Pagu Anggaran Unit -->
                <div
                    class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500">Pagu Anggaran Unit</span>
                        <x-heroicon-m-banknotes class="w-5 h-5 text-blue-500" />
                    </div>
                    <div class="mt-2 text-xl font-bold font-mono text-gray-900 dark:text-white">
                        Rp {{ number_format($allocatedAmount, 2, ',', '.') }}
                    </div>
                </div>

                <!-- Terpakai (Approved) -->
                <div
                    class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500">Terpakai (Approved)</span>
                        <x-heroicon-m-check-circle class="w-5 h-5 text-emerald-500" />
                    </div>
                    <div class="mt-2 text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                        Rp {{ number_format($approvedAmount, 2, ',', '.') }}
                    </div>
                </div>

                <!-- Sedang Diproses (Pending) -->
                <div
                    class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500">Sedang Diproses</span>
                        <x-heroicon-m-clock class="w-5 h-5 text-amber-500" />
                    </div>
                    <div class="mt-2 text-xl font-bold font-mono text-amber-600 dark:text-amber-400">
                        Rp {{ number_format($pendingAmount, 2, ',', '.') }}
                    </div>
                </div>

                <!-- Sisa Pagu Terencana -->
                <div
                    class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500">Sisa Pagu Terencana</span>
                        <x-heroicon-m-scale class="w-5 h-5 text-indigo-500" />
                    </div>
                    <div
                        class="mt-2 text-xl font-bold font-mono {{ $plannedRemaining < 0 ? 'text-rose-600' : 'text-gray-900 dark:text-white' }}">
                        Rp {{ number_format($plannedRemaining, 2, ',', '.') }}
                    </div>
                </div>
            </div>

            <!-- Budget Burn-rate Bar -->
            <x-filament::section>
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Penyerapan Anggaran Unit
                            (Burn-rate)</span>
                        <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $burnRate }}%
                            Terpakai</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                        <div class="h-3 rounded-full transition-all duration-500 {{ $burnRate > 90 ? 'bg-rose-500' : ($burnRate > 75 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                            style="width: {{ min($burnRate, 100) }}%">
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-widgets::widget>
