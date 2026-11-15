<?php

namespace App\Filament\Resources\Accounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode Rekening')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('code', 'like', "%{$search}%")
                            ->orWhere('code_clean', 'like', "%{$search}%");
                    })
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Kode rekening berhasil disalin'),

                TextColumn::make('name')
                    ->label('Nama Uraian Akun')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn ($record) => $record->managed_by_note ?? $record->description),

                TextColumn::make('level_label')
                    ->label('Tingkat')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Kelompok' => 'danger',
                        'Jenis' => 'warning',
                        'Rincian' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label('Induk (Parent)')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                IconColumn::make('is_centralized')
                    ->label('Terpusat')
                    ->boolean()
                    ->trueIcon('heroicon-o-building-library')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignment('center')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignment('center')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label('Tingkat Hirarki')
                    ->options([
                        1 => 'Kelompok (Level 1)',
                        2 => 'Jenis (Level 2)',
                        3 => 'Rincian (Level 3)',
                    ]),

                TernaryFilter::make('is_centralized')
                    ->label('Pos Terpusat')
                    ->placeholder('Semua Akun')
                    ->trueLabel('Hanya Akun Terpusat')
                    ->falseLabel('Bukan Terpusat'),

                TernaryFilter::make('is_active')
                    ->label('Status Akun')
                    ->placeholder('Semua Status')
                    ->trueLabel('Hanya Aktif')
                    ->falseLabel('Non-Aktif'),
            ], layout: FiltersLayout::BeforeContent)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }
}