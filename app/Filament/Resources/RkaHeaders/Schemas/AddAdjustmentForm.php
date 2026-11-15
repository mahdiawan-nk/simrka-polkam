<?php

namespace App\Filament\Resources\RkaHeaders\Schemas;

use App\Models\Account;
use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AddAdjustmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('section_type')
                        ->label('Tipe Penyesuaian')
                        ->options([
                            'A' => 'A - Usulan Dipindah / Tidak Dianggarkan di Unit Ini',
                            'B' => 'B - Pos Dikelola Unit Ini untuk Unit Lain (Shared Cost / Usulan Reviewer)',
                        ])
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state === 'A') {
                                $set('section_name', 'Penyesuaian Unit Asal');
                            } else {
                                $set('section_name', 'Penyesuaian Unit Pengelola');
                            }
                        }),

                    Select::make('action_type')
                        ->label('Aksi Reviewer')
                        ->options([
                            'MOVED' => 'MOVED (Dipindahkan)',
                            'REJECTED' => 'REJECTED (Tidak Dianggarkan)',
                            'SHARED_COST' => 'SHARED_COST (Dialokasikan ke Unit Lain)',
                            'ADDED_BY_REVIEWER' => 'ADDED_BY_REVIEWER (Tambahan Saat Review)',
                            'REMOVED' => 'REMOVED (Dihapus)',
                        ])
                        ->required(),

                    Select::make('account_id')
                        ->label('Kode Rekening')
                        ->options(Account::all()->pluck('full_account_label', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('description')
                        ->label('Uraian Penyesuaian')
                        ->placeholder('Misal: Pembelian Akun Turnitin / Lisensi')
                        ->required(),

                    Select::make('destination_unit_id')
                        ->label('Unit Tujuan / Pengalih')
                        ->options(Department::pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn (callable $get) => $get('section_type') === 'A'),

                    Select::make('target_unit_id')
                        ->label('Unit Penerima Manfaat')
                        ->options(Department::pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn (callable $get) => $get('section_type') === 'B'),

                    TextInput::make('proposed_volume')
                        ->label('Volume')
                        ->numeric()
                        ->minValue(0.01)
                        ->default(1)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                            $volume = is_numeric($state) ? (float) $state : 0;
                            $price = is_numeric($get('unit_price')) ? (float) $get('unit_price') : 0;
                            if ($price > 0) {
                                $set('proposed_amount', $volume * $price);
                            }
                        }),

                    TextInput::make('unit_symbol')
                        ->label('Satuan')
                        ->placeholder('Unit / Paket / Bulan / Keg')
                        ->maxLength(50)
                        ->required(),

                    TextInput::make('unit_price')
                        ->label('Harga Satuan (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                            $price = is_numeric($state) ? (float) $state : 0;
                            $volume = is_numeric($get('proposed_volume')) ? (float) $get('proposed_volume') : 0;
                            $set('proposed_amount', $volume * $price);
                        }),

                    TextInput::make('proposed_amount')
                        ->label('Nominal Usulan (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make('approved_amount')
                        ->label('Nominal Disetujui (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Textarea::make('notes')
                        ->label('Keterangan / Alasan Penyesuaian')
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
