<?php

namespace App\Filament\Resources\RkaHeaders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Services\RkaApprovalService;
use App\Models\RkaHeader;

class RkaHeadersTable
{
    protected static string $resource = RkaHeaderResource::class;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')
                    ->label('Unit / Departemen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('schedule.fiscal_year')
                    ->label('Jadwal RKA')
                    ->searchable()
                    ->description(fn($record) => $record->schedule?->phase_name . '-' . $record->schedule->period_range)
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('pagu_budget')
                    ->label('Pagu Anggaran')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignEnd()
                    ->weight('semibold'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match (strtolower($state)) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'info',
                    })
                    ->formatStateUsing(fn(string $state): string => strtoupper($state))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_locked')
                    ->label('Kunci')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->alignCenter(),

                TextColumn::make('submitted_at')
                    ->label('Tgl Pengajuan')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('review_date')
                    ->label('Tgl Review')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                /*
|--------------------------------------------------------------------------
| 1. Action: Data Item (Khusus Pemohon saat DRAFT)
|--------------------------------------------------------------------------
*/
                Action::make('form_indicator_and_item')
                    ->label('Data Item')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->visible(
                        fn($record): bool =>
                        strtoupper($record->rka_status ?? $record->status ?? '') === RkaHeader::STATUS_DRAFT &&
                            auth()->user()->hasRole('pemohon')
                    )
                    ->url(fn($record): string => RkaHeaderResource::getUrl('form-indicator-and-item', ['record' => $record])),

                /*
|--------------------------------------------------------------------------
| 2. Action: Lihat RKA (Mode Read-Only untuk Pemohon setelah Submit)
|--------------------------------------------------------------------------
*/
                Action::make('view')
                    ->label('Lihat RKA')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(
                        fn($record): bool =>
                        in_array(strtoupper($record->rka_status ?? $record->status ?? ''), [
                            RkaHeader::STATUS_SUBMITTED,
                            RkaHeader::STATUS_IN_REVIEW,
                            RkaHeader::STATUS_IN_APPROVED,
                            RkaHeader::STATUS_REVISION_REQUIRED,
                            RkaHeader::STATUS_APPROVED,
                        ]) &&
                            auth()->user()->hasRole('pemohon')
                    )
                    ->url(function ($record): string {
                        $status = strtoupper($record->rka_status ?? $record->status ?? '');

                        if ($status === RkaHeader::STATUS_APPROVED) {
                            return RkaHeaderResource::getUrl('review', ['record' => $record]);
                        }

                        return RkaHeaderResource::getUrl('view', ['record' => $record]);
                    }),

                /*
|--------------------------------------------------------------------------
| 3. Action: Submit RKA (Pemohon -> Wadir Keuangan)
|--------------------------------------------------------------------------
*/
                Action::make('submit_rka')
                    ->label('Submit RKA')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(
                        fn($record): bool =>
                        strtoupper($record->rka_status ?? $record->status ?? '') === RkaHeader::STATUS_DRAFT &&
                            auth()->user()->hasRole('pemohon') &&
                            $record->pagu_budget > 0
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Submit RKA')
                    ->modalDescription('Apakah Anda yakin ingin mengajukan RKA ini ke Wadir Keuangan? Data yang telah diajukan akan dikunci.')
                    ->modalSubmitActionLabel('Ya, Submit Sekarang')
                    ->action(function ($record, RkaApprovalService $service): void {
                        $service->submitByPemohon(
                            $record,
                            auth()->user(),
                            'Pengajuan RKA dari unit kerja.'
                        );

                        Notification::make()
                            ->title('RKA Berhasil Diajukan')
                            ->body('Status RKA telah diperbarui menjadi SUBMITTED.')
                            ->success()
                            ->send();
                    }),

                /*
|--------------------------------------------------------------------------
| 4. Action: Review & Teruskan (Khusus Wadir Keuangan)
|--------------------------------------------------------------------------
*/
                Action::make('review_wadir')
                    ->label('Review & Teruskan')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(function ($record): bool {
                        $user = auth()->user();
                        $status = strtoupper($record->rka_status ?? $record->status ?? '');

                        // Aktif HANYA untuk Wadir Keuangan saat status SUBMITTED, IN_REVIEW, atau REVISION_REQUIRED
                        return $user->hasRole('wadir_keuangan') && in_array($status, [
                            RkaHeader::STATUS_SUBMITTED,
                            RkaHeader::STATUS_IN_REVIEW,
                            RkaHeader::STATUS_REVISION_REQUIRED,
                        ]);
                    })
                    ->url(function ($record, RkaApprovalService $service): string {
                        $status = strtoupper($record->rka_status ?? $record->status ?? '');

                        // Otomatis ubah status dari SUBMITTED ke IN_REVIEW ketika Wadir membuka halaman review
                        if ($status === RkaHeader::STATUS_SUBMITTED) {
                            $service->startReviewByWadir($record, auth()->user());
                        }

                        return RkaHeaderResource::getUrl('review', ['record' => $record]);
                    }),

                /*
|--------------------------------------------------------------------------
| 5. Action: Pengesahan / Revisi Direktur (Khusus Direktur saat IN_APPROVED)
|--------------------------------------------------------------------------
*/
                Action::make('review_direktur')
                    ->label('Pengesahan Direktur')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function ($record): bool {
                        $user = auth()->user();
                        $status = strtoupper($record->rka_status ?? $record->status ?? '');

                        // Aktif HANYA untuk Direktur saat status IN_APPROVED
                        return $user->hasRole('direktur') && $status === RkaHeader::STATUS_IN_APPROVED;
                    })
                    ->url(fn($record): string => RkaHeaderResource::getUrl('review', ['record' => $record]))

                // EditAction::make()
                // ->label('Edit')
                // ->icon('heroicon-o-pencil')
                // ->color('primary')
                // ->visible(fn($record): bool => strtolower($record->status) === 'draft'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(false);
    }
}
