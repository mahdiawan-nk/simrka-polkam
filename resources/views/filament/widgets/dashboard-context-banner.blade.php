<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Selamat datang, {{ $userName }}
                </h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Workspace: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $roleLabel }}</span>
                    &bull;
                    Unit: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $departmentName }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center rounded-md bg-amber-50 px-2.5 py-1 font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300">
                    TA {{ $fiscalYear }} &mdash; {{ $phaseName }}
                </span>
                <span class="text-gray-400 dark:text-gray-500">
                    Diperbarui {{ $updatedAt }}
                </span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
