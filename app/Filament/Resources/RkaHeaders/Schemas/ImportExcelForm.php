<?php

namespace App\Filament\Resources\RkaHeaders\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;

class ImportExcelForm
{
    public static function configure(Schema $schema, mixed $activeTabGroup = null): Schema
    {
        return $schema
            ->components([
                Radio::make('import_scope')
                    ->label('Cakupan Import Data')
                    ->options([
                        'all' => 'Semua Indikator (Otomatis sesuai Kode Rekening di Excel)',
                        'active_tab' => 'Hanya Indikator Aktif Saat Ini',
                    ])
                    ->default('all')
                    ->required(),

                FileUpload::make('attachment')
                    ->label('File Excel RKA (.xlsx / .xls)')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel'
                    ])
                    ->required()
                    ->storeFiles(false),
            ]);
    }
}
