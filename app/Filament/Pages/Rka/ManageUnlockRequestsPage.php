<?php

namespace App\Filament\Pages\Rka;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\RkaUnlockRequest;
use App\Services\ApprovedRkaImport\RkaUnlockRequestService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use UnitEnum;

class ManageUnlockRequestsPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen RKA';

    protected static ?string $navigationLabel = 'Permintaan Buka Kunci RKA';

    protected static ?string $title = 'Permintaan Pembukaan Kunci RKA Disetujui';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.rka.manage-unlock-requests-page';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getUnlockRequestsQuery())
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('rkaHeader.id')
                    ->label('RKA')
                    ->formatStateUsing(fn ($state, RkaUnlockRequest $record): string => "RKA #{$state} ({$record->rkaHeader?->status})")
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label('Unit / Departemen')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->searchable(),

                TextColumn::make('reason')
                    ->label('Alasan Pembukaan Kunci')
                    ->wrap()
                    ->limit(80),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RkaUnlockRequest::STATUS_PENDING => 'warning',
                        RkaUnlockRequest::STATUS_APPROVED => 'success',
                        RkaUnlockRequest::STATUS_REJECTED => 'danger',
                        RkaUnlockRequest::STATUS_USED => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('reimport_expires_at')
                    ->label('Batas Import Ulang')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),

                TextColumn::make('reviewer.name')
                    ->label('Diproses Oleh')
                    ->placeholder('-'),

                TextColumn::make('decision_notes')
                    ->label('Catatan Keuangan')
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        RkaUnlockRequest::STATUS_PENDING => 'Menunggu Persetujuan',
                        RkaUnlockRequest::STATUS_APPROVED => 'Disetujui (Siap Reimport)',
                        RkaUnlockRequest::STATUS_REJECTED => 'Ditolak',
                        RkaUnlockRequest::STATUS_USED => 'Sudah Digunakan',
                        RkaUnlockRequest::STATUS_EXPIRED => 'Kedaluwarsa',
                    ]),
            ])
            ->recordActions([
                Action::make('approve_unlock')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RkaUnlockRequest $record): bool => $record->status === RkaUnlockRequest::STATUS_PENDING
                        && (auth()->user()?->can('approve', $record) ?? false))
                    ->form([
                        Textarea::make('decision_notes')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->rows(3),
                        TextInput::make('valid_hours')
                            ->label('Masa Berlaku Hak Import Ulang (Jam)')
                            ->numeric()
                            ->default(24)
                            ->minValue(1)
                            ->maxValue(168)
                            ->required(),
                    ])
                    ->action(function (RkaUnlockRequest $record, array $data): void {
                        try {
                            app(RkaUnlockRequestService::class)->approveRequest(
                                request: $record,
                                financeUser: auth()->user(),
                                notes: $data['decision_notes'] ?? null,
                                validHours: (int) ($data['valid_hours'] ?? 24),
                            );

                            Notification::make()
                                ->title('Permintaan Disetujui')
                                ->body('Hak import ulang satu kali telah diberikan kepada pemohon. Status RKA tetap APPROVED.')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Gagal Menyetujui')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reject_unlock')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (RkaUnlockRequest $record): bool => $record->status === RkaUnlockRequest::STATUS_PENDING
                        && (auth()->user()?->can('reject', $record) ?? false))
                    ->form([
                        Textarea::make('decision_notes')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (RkaUnlockRequest $record, array $data): void {
                        try {
                            app(RkaUnlockRequestService::class)->rejectRequest(
                                request: $record,
                                financeUser: auth()->user(),
                                notes: (string) ($data['decision_notes'] ?? ''),
                            );

                            Notification::make()
                                ->title('Permintaan Ditolak')
                                ->body('RKA tetap berstatus APPROVED dan terkunci.')
                                ->warning()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Gagal Menolak')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('go_to_reimport')
                    ->label('Jalankan Import Ulang')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(fn (RkaUnlockRequest $record): bool => $record->isReimportActive()
                        && (auth()->user()?->can('importApproved', RkaHeaderResource::getModel()) ?? false))
                    ->url(fn (RkaUnlockRequest $record): string => RkaHeaderResource::getUrl('import-approved', [
                        'reimport' => $record->rka_header_id,
                    ])),
            ]);
    }

    protected function getUnlockRequestsQuery(): Builder
    {
        $user = auth()->user();
        $query = RkaUnlockRequest::query()->with(['rkaHeader', 'department', 'requester', 'reviewer']);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasAnyRole(['super_admin', 'keuangan', 'wadir_keuangan'])) {
            return $query;
        }

        $departmentIds = $user->departments()->pluck('departments.id');

        return $query->whereIn('department_id', $departmentIds);
    }
}
