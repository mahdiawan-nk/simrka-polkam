<?php

namespace App\Filament\Resources\RkaGenerateConfigs\Pages;

use App\Filament\Resources\RkaGenerateConfigs\RkaGenerateConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRkaGenerateConfig extends EditRecord
{
    protected static string $resource = RkaGenerateConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            // DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
