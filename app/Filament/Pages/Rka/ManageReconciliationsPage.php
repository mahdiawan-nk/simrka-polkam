<?php

declare(strict_types=1);

namespace App\Filament\Pages\Rka;

use App\Models\Department;
use App\Models\RkaAdjustment;
use App\Models\RkaAdjustmentMatch;
use App\Models\RkaSchedule;
use App\Services\Rka\Reconciliation\RkaReconciliationService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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

class ManageReconciliationsPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen RKA';

    protected static ?string $navigationLabel = 'Rekonsiliasi Antarunit';

    protected static ?string $title = 'Monitoring Rekonsiliasi Antarunit';

    protected static ?int $navigationSort = 36;

    protected string $view = 'filament.pages.rka.manage-reconciliations-page';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rerunReconciliation')
                ->label('Rekonsiliasi Ulang')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('rerun', RkaAdjustmentMatch::class) ?? false)
                ->form([
                    Select::make('schedule_id')
                        ->label('Periode / Jadwal RKA')
                        ->options(fn (): array => RkaSchedule::query()->orderByDesc('id')->pluck('phase_name', 'id')->all())
                        ->required(),
                    Textarea::make('reason')
                        ->label('Alasan Rekonsiliasi Ulang')
                        ->required()
                        ->minLength(5),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    if (! $user) {
                        return;
                    }

                    try {
                        $stats = app(RkaReconciliationService::class)->rerunForSchedule(
                            scheduleId: (int) $data['schedule_id'],
                            actor: $user,
                            reason: (string) $data['reason'],
                        );

                        Notification::make()
                            ->title('Rekonsiliasi Ulang Selesai')
                            ->body("Matched: {$stats['matched']}, Waiting: {$stats['waiting']}, Conflict: {$stats['conflict']}, Review: {$stats['review_required']}.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Gagal Rekonsiliasi Ulang')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getReconciliationsQuery())
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('schedule.phase_name')
                    ->label('Periode / Jadwal')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('sourceDepartment.name')
                    ->label('Unit Sumber')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('sourceAdjustment.description')
                    ->label('Item Sumber')
                    ->wrap()
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('targetDepartment.name')
                    ->label('Unit Lawan / Penerima')
                    ->placeholder('Belum Terpetakan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('counterpartAdjustment.description')
                    ->label('Item Lawan')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60),

                TextColumn::make('sourceAdjustment.proposed_amount')
                    ->label('Nilai Sumber')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('matched_amount')
                    ->label('Nilai Cocok')
                    ->money('IDR')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('reconciliation_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RkaAdjustmentMatch::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        RkaAdjustmentMatch::STATUS_MATCHED => 'success',
                        RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART => 'info',
                        RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED => 'warning',
                        RkaAdjustmentMatch::STATUS_CONFLICT, RkaAdjustmentMatch::STATUS_UNMATCHED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('matching_rule')
                    ->label('Aturan')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('matched_at')
                    ->label('Waktu Cocok')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('schedule_id')
                    ->label('Periode / Jadwal')
                    ->options(fn (): array => RkaSchedule::query()->orderByDesc('id')->pluck('phase_name', 'id')->all()),
                SelectFilter::make('source_department_id')
                    ->label('Unit Sumber')
                    ->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('target_department_id')
                    ->label('Unit Penerima / Lawan')
                    ->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('reconciliation_status')
                    ->label('Status Rekonsiliasi')
                    ->options(RkaAdjustmentMatch::statusLabels()),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Detail & Histori')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (RkaAdjustmentMatch $record): string => "Detail Rekonsiliasi #{$record->id}")
                    ->modalContent(fn (RkaAdjustmentMatch $record) => view('filament.pages.rka.reconciliation-detail-modal', [
                        'match' => $record->loadMissing([
                            'schedule',
                            'sourceDepartment',
                            'targetDepartment',
                            'sourceAdjustment.account',
                            'counterpartAdjustment.account',
                            'sourceItem',
                            'counterpartItem',
                            'reviewer',
                            'histories.actor',
                        ]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Action::make('adjust')
                    ->label('Koreksi')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->visible(fn (RkaAdjustmentMatch $record): bool => auth()->user()?->can('adjust', $record) ?? false)
                    ->form(fn (RkaAdjustmentMatch $record): array => [
                        Select::make('reconciliation_status')
                            ->label('Status Rekonsiliasi')
                            ->options([
                                RkaAdjustmentMatch::STATUS_MATCHED => 'Cocok (Sah)',
                                RkaAdjustmentMatch::STATUS_WAITING_COUNTERPART => 'Menunggu Unit Lawan',
                                RkaAdjustmentMatch::STATUS_REVIEW_REQUIRED => 'Perlu Tinjauan Finance',
                                RkaAdjustmentMatch::STATUS_CONFLICT => 'Konflik Data/Nilai',
                                RkaAdjustmentMatch::STATUS_UNMATCHED => 'Tidak Ada Pasangan',
                            ])
                            ->default($record->reconciliation_status)
                            ->required(),
                        Select::make('target_department_id')
                            ->label('Unit Lawan / Penerima')
                            ->options(Department::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default($record->target_department_id)
                            ->searchable(),
                        Select::make('counterpart_adjustment_id')
                            ->label('Pasangan Adjustment Lawan')
                            ->options(fn (): array => RkaAdjustment::query()
                                ->with('rkaHeader.department')
                                ->whereHas('rkaHeader', fn ($q) => $q->where('schedule_id', $record->schedule_id))
                                ->where('id', '!=', $record->source_adjustment_id)
                                ->get()
                                ->mapWithKeys(fn (RkaAdjustment $adj): array => [
                                    $adj->id => "[{$adj->rkaHeader?->department?->code}] {$adj->description} (Rp ".number_format((float) $adj->proposed_amount, 0, ',', '.').')',
                                ])
                                ->all())
                            ->default($record->counterpart_adjustment_id)
                            ->searchable(),
                        TextInput::make('matched_amount')
                            ->label('Nominal Rekonsiliasi (Rp)')
                            ->numeric()
                            ->default($record->matched_amount ?? $record->sourceAdjustment?->proposed_amount),
                        Textarea::make('reason')
                            ->label('Alasan Koreksi Finance')
                            ->required()
                            ->minLength(5),
                    ])
                    ->action(function (RkaAdjustmentMatch $record, array $data): void {
                        $user = auth()->user();
                        if (! $user) {
                            return;
                        }

                        try {
                            app(RkaReconciliationService::class)->financeAdjust(
                                match: $record,
                                actor: $user,
                                newStatus: (string) $data['reconciliation_status'],
                                counterpartAdjustmentId: isset($data['counterpart_adjustment_id']) && $data['counterpart_adjustment_id'] !== '' ? (int) $data['counterpart_adjustment_id'] : null,
                                targetDepartmentId: isset($data['target_department_id']) && $data['target_department_id'] !== '' ? (int) $data['target_department_id'] : null,
                                matchedAmount: isset($data['matched_amount']) && $data['matched_amount'] !== '' ? (float) $data['matched_amount'] : null,
                                reason: (string) $data['reason'],
                            );

                            Notification::make()
                                ->title('Koreksi Rekonsiliasi Tersimpan')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Gagal Menyimpan Koreksi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (RkaAdjustmentMatch $record): bool => $record->reconciliation_status !== RkaAdjustmentMatch::STATUS_CANCELLED
                        && (auth()->user()?->can('cancel', $record) ?? false))
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan Relasi')
                            ->required()
                            ->minLength(5),
                    ])
                    ->action(function (RkaAdjustmentMatch $record, array $data): void {
                        $user = auth()->user();
                        if (! $user) {
                            return;
                        }

                        try {
                            app(RkaReconciliationService::class)->financeCancel(
                                match: $record,
                                actor: $user,
                                reason: (string) $data['reason'],
                            );

                            Notification::make()
                                ->title('Relasi Rekonsiliasi Dibatalkan')
                                ->warning()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Gagal Membatalkan Relasi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    private function getReconciliationsQuery(): Builder
    {
        return RkaAdjustmentMatch::query()
            ->with([
                'schedule',
                'sourceDepartment',
                'targetDepartment',
                'sourceAdjustment.account',
                'counterpartAdjustment.account',
                'sourceItem',
                'counterpartItem',
                'reviewer',
            ]);
    }
}
