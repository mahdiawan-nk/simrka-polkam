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

class AddItemForm
{
    public static function configure(Schema $schema,mixed $activeTabGroup = null): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('account_id')
                        ->label('Kode Rekening (Sub Akun / Level 3/4/5)')
                        ->options(function () use ($activeTabGroup) {
                            $activeIndicator = RkaIndicator::find($activeTabGroup);
                            if (! $activeIndicator) {
                                return Account::pluck('name', 'id');
                            }

                            // Filter akun turunan dari kelompok akun indikator
                            return Account::where('code', 'LIKE', $activeIndicator->account->code . '%')
                                ->get()
                                ->pluck('full_account_label', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }

                            $account = Account::find($state);
                            if (! $account) {
                                return;
                            }

                            // 1. Isi unit_symbol dari akun yang dipilih (jika ada)
                            $set('unit_symbol', $account->unit_symbol ?? '');

                            // 2. Tentukan form_type berdasarkan Kode Akun Level 1 (Parent)
                            // Opsi A: Jika relasi parent diset di model Account (recursively find Level 1)
                            $parentLevel1 = $account;
                            while ($parentLevel1 && $parentLevel1->level > 1 && $parentLevel1->parent) {
                                $parentLevel1 = $parentLevel1->parent;
                            }

                            // Ambilllah kode dari Level 1 (misal '5.1', '5.2', '5.3', dst.)
                            $formType = $parentLevel1?->code ?? explode('.', $account->code)[0];

                            $set('form_type', (string) $formType);
                        })
                        ->columnSpanFull(),


                    TextInput::make('description')
                        ->label('Uraian Item Belanja')
                        ->placeholder('Misal: Peningkatan jaringan – Access Point WiFi 6')
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('proposed_volume')
                        ->label('Volume Usulan')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->live(),

                    TextInput::make('unit_symbol')
                        ->label('Satuan')
                        ->placeholder('Unit / Pcs / Paket / Bulan')
                        ->required(),

                    TextInput::make('unit_price')
                        ->label('Harga Satuan (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->live(),

                    TextInput::make('form_type')
                        ->label('Formulir')
                        ->default('5.3')
                        ->required(),
                ]),
            ]);
    }
}
