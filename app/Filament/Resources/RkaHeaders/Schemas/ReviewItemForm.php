<?php

namespace App\Filament\Resources\RkaHeaders\Schemas;

use App\Models\RkaItem;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;

class ReviewItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('item_id'),
                Hidden::make('unit_price'),
                Hidden::make('proposed_volume'),
                Hidden::make('proposed_total'),

                Section::make('Informasi Item Asli Usulan')
                    ->description('Rincian item usulan sebelum dilakukan review/penyesuaian.')
                    ->compact()
                    ->schema([
                        Placeholder::make('item_info_description')
                            ->label('Uraian Item')
                            ->content(function (callable $get) {
                                $item = RkaItem::find($get('item_id'));
                                return $item ? $item->description : '-';
                            })
                            ->columnSpanFull(),

                        Placeholder::make('item_info_account')
                            ->label('Kode & Rekening')
                            ->content(function (callable $get) {
                                $item = RkaItem::find($get('item_id'));
                                return $item?->account ? "{$item->account->code} - {$item->account->name}" : '-';
                            }),

                        Placeholder::make('item_info_proposed')
                            ->label('Usulan Awal')
                            ->content(function (callable $get) {
                                $item = RkaItem::find($get('item_id'));
                                if (! $item) return '-';

                                $vol = number_format($item->proposed_volume, 0, ',', '.');
                                $sat = $item->unit_symbol ?? $item->unit ?? 'Satuan';
                                $harga = number_format($item->unit_price, 0, ',', '.');
                                $total = number_format($item->proposed_total, 0, ',', '.');

                                return "{$vol} {$sat} @ Rp {$harga} (Total: Rp {$total})";
                            }),
                    ])->columns(2),

                Grid::make(2)->schema([
                    Select::make('action_type')
                        ->label('Hasil Review / Status Aksi')
                        ->options([
                            'APPROVED'                 => 'Disetujui (Approved)',
                            'MOVED'                    => 'Dipindahkan / Pos Terpusat (Membuat Section B)',
                            'BUDGETED_ELSEWHERE'       => 'Sudah Dianggarkan di Unit Lain / Terpusat',
                            'REJECTED_WITH_ADJUSTMENT' => 'Disesuaikan Lalu Ditolak (Ada Adjustment Harga/Vol)',
                            'REJECTED'                 => 'Dibatalkan / Ditolak Langsung (Tanpa Penyesuaian)',
                            'PENDING'                  => 'Ditunda / Menunggu Keputusan',
                            'REMOVED'                  => 'Dipangkas / Dihapus',
                        ])
                        ->default('APPROVED')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Jika status ditolak, dipangkas, atau dianggarkan di tempat lain, set volume & total disetujui ke 0
                            if (in_array($state, ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'])) {
                                $set('approved_volume', 0);
                                $set('approved_total', 0);

                                // Jika ditolak murni TANPA penyesuaian, kembalikan input harga ke harga usulan asli
                                if ($state === 'REJECTED') {
                                    $set('approved_price', $get('unit_price'));
                                }
                            } elseif ($state === 'APPROVED') {
                                $proposedVol = $get('proposed_volume') ?? 0;
                                $proposedTot = $get('proposed_total') ?? 0;
                                $unitPrice   = $get('unit_price') ?? 0;

                                $set('approved_volume', $proposedVol);
                                $set('approved_price', $unitPrice);
                                $set('approved_total', $proposedTot);
                            }
                        })
                        ->columnSpanFull(),

                    TextInput::make('approved_volume')
                        ->label('Volume Disetujui')
                        ->numeric()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $price = (float) ($get('approved_price') ?? $get('unit_price') ?? 0);
                            if (is_numeric($state) && ! in_array($get('action_type'), ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'])) {
                                $set('approved_total', (float) $state * $price);
                            }
                        }),

                    TextInput::make('unit_symbol')
                        ->label('Satuan (Unit Symbol)')
                        ->required(),

                    TextInput::make('approved_price')
                        ->label('Harga Satuan / Adjustment (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->required()
                        ->disabled(fn(callable $get) => $get('action_type') === 'REJECTED') // Nonaktifkan jika Ditolak Langsung
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $volume = (float) ($get('approved_volume') ?? 0);
                            if (is_numeric($state) && ! in_array($get('action_type'), ['REJECTED', 'REJECTED_WITH_ADJUSTMENT', 'REMOVED', 'BUDGETED_ELSEWHERE'])) {
                                $set('approved_total', (float) $state * $volume);
                            }
                        }),

                    TextInput::make('approved_total')
                        ->label('Total Disetujui (Rp)')
                        ->prefix('Rp')
                        ->numeric()
                        ->required(),

                    Select::make('destination_unit_id')
                        ->label('Unit Tujuan / Pengalih')
                        ->options(\App\Models\Department::whereNotIn('type', ['direktorat', 'bidang'])->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn(callable $get) => $get('action_type') === 'MOVED')
                        ->visible(fn(callable $get) => in_array($get('action_type'), ['MOVED', 'PENDING'])),

                    Textarea::make('review_notes')
                        ->label('Catatan Reviewer / Alasan Adjustment')
                        ->placeholder('Misal: Penyesuaian harga acuan laboratorium lalu ditolak / Dianggarkan di unit lain')
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
