<?php

namespace App\Filament\Resources\RkaSettings\Pages;

use App\Filament\Resources\RkaSettings\RkaSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRkaSetting extends EditRecord
{
    protected static string $resource = RkaSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
