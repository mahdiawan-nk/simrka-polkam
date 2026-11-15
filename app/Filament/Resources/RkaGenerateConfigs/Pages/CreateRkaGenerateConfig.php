<?php

namespace App\Filament\Resources\RkaGenerateConfigs\Pages;

use App\Filament\Resources\RkaGenerateConfigs\RkaGenerateConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRkaGenerateConfig extends CreateRecord
{
    protected static string $resource = RkaGenerateConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
