<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRkaHeader extends CreateRecord
{
    protected static string $resource = RkaHeaderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('form-indicator-and-item', ['record' => $this->record]);
    }
}
