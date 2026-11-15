<div class="space-y-4 text-xs">
    @forelse ($record->approvals->sortByDesc('created_at') as $log)
        @php
            $actionColor = match (strtolower($log->action)) {
                'approved' => 'bg-emerald-500',
                'rejected' => 'bg-rose-500',
                default    => 'bg-amber-500',
            };

            $badgeColor = match (strtolower($log->action)) {
                'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                default    => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            };
        @endphp

        <div class="relative pl-6 pb-4 border-l-2 border-gray-200 dark:border-gray-700 last:border-l-0 last:pb-0">
            {{-- Dot Status --}}
            <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full {{ $actionColor }} border-2 border-white dark:border-gray-900"></div>

            <div class="flex items-center justify-between gap-2 mb-1">
                <span class="font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                    {{ $log->stage ?? 'Tahap Persetujuan' }}
                </span>
                <span class="text-[11px] text-gray-500 font-mono">
                    {{ $log->created_at ? $log->created_at->format('d M Y, H:i') : '-' }}
                </span>
            </div>

            <div class="flex items-center gap-2 mb-2">
                <span class="font-medium text-gray-700 dark:text-gray-300">
                    {{ $log->user->name ?? 'Sistem' }}
                </span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $badgeColor }}">
                    {{ ucfirst($log->action) }}
                </span>
            </div>

            @if ($log->approved_amount > 0)
                <div class="text-gray-600 dark:text-gray-400 font-mono mb-1">
                    Nominal Disetujui: <span class="font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($log->approved_amount, 0, ',', '.') }}</span>
                </div>
            @endif

            @if (!empty($log->note))
                <div class="p-2.5 bg-gray-50 dark:bg-gray-800/60 rounded border border-gray-200 dark:border-gray-700 italic text-gray-600 dark:text-gray-300">
                    "{{ $log->note }}"
                </div>
            @endif
        </div>
    @empty
        <div class="text-center py-6 text-gray-400 italic">
            Belum ada riwayat persetujuan untuk pengajuan ini.
        </div>
    @endforelse
</div>