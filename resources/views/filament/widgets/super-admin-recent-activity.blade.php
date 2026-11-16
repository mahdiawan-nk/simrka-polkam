<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Aktivitas Sistem Terbaru (RKA &amp; Fund Request)
        </x-slot>

        @if (empty($activities))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Belum ada catatan aktivitas sistem yang tersedia.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pr-4">Waktu</th>
                            <th class="py-2 pr-4">Modul</th>
                            <th class="py-2 pr-4">Unit Kerja</th>
                            <th class="py-2 pr-4">Pengguna</th>
                            <th class="py-2 pr-4">Aktivitas</th>
                            <th class="py-2">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($activities as $activity)
                            <tr>
                                <td class="whitespace-nowrap py-2 pr-4 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $activity['time_formatted'] }}
                                </td>
                                <td class="whitespace-nowrap py-2 pr-4">
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $activity['module'] === 'RKA' ? 'bg-blue-50 text-blue-700 dark:bg-blue-400/10 dark:text-blue-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' }}">
                                        {{ $activity['module'] }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">{{ $activity['department'] }}</td>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">{{ $activity['actor'] }}</td>
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $activity['activity'] }}</td>
                                <td class="py-2 text-xs text-gray-600 dark:text-gray-400">{{ $activity['description'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
