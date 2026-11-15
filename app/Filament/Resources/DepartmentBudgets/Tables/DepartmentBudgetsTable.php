<?php

namespace App\Filament\Resources\DepartmentBudgets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use App\Models\DepartmentBudget;

class DepartmentBudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')
                    ->label('Departemen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-office-2'),

                TextColumn::make('account.name')
                    ->label('Akun Anggaran')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => $record->account?->code ?? '-'),

                TextColumn::make('fiscal_year')
                    ->label('Tahun Anggaran')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->alignment('center'),

                TextColumn::make('allocated_amount')
                    ->label('Alokasi Anggaran')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignment('right')
                    ->summarize(
                        Sum::make()
                            ->label('Total Anggaran')
                            ->money('IDR', locale: 'id')
                    ),

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
                SelectFilter::make('department_id')
                    ->label('Departemen')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('fiscal_year')
                    ->label('Tahun Anggaran')
                    ->options(fn() => DepartmentBudget::query()
                        ->select('fiscal_year')
                        ->distinct()
                        ->orderByDesc('fiscal_year')
                        ->pluck('fiscal_year', 'fiscal_year')
                        ->toArray())
                    ->searchable()
                    ->placeholder('Semua Tahun'),
            ], layout: FiltersLayout::BeforeContent)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
