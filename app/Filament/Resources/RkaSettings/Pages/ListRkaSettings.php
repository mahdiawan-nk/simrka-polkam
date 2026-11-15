<?php

namespace App\Filament\Resources\RkaSettings\Pages;

use App\Filament\Resources\RkaSettings\RkaSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRkaSettings extends ListRecords
{
    protected static string $resource = RkaSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
