<?php

namespace App\Filament\Resources\RkaSettings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RkaSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('setting_name')
                    ->label('Nama Konfigurasi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => "Tempat: {$record->signing_place}"),

                TextColumn::make('director.name')
                    ->label('Akun Direktur')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Belum Dihubungkan')
                    ->badge()
                    ->color('info'),

                TextColumn::make('director_name')
                    ->label('Nama Pejabat Direktur')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => "NRP/NIDN: {$record->director_nrp}"),

                TextColumn::make('foundation_head_name')
                    ->label('Ketua Yayasan')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('pdf_header_config.header_type')
                    ->label('Tipe Kop PDF')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'full_image' => 'Full Image',
                        'manual_two_logos' => 'Manual (2 Logo)',
                        'manual_single_logo' => 'Manual (1 Logo)',
                        default => 'Belum Diatur',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'full_image' => 'success',
                        'manual_two_logos' => 'warning',
                        'manual_single_logo' => 'gray',
                        default => 'danger',
                    }),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}