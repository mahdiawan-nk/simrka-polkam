<?php

namespace App\Filament\Resources\RkaSchedules\Tables;

use App\Models\RkaGenerateConfig;
use App\Models\RkaSchedule;
use App\Services\RkaGenerateService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RkaSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fiscal_year')
                    ->label('Tahun Anggaran')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('phase_name')
                    ->label('Tahapan / Gelombang')
                    ->searchable()
                    ->sortable()
                    ->description(fn(RkaSchedule $record) => $record->period_range),

                // Indikator Status Portal Berdasarkan Waktu Real-time
                TextColumn::make('status_label')
                    ->label('Status Portal')
                    ->badge()
                    ->color(fn(RkaSchedule $record): string => match ($record->status_label) {
                        'Terbuka' => 'success',
                        'Terbuka (Susulan)' => 'warning',
                        'Belum Dimulai' => 'info',
                        'Ditutup' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('start_at')
                    ->label('Buka Portal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('end_at')
                    ->label('Tutup Portal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('allow_late_submission')
                    ->label('Susulan')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sistem')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('fiscal_year')
                    ->label('Tahun Anggaran')
                    ->options(fn() => RkaSchedule::query()->pluck('fiscal_year', 'fiscal_year')->unique()->toArray()),

                TernaryFilter::make('is_active')
                    ->label('Status Keaktifan')
                    ->boolean()
                    ->trueLabel('Hanya Jadwal Aktif')
                    ->falseLabel('Hanya Jadwal Nonaktif'),

                TernaryFilter::make('allow_late_submission')
                    ->label('Izin Susulan')
                    ->boolean()
                    ->trueLabel('Mengizinkan Susulan')
                    ->falseLabel('Tanpa Susulan'),
            ])
            ->recordActions([
                // Action Auto-Generate RKA berdasarkan Jadwal yang dipilih
                Action::make('generateRka')
                    ->label('Generate RKA')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate RKA untuk Jadwal Ini?')
                    ->modalDescription('Proses ini akan membuat RKA Header & Indikator untuk seluruh departemen berdasarkan jadwal ini.')
                    ->modalSubmitActionLabel('Ya, Generate Sekarang')
                    ->action(function (RkaSchedule $record, RkaGenerateService $service) {
                        try {
                            // Oper $record (RkaSchedule) secara langsung ke service
                            $result = $service->generate(schedule: $record);

                            Notification::make()
                                ->title('Berhasil Generate RKA')
                                ->body("Selesai: {$result['headers']} Header RKA & {$result['indicators']} Indikator berhasil dibuat.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal Generate')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_at', 'desc');
    }
}
