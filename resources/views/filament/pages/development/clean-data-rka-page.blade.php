<x-filament-panels::page>
    @php
        $db = $previewResult['database'] ?? [];
        $plan = $previewResult['plan'] ?? ['delete_targets' => [], 'preserved_tables' => [], 'total_to_delete' => 0, 'fund_request_count' => 0];
    @endphp

    <div class="space-y-6">
        {{-- Peringatan Environment --}}
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-950/40 dark:text-amber-200">
            <p class="font-semibold">Peringatan Fitur Development</p>
            <p class="mt-1 text-xs">
                Fitur ini hanya aktif pada environment <code>local</code> / <code>development</code> untuk role <code>super_admin</code> dan akan menghapus seluruh data operasional RKA beserta histori aktivitasnya.
            </p>
        </div>

        {{-- A. Environment Information --}}
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Informasi Environment &amp; Database</h3>
            <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-slate-500">Environment Aktif</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $db['environment'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Koneksi</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $db['connection'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Database Aktif</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $db['database'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Status Izin Cleanup</dt>
                    <dd class="mt-0.5 font-semibold {{ ($db['is_allowed_environment'] ?? false) && ($db['is_safe_database'] ?? false) ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ ($db['is_allowed_environment'] ?? false) && ($db['is_safe_database'] ?? false) ? 'Diizinkan (Super Admin)' : 'Diblokir' }}
                    </dd>
                </div>
            </dl>
        </section>

        {{-- B & C. Data Overview & Cleanup Scope --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-xl border border-rose-200 bg-white shadow-sm dark:border-rose-500/30 dark:bg-slate-900">
                <header class="flex items-center justify-between border-b border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/30 dark:bg-rose-950/30">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-800 dark:text-rose-200">
                        Cleanup Scope (Akan Dihapus)
                    </h3>
                    <span class="text-xs font-semibold text-rose-700 dark:text-rose-300">
                        Total: {{ number_format($plan['total_to_delete']) }} record
                    </span>
                </header>
                <div class="divide-y divide-slate-200 dark:divide-white/10">
                    @foreach ($plan['delete_targets'] as $target)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs">
                            <div>
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $target['label'] }} <span class="font-mono text-[11px] text-slate-400">({{ $target['table'] }})</span></p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $target['reason'] }}</p>
                            </div>
                            <span class="font-mono font-bold text-rose-600 dark:text-rose-400">{{ number_format($target['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-500/30 dark:bg-slate-900">
                <header class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/30 dark:bg-emerald-950/30">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-200">
                        Master Data &amp; Modul Lain (Dipertahankan)
                    </h3>
                </header>
                <div class="divide-y divide-slate-200 dark:divide-white/10">
                    @foreach ($plan['preserved_tables'] as $kept)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $kept['label'] }}</span>
                            <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($kept['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Hasil Eksekusi Terakhir --}}
        @if ($executionResult)
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    Ringkasan Eksekusi Cleanup ({{ $executionResult['status'] }})
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Waktu: {{ $executionResult['executed_at'] }} | Pelaksana: {{ $executionResult['executed_by'] }} | Total Dihapus: <strong>{{ number_format($executionResult['total_deleted']) }}</strong> record
                </p>
                @if (! empty($executionResult['deleted_counts']))
                    <div class="mt-3 grid gap-2 text-xs sm:grid-cols-4">
                        @foreach ($executionResult['deleted_counts'] as $table => $count)
                            <div class="rounded-lg border border-slate-200 px-3 py-2 dark:border-white/10">
                                <span class="block font-mono text-[11px] text-slate-500">{{ $table }}</span>
                                <span class="font-bold text-slate-800 dark:text-slate-100">{{ number_format($count) }} dihapus</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
