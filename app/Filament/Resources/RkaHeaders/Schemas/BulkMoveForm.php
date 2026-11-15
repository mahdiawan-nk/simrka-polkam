<?php

namespace App\Filament\Resources\RkaHeaders\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BulkMoveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('destination_unit_id')
                    ->label('Unit Tujuan / Pengalih')
                    ->options(Department::whereNotIn('type', ['direktorat', 'bidang'])->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Textarea::make('review_notes')
                    ->label('Catatan Reviewer / Alasan Pemindahan')
                    ->placeholder('Misal: Dialihkan ke Pos Terpusat Unit Pengelola ICT')
                    ->default('Dipindahkan secara massal ke Pos Terpusat'),
            ]);
    }
}
