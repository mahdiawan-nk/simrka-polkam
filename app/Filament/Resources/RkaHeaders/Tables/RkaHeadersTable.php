<?php

namespace App\Filament\Resources\RkaHeaders\Tables;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\RkaHeader;
use App\Models\RkaUnlockRequest;
use App\Services\ApprovedRkaImport\RkaUnlockRequestService;
use App\Services\RkaApprovalService;
use App\Services\RkaApprovedTemplateService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

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
                    ->description(fn ($record) => $record->schedule?->phase_name.'-'.$record->schedule->period_range)
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
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'in_review', 'pending', 'revision', 'revision_required', 'trimmed' => 'warning',
                        'approved', 'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
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
                        fn ($record): bool => strtoupper($record->rka_status ?? $record->status ?? '') === RkaHeader::STATUS_DRAFT &&
                            auth()->user()->hasRole('pemohon')
                    )
                    ->url(fn ($record): string => RkaHeaderResource::getUrl('form-indicator-and-item', ['record' => $record])),

                /*
|--------------------------------------------------------------------------
| 2. Action: Lihat RKA (Mode Read-Only untuk Pemohon setelah Submit)
|--------------------------------------------------------------------------
*/
                Action::make('view')
                    ->label('Lihat RKA')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    // ->visible(
                    //     fn ($record): bool => in_array(strtoupper($record->rka_status ?? $record->status ?? ''), [
                    //         RkaHeader::STATUS_SUBMITTED,
                    //         RkaHeader::STATUS_IN_REVIEW,
                    //         RkaHeader::STATUS_IN_APPROVED,
                    //         RkaHeader::STATUS_REVISION_REQUIRED,
                    //         RkaHeader::STATUS_APPROVED,
                    //     ]) &&
                    //         auth()->user()->hasRole('pemohon')
                    // )
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
                        fn ($record): bool => strtoupper($record->rka_status ?? $record->status ?? '') === RkaHeader::STATUS_DRAFT &&
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
                    ->url(fn ($record): string => RkaHeaderResource::getUrl('review', ['record' => $record])),

                /*
                |--------------------------------------------------------------------------
                | 6. Action: Export Approved RKA Excel (Khusus Status APPROVED)
                |--------------------------------------------------------------------------
                */
                Action::make('export_approved_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(function ($record): bool {
                        $user = auth()->user();
                        $status = strtoupper((string) ($record->rka_status ?? $record->status ?? ''));

                        return $status === RkaHeader::STATUS_APPROVED
                            && $user !== null
                            && $user->can('view', $record);
                    })
                    ->action(function ($record, RkaApprovedTemplateService $exportService) {
                        try {
                            return $exportService->export($record, auth()->user());
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Export Excel Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return null;
                        }
                    }),

                Action::make('request_unlock')
                    ->label('Ajukan Buka Kunci')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->visible(function ($record): bool {
                        $user = auth()->user();
                        if ($user === null || ! $user->can('requestUnlock', $record)) {
                            return false;
                        }

                        return ! $record->unlockRequests()
                            ->whereIn('status', [RkaUnlockRequest::STATUS_PENDING, RkaUnlockRequest::STATUS_APPROVED])
                            ->where(function ($query): void {
                                $query->where('status', RkaUnlockRequest::STATUS_PENDING)
                                    ->orWhere(function ($q): void {
                                        $q->where('status', RkaUnlockRequest::STATUS_APPROVED)
                                            ->whereNull('reimport_used_at')
                                            ->where(function ($exp): void {
                                                $exp->whereNull('reimport_expires_at')
                                                    ->orWhere('reimport_expires_at', '>', now());
                                            });
                                    });
                            })
                            ->exists();
                    })
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Permintaan Pembukaan Kunci')
                            ->required()
                            ->rows(3),
                        Textarea::make('error_description')
                            ->label('Deskripsi Kesalahan Data / Item (Opsional)')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data, RkaUnlockRequestService $unlockService): void {
                        try {
                            $unlockService->submitRequest(
                                header: $record,
                                user: auth()->user(),
                                reason: (string) ($data['reason'] ?? ''),
                                errorDescription: $data['error_description'] ?? null,
                            );

                            Notification::make()
                                ->title('Permintaan Pembukaan Kunci Terkirim')
                                ->body('Menunggu persetujuan tim keuangan sebelum Anda dapat menjalankan import ulang.')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Gagal Mengajukan Buka Kunci')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reimport_approved_rka')
                    ->label('Import Ulang Approved')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(function ($record): bool {
                        $user = auth()->user();
                        if ($user === null || ! $user->can('importApproved', RkaHeader::class)) {
                            return false;
                        }

                        if (! $user->hasRole('super_admin') && ! $user->departments()->where('departments.id', $record->department_id)->exists()) {
                            return false;
                        }

                        $active = $record->unlockRequests()
                            ->where('status', RkaUnlockRequest::STATUS_APPROVED)
                            ->whereNull('reimport_used_at')
                            ->latest('id')
                            ->first();

                        return $active !== null && $active->isReimportActive();
                    })
                    ->url(fn ($record): string => RkaHeaderResource::getUrl('import-approved', [
                        'reimport' => $record->id,
                    ])),

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
