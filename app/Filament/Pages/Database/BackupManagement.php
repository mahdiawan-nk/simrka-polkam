<?php

namespace App\Filament\Pages\Database;

use App\Jobs\RunDatabaseBackup;
use App\Models\BackupRun;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Forms\Components\CheckboxList;

class BackupManagement extends Page implements HasTable
{
    use InteractsWithTable;

    // protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Backup Database';

    protected static ?string $title = 'Database Backup Management';

    protected static ?string $slug = 'database/backup';

    protected string $view =
    'filament.pages.database.backup-management';

    protected static ?int $navigationSort = 10;


    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label('Buat Backup')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Buat Database Backup')
                ->modalDescription(
                    'Pilih jenis dan format backup database.'
                )
                ->form([
                    TextInput::make('name')
                        ->label('Nama Backup')
                        ->required()
                        ->maxLength(255)
                        ->default(fn() => 'Backup ' . now()->format('Y-m-d H:i')),

                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2),

                    Select::make('type')
                        ->label('Jenis Backup')
                        ->options([
                            'full' => 'Full Database',
                            'table' => 'Selected Tables',
                        ])
                        ->default('full')
                        ->required()
                        ->live(),

                    Select::make('format')
                        ->label('Format')
                        ->options([
                            'sql' => 'SQL Database',
                            'csv' => 'CSV Data Export',
                        ])
                        ->default('sql')
                        ->required(),

                    CheckboxList::make('table_names')
                        ->label('Pilih Tabel Database')
                        ->options(fn() => collect(
                            DB::connection()->getSchemaBuilder()->getTableListing()
                        )
                            ->reject(fn($table) => in_array($table, [
                                'migrations',
                            ], true))
                            ->mapWithKeys(fn($table) => [
                                $table => $table,
                            ])
                            ->all())
                        ->columns(3)
                        ->searchable()
                        ->bulkToggleable()
                        ->gridDirection('row')
                        ->visible(fn($get) => $get('type') === 'table')
                        ->required(fn($get) => $get('type') === 'table')
                        ->minItems(1)
                        ->helperText('Pilih satu atau beberapa tabel yang ingin di-backup.')
                ])
                ->action(function (array $data): void {
                    $tables = $data['type'] === 'table'
                        ? array_values($data['table_names'] ?? [])
                        : null;

                    $run = BackupRun::create([
                        'uuid' => (string) Str::uuid(),
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'type' => $data['type'],
                        'format' => $data['format'],
                        'table_names' => $tables,
                        'status' => 'pending',
                        'file_count' => 0,
                        'total_size' => 0,
                        'checksum_algorithm' => 'sha256',
                        'created_by' => auth()->id(),
                    ]);

                    RunDatabaseBackup::dispatch($run->id);

                    Notification::make()
                        ->title('Backup berhasil dijadwalkan')
                        ->body('Proses backup akan berjalan di background.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BackupRun::query()->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Backup')
                    ->searchable()
                    ->description(
                        fn(BackupRun $record) =>
                        $record->description
                    ),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'full' => 'Full Database',
                        'table' => 'Selected Tables',
                        default => $state,
                    })
                    ->color('info'),

                Tables\Columns\TextColumn::make('format')
                    ->label('Format')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'success' => 'success',
                        'processing' => 'info',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('file_count')
                    ->label('File')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_size')
                    ->label('Ukuran')
                    ->formatStateUsing(
                        fn($state) =>
                        self::formatBytes((int) $state)
                    ),

                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Durasi')
                    ->formatStateUsing(
                        fn($state) =>
                        $state !== null ? $state . ' detik' : '-'
                    ),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'full' => 'Full Database',
                        'table' => 'Selected Tables',
                    ]),
            ])
            ->actions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(
                        fn(BackupRun $record) =>
                        'Detail Backup: ' . $record->name
                    )
                    ->modalContent(
                        fn(BackupRun $record) =>
                        view('filament.pages.database.backup-detail', [
                            'record' => $record->load('files'),
                        ])
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->emptyStateHeading('Belum ada backup')
            ->emptyStateDescription(
                'Buat backup database pertama untuk memulai.'
            )
            ->poll('10s');
    }

    public function getStats(): array
    {
        return [
            'total' => BackupRun::count(),
            'success' => BackupRun::where('status', 'success')->count(),
            'processing' => BackupRun::where('status', 'processing')->count(),
            'failed' => BackupRun::where('status', 'failed')->count(),
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2)
            . ' ' . $units[$power];
    }
}
