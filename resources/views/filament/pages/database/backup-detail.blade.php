@php
    use App\Filament\Pages\Database\BackupManagement;
    use Illuminate\Support\Facades\Storage;

    $files = $record->files ?? collect();
    $canDownload = BackupManagement::canAccess();
    $databaseName = config('database.connections.' . config('database.default') . '.database') ?: '-';
    $storageDisks = $files->pluck('disk')->filter()->unique()->values();
    $storageLocation = $storageDisks->isNotEmpty() ? $storageDisks->implode(', ') : 'local';

    $statusColor = match ($record->status) {
        'success' => 'success',
        'processing' => 'info',
        'failed' => 'danger',
        'cancelled' => 'gray',
        default => 'warning',
    };

    $typeLabel = match ($record->type) {
        'full' => 'Full Database',
        'table' => 'Selected Tables',
        default => ucfirst((string) ($record->type ?? '-')),
    };
@endphp

<div class="space-y-6">
    @if ($record->status === 'failed' || filled($record->error_message))
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-700 dark:bg-danger-950/50 dark:text-danger-200">
            <div class="flex items-start gap-2.5">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-danger-600 dark:text-danger-400" />
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">Pesan Error Backup</div>
                    <p class="mt-1 whitespace-pre-wrap break-words font-mono text-xs">
                        {{ $record->error_message ?: 'Proses backup gagal tanpa pesan error spesifik.' }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <x-filament::section heading="Informasi Backup">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Nama Backup</dt>
                <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $record->name ?: '-' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">ID Backup</dt>
                <dd class="mt-1 font-mono text-xs text-gray-900 dark:text-gray-100">
                    #{{ $record->id }}@if ($record->uuid) ({{ $record->uuid }})@endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1">
                    <x-filament::badge :color="$statusColor">
                        {{ strtoupper((string) ($record->status ?? 'unknown')) }}
                    </x-filament::badge>
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Jenis Backup</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">
                    {{ $typeLabel }}
                    @if ($record->type === 'table' && is_array($record->table_names) && count($record->table_names) > 0)
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            ({{ count($record->table_names) }} tabel)
                        </span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Database</dt>
                <dd class="mt-1 font-mono text-xs text-gray-900 dark:text-gray-100">{{ $databaseName }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Lokasi Penyimpanan</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">Disk: <span class="font-mono text-xs">{{ $storageLocation }}</span></dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Waktu Mulai</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $record->started_at?->format('d M Y H:i:s') ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Waktu Selesai</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $record->completed_at?->format('d M Y H:i:s') ?? '-' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Durasi Proses</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">
                    {{ $record->duration_seconds !== null ? $record->duration_seconds . ' detik' : '-' }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Ukuran Backup</dt>
                <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                    {{ BackupManagement::formatBytes((int) ($record->total_size ?? 0)) }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Jumlah File</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ number_format((int) ($record->file_count ?? 0)) }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Dibuat Oleh</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $record->creator?->name ?? '-' }}</dd>
            </div>
        </dl>

        @if (filled($record->description))
            <div class="mt-4 border-t border-gray-200 pt-3 text-sm dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Deskripsi:</span>
                <p class="mt-1 text-gray-700 dark:text-gray-300">{{ $record->description }}</p>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Daftar File Backup">
        @if ($files->isEmpty())
            <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Belum ada file backup yang tersedia untuk riwayat ini.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-300">Nama File</th>
                            <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-300">Format</th>
                            <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-300">Ukuran</th>
                            <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-300">Status</th>
                            <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-300">Waktu Pembuatan</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($files as $file)
                            @php
                                $disk = $file->disk ?: 'local';
                                $path = (string) $file->file_path;
                                $isSafePath = $path !== '' && ! str_contains($path, '..') && ! str_starts_with($path, '/');
                                $fileAvailable = $isSafePath
                                    && $record->status === 'success'
                                    && $file->status === 'success'
                                    && Storage::disk($disk)->exists($path)
                                    && Storage::disk($disk)->size($path) > 0;
                                $fileStatusColor = match ($file->status) {
                                    'success' => 'success',
                                    'failed' => 'danger',
                                    default => 'gray',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2.5 font-mono text-xs text-gray-900 dark:text-gray-100">
                                    {{ $file->file_name }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <x-filament::badge color="gray">
                                        {{ strtoupper((string) ($file->format ?? '-')) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">
                                    {{ BackupManagement::formatBytes((int) ($file->file_size ?? 0)) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <x-filament::badge :color="$fileStatusColor">
                                        {{ strtoupper((string) ($file->status ?? 'unknown')) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">
                                    {{ $file->created_at?->format('d M Y H:i:s') ?? '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    @if ($canDownload && $fileAvailable)
                                        <x-filament::button
                                            tag="a"
                                            size="xs"
                                            color="primary"
                                            icon="heroicon-o-arrow-down-tray"
                                            :href="route('database.backups.files.download', $file)"
                                            download="{{ basename((string) $file->file_name) }}"
                                            data-navigate-ignore="true"
                                        >
                                            Download
                                        </x-filament::button>
                                    @elseif (! $fileAvailable)
                                        <span class="text-xs text-gray-400 dark:text-gray-500">File tidak tersedia</span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">Tidak diizinkan</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</div>
