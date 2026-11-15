<x-filament-panels::page>
    <div class="space-y-6">
        <!-- CARD 1: INFORMASI GENERAL -->
        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-800">
            <h3 class="flex items-center gap-2 mb-4 text-base font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
                Informasi General Konfigurasi
            </h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Nama Konfigurasi</span>
                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $record->config_name }}</span>
                </div>

                <div>
                    <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Status Keaktifan</span>
                    @if ($record->is_active)
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                            <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> Aktif
                        </span>
                    @else
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            <x-heroicon-o-x-circle class="w-3.5 h-3.5" /> Non-Aktif
                        </span>
                    @endif
                </div>

                <div>
                    <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Setting RKA Terkait</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $record->rkaSetting ? "Setting ID #{$record->rka_setting_id} - Setting Name: {$record->rkaSetting->setting_name}" : 'Global (Tanpa Linked Setting)' }}
                    </span>
                </div>

                <div>
                    <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Dibuat Oleh</span>
                    <span
                        class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->creator?->name ?? 'System / Admin' }}</span>
                </div>

                <div>
                    <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Waktu Dibuat</span>
                    <span
                        class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->created_at?->format('d M Y, H:i T') ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- CARD 2: TARGET DEPARTEMEN -->
        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-800">
            @php
                $deptIds = (array) ($record->selected_department_ids ?? []);
                $departments = \App\Models\Department::whereIn('id', $deptIds)->get();
                $totalDept = $departments->count();
            @endphp

            <!-- HEADER SECTION -->
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2.5">
                    <div
                        class="p-2 rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-heroicon-o-building-office-2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Target Departemen / Unit Terpilih
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Unit kerja yang dikonfigurasikan untuk menerima auto-generate RKA
                        </p>
                    </div>
                </div>

                <!-- BADGE TOTAL COUNTER -->
                <span
                    class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $totalDept }} {{ Str::plural('Unit', $totalDept) }}
                </span>
            </div>

            <!-- CONTENT SECTION -->
            @if ($departments->isNotEmpty())
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($departments as $dept)
                        <div
                            class="flex items-center justify-between p-3 transition-colors border border-gray-100 rounded-lg bg-gray-50/50 hover:bg-gray-100/50 dark:bg-gray-800/40 dark:border-gray-800 dark:hover:bg-gray-800/80">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <x-heroicon-o-check-circle class="flex-shrink-0 w-4 h-4 text-success-500" />
                                <span class="text-xs font-medium text-gray-900 truncate dark:text-white"
                                    title="{{ $dept->name }}">
                                    {{ $dept->name }}
                                </span>
                            </div>

                            @if (!empty($dept->code))
                                <span
                                    class="ml-2 font-mono text-[10px] uppercase font-semibold text-gray-400 dark:text-gray-500">
                                    {{ $dept->code }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <!-- EMPTY STATE -->
                <div
                    class="flex flex-col items-center justify-center py-8 text-center border-2 border-dashed rounded-lg border-gray-200/80 dark:border-gray-800">
                    <x-heroicon-o-building-office class="w-10 h-10 mb-2 text-gray-300 dark:text-gray-600" />
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Belum Ada Departemen Terpilih</p>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Konfigurasikan unit kerja pada mode
                        edit untuk mulai memasukkan target.</p>
                </div>
            @endif
        </div>

        <!-- CARD 3: TEMPLATE INDIKATOR LEVEL 1 -->
        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-800">
            <h3 class="flex items-center gap-2 mb-4 text-base font-semibold text-gray-900 dark:text-white">
                <x-heroicon-o-document-text class="w-5 h-5 text-primary-500" />
                Template Indikator Level 1
            </h3>

            <div class="space-y-4">
                @foreach ($record->template_indicators ?? [] as $code => $tmpl)
                    <div
                        class="p-4 border border-gray-200 rounded-lg dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-primary-600 dark:text-primary-400">
                                Formulir RKA-{{ $tmpl['account_code'] ?? $code }}
                            </h4>
                            <span
                                class="px-2 py-0.5 text-xs font-medium rounded-md {{ !empty($tmpl['is_active']) ? 'bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-400' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ !empty($tmpl['is_active']) ? 'Aktif' : 'Non-Aktif' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-2 mb-4 md:grid-cols-2 text-xs">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Program:</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-white">{{ $tmpl['program_name'] ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Kegiatan:</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-white">{{ $tmpl['activity_name'] ?? '-' }}</span>
                            </div>
                        </div>

                        <!-- TABEL MATRIKS INDIKATOR -->
                        <!-- TABEL MATRIKS INDIKATOR -->
                        @if (!empty($tmpl['matrix']))
                            <div class="overflow-x-auto">
                                <!-- Tambahkan table-fixed agar lebar kolom konsisten -->
                                <table
                                    class="w-full table-fixed text-xs text-left border border-gray-200 dark:border-gray-700">
                                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                        <tr>
                                            <!-- Atur persentase/lebar pasti menggunakan class Tailwind -->
                                            <th class="w-3/12 p-2 border-b border-r dark:border-gray-700">Indikator</th>
                                            <th class="w-5/12 p-2 border-b border-r dark:border-gray-700">Tolok Ukur
                                                Kinerja</th>
                                            <th class="w-2/12 p-2 border-b border-r text-center dark:border-gray-700">
                                                Target</th>
                                            <th class="w-2/12 p-2 border-b text-center dark:border-gray-700">Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                        @foreach ($tmpl['matrix'] as $row)
                                            <tr>
                                                <td
                                                    class="p-2 font-medium border-r dark:border-gray-700 text-gray-900 dark:text-white break-words">
                                                    {{ $row['indicator'] ?? '-' }}
                                                </td>
                                                <td
                                                    class="p-2 border-r dark:border-gray-700 text-gray-600 dark:text-gray-300 break-words">
                                                    {{ $row['benchmark'] ?? '-' }}
                                                </td>
                                                <td
                                                    class="p-2 text-center border-r dark:border-gray-700 text-gray-600 dark:text-gray-300 break-words">
                                                    {{ $row['target'] ?? '-' }}
                                                </td>
                                                <td
                                                    class="p-2 text-center text-gray-600 dark:text-gray-300 break-words">
                                                    {{ $row['unit'] ?? '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
