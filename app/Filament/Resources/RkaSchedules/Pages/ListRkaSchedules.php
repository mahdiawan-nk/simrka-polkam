<?php

namespace App\Filament\Resources\RkaSchedules\Pages;

use App\Filament\Resources\RkaSchedules\RkaScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRkaSchedules extends ListRecords
{
    protected static string $resource = RkaScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
