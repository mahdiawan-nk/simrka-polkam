<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Utama Departemen')
                    ->description('Masukan detail identitas dan hirarki departemen.')
                    ->icon('heroicon-o-building-office-2')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Departemen')
                                ->placeholder('Contoh: Sumber Daya Manusia')
                                ->required()
                                ->maxLength(255)
                                ->autofocus()
                                ->columnSpan(1),

                            Select::make('parent_id')
                                ->label('Induk Departemen')
                                ->relationship(
                                    name: 'parent', 
                                    titleAttribute: 'name',
                                    // Mencegah departemen memilih dirinya sendiri sebagai parent saat edit
                                    modifyQueryUsing: fn (Builder $query, $record) => 
                                        $record ? $query->where('id', '!=', $record->id) : $query
                                )
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->placeholder('Pilih Induk (Opsional)')
                                ->helperText('Kosongkan jika departemen ini merupakan struktur tertinggi.')
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Kode & Klasifikasi')
                    ->description('Pengaturan kode unik dan tingkatan struktur.')
                    ->icon('heroicon-o-identification')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code')
                                ->label('Kode Departemen')
                                ->placeholder('Contoh: HRD-001')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->alphaDash()
                                ->maxLength(50)
                                ->extraInputAttributes(['style' => 'text-transform: uppercase;']),

                            TextInput::make('type')
                                ->label('Tipe Departemen')
                                ->placeholder('Contoh: Divisi / Sub-Divisi / Unit')
                                ->required()
                                ->maxLength(50),

                            TextInput::make('level_code')
                                ->label('Kode Level')
                                ->default('PK.3')
                                ->required()
                                ->maxLength(20)
                                ->helperText('Format standar hirarki (Default: PK.3).'),
                        ]),
                    ]),
            ])
            ->columns(1);
    }
}