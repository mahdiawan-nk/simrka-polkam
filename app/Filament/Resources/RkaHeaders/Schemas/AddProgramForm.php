<?php

namespace App\Filament\Resources\RkaHeaders\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use App\Models\RkaIndicator;
use App\Models\Account;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class AddProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('account_id')
                        ->label('Kelompok Belanja (Akun Level 1)')
                        ->options(Account::where('level', 1)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    TextInput::make('location')
                        ->label('Lokasi Kegiatan')
                        ->default('Politeknik Kampar')
                        ->required(),

                    TextInput::make('program_name')
                        ->label('Nama Program')
                        ->placeholder('Misal: Program Peningkatan Sarana dan Prasarana')
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('activity_name')
                        ->label('Nama Kegiatan')
                        ->placeholder('Misal: Belanja Modal')
                        ->required()
                        ->columnSpanFull(),
                ]),

                Repeater::make('indicator_matrix')
                    ->label('Matriks Indikator & Tolak Ukur Kinerja')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('indicator_category')
                                ->label('Kategori Indikator')
                                ->readOnly()
                                ->required(),

                            TextInput::make('performance_benchmark')
                                ->label('Tolak Ukur Kinerja')
                                ->placeholder('Misal: Tersedianya sarana dan prasarana')
                                ->required()
                                ->columnSpan(2),

                            Grid::make(2)->schema([
                                TextInput::make('target_value')
                                    ->label('Target')
                                    ->placeholder('1 / 100')
                                    ->required(),

                                TextInput::make('unit_target')
                                    ->label('Satuan')
                                    ->placeholder('Paket / Unit')
                                    ->required(),
                            ]),
                        ]),
                    ])
                    // Mengisi default items secara otomatis sesuai daftar kategori dari Model
                    ->default([
                        ['indicator_category' => RkaIndicator::CATEGORY_CAPAIAN_PROGRAM, 'performance_benchmark' => '', 'target_value' => '', 'unit_target' => ''],
                        ['indicator_category' => RkaIndicator::CATEGORY_MASUKAN, 'performance_benchmark' => '', 'target_value' => '', 'unit_target' => ''],
                        ['indicator_category' => RkaIndicator::CATEGORY_KELUARAN, 'performance_benchmark' => '', 'target_value' => '', 'unit_target' => ''],
                        ['indicator_category' => RkaIndicator::CATEGORY_HASIL, 'performance_benchmark' => '', 'target_value' => '', 'unit_target' => ''],
                        ['indicator_category' => RkaIndicator::CATEGORY_KELOMPOK_SASARAN, 'performance_benchmark' => '', 'target_value' => '', 'unit_target' => ''],
                    ])
                    ->addable(false)      // Pengguna tidak perlu menambah item manual
                    ->deletable(false)    // Mencegah penghapusan kategori standar
                    ->reorderable(false)  // Menjaga urutan indikator sesuai standar RKA
                    ->collapsible(),
            ]);
    }
}
