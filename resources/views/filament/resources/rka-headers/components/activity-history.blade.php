{{-- resources/views/filament/resources/rka-headers/components/activity-history.blade.php --}}
@php
    $actionOptions = $actionOptions ?? ($this->activityActionOptions ?? []);
    $userOptions = $userOptions ?? ($this->activityUserOptions ?? []);
@endphp

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-900">
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">
                Riwayat Aktivitas RKA
            </h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                Catatan aktivitas terbaru pada dokumen RKA ini (read-only).
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (count($actionOptions) > 0)
                <select
                    wire:model.live="logActionFilter"
                    aria-label="Filter jenis aktivitas"
                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700 focus:border-primary-500 focus:outline-none dark:border-white/15 dark:bg-slate-950 dark:text-slate-200"
                >
                    <option value="">Semua Aksi</option>
                    @foreach ($actionOptions as $actionKey => $actionLabel)
                        <option value="{{ $actionKey }}">{{ $actionLabel }}</option>
                    @endforeach
                </select>
            @endif

            @if (count($userOptions) > 0)
                <select
                    wire:model.live="logUserFilter"
                    aria-label="Filter pengguna"
                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700 focus:border-primary-500 focus:outline-none dark:border-white/15 dark:bg-slate-950 dark:text-slate-200"
                >
                    <option value="">Semua Pengguna</option>
                    @foreach ($userOptions as $userId => $userName)
                        <option value="{{ $userId }}">{{ $userName }}</option>
                    @endforeach
                </select>
            @endif

            <span class="text-xs text-slate-500 dark:text-slate-400">
                {{ $logs->total() }} aktivitas
            </span>
        </div>
    </header>

    <div class="divide-y divide-slate-200 dark:divide-white/10">
        @forelse ($logs as $log)
            <div class="flex flex-col gap-1.5 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class([
                            'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold',
                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $log->action_color === 'success',
                            'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' => $log->action_color === 'warning',
                            'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' => $log->action_color === 'danger',
                            'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300' => $log->action_color === 'info',
                            'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => ! in_array($log->action_color, ['success', 'warning', 'danger', 'info'], true),
                        ])>
                            {{ $log->action_label }}
                        </span>

                        @if ($log->subject_label)
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {{ $log->subject_label }}@if ($log->subject_id) #{{ $log->subject_id }}@endif
                            </span>
                        @endif

                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200">
                            {{ $log->user?->name ?? 'Sistem' }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        {{ $log->description }}
                    </p>

                    @if (! empty($log->old_values) || ! empty($log->new_values))
                        <details class="pt-0.5">
                            <summary class="cursor-pointer select-none text-[11px] font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                                Detail perubahan
                            </summary>
                            <div class="mt-1.5 grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5 text-[11px] dark:border-white/10 dark:bg-slate-900/70 sm:grid-cols-2">
                                @if (! empty($log->old_values))
                                    <div>
                                        <span class="font-semibold text-slate-600 dark:text-slate-300">Sebelum:</span>
                                        <pre class="mt-1 overflow-x-auto whitespace-pre-wrap font-mono text-slate-600 dark:text-slate-400">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                                @if (! empty($log->new_values))
                                    <div>
                                        <span class="font-semibold text-slate-600 dark:text-slate-300">Sesudah / Detail:</span>
                                        <pre class="mt-1 overflow-x-auto whitespace-pre-wrap font-mono text-slate-600 dark:text-slate-400">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>

                <time class="shrink-0 text-[11px] font-mono text-slate-400 dark:text-slate-500">
                    {{ $log->created_at?->format('d M Y H:i') ?? '-' }}
                </time>
            </div>
        @empty
            <div class="px-4 py-10 text-center text-xs text-slate-500 dark:text-slate-400">
                Belum ada riwayat aktivitas tercatat untuk dokumen RKA ini.
            </div>
        @endforelse
    </div>

    @if ($logs->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-white/10">
            {{ $logs->links() }}
        </div>
    @endif
</section>
