<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRkaHeader extends EditRecord
{
    protected static string $resource = RkaHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
