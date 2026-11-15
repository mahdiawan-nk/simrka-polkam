<?php

namespace App\Filament\Resources\RkaSchedules\Pages;

use App\Filament\Resources\RkaSchedules\RkaScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRkaSchedule extends EditRecord
{
    protected static string $resource = RkaScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
