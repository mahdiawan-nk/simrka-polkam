<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Account;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Rekening')
                            ->placeholder('Contoh: 5.2.1.03')
                            ->required()
                            ->maxLength(30)
                            ->unique(Account::class, 'code', ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                // Otomatis isi code_clean (tanpa titik) saat kode diinput
                                if ($state) {
                                    $set('code_clean', str_replace('.', '', $state));
                                }
                            }),

                        TextInput::make('code_clean')
                            ->label('Kode Clean (Tanpa Titik)')
                            ->placeholder('Otomatis terisi, contoh: 52103')
                            ->maxLength(30)
                            ->readOnly()
                            ->dehydrated(),
                    ]),

                TextInput::make('name')
                    ->label('Nama / Uraian Akun')
                    ->placeholder('Contoh: Belanja Rumah Tangga')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Grid::make(3)
                    ->schema([
                        Select::make('level')
                            ->label('Tingkat Hirarki')
                            ->options([
                                1 => 'Kelompok (Level 1)',
                                2 => 'Jenis (Level 2)',
                                3 => 'Rincian (Level 3)',
                            ])
                            ->default(3)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $labels = [
                                    1 => 'Kelompok',
                                    2 => 'Jenis',
                                    3 => 'Rincian',
                                ];
                                $set('level_label', $labels[$state] ?? 'Rincian');
                                // Reset parent jika diubah ke Level 1
                                if ($state == 1) {
                                    $set('parent_id', null);
                                    $set('parent_code', null);
                                }
                            }),

                        TextInput::make('level_label')
                            ->label('Label Tingkat')
                            ->readOnly()
                            ->dehydrated(),

                        Select::make('parent_id')
                            ->label('Induk (Parent Account)')
                            ->options(function (callable $get) {
                                $currentLevel = $get('level');
                                // Level 1 tidak memiliki parent
                                if ($currentLevel == 1) {
                                    return [];
                                }
                                // Induk untuk Level 2 adalah Level 1, Induk untuk Level 3 adalah Level 2
                                $targetParentLevel = $currentLevel == 3 ? 2 : 1;

                                return Account::where('level', $targetParentLevel)
                                    ->pluck('name', 'id')
                                    ->mapWithKeys(function ($name, $id) {
                                        $account = Account::find($id);
                                        return [$id => "{$account->code} - {$name}"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn(callable $get) => $get('level') == 1)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $parent = Account::find($state);
                                    $set('parent_code', $parent?->code);
                                } else {
                                    $set('parent_code', null);
                                }
                            }),
                    ]),

                Textarea::make('description')
                    ->label('Keterangan / Contoh Isi (Petunjuk RKA)')
                    ->placeholder('Sesuai contoh di Excel (contoh: Bahan kebersihan, tisu, gas, dll.)')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('managed_by_note')
                    ->label('Catatan Pengelolaan / Pos Terpusat')
                    ->placeholder('Contoh: Dikelola UPT ICT / Dikelola Bagian Keuangan')
                    ->rows(2)
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        Toggle::make('is_centralized')
                            ->label('Pos Belanja Terpusat')
                            ->helperText('Aktifkan jika pengadaan dikelola oleh unit terpusat (seperti UPT ICT/Keuangan)')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Status Akun Aktif')
                            ->helperText('Nonaktifkan jika akun rekening ini tidak boleh dipilih pada pengajuan RKA')
                            ->default(true),
                    ]),
            ])
            ->columns(1);
    }
}
