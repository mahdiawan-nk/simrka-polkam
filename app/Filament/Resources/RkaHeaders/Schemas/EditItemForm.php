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

class EditItemForm
{
    public static function configure(Schema $schema, mixed $activeTabGroup = null): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('account_id')
                        ->label('Kode Rekening')
                        ->options(Account::all()->pluck('full_account_label', 'id'))
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('description')
                        ->label('Uraian Item Belanja')
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('proposed_volume')
                        ->label('Volume Usulan')
                        ->numeric()
                        ->required(),

                    TextInput::make('unit_symbol')
                        ->label('Satuan')
                        ->required(),

                    TextInput::make('unit_price')
                        ->label('Harga Satuan (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->required(),
                ]),
            ]);
    }
}
