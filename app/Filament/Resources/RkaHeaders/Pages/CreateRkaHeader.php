<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\RkaLog;
use App\Services\RkaActivityLogService;
use Filament\Resources\Pages\CreateRecord;

class CreateRkaHeader extends CreateRecord
{
    protected static string $resource = RkaHeaderResource::class;

    protected function afterCreate(): void
    {
        app(RkaActivityLogService::class)->log(
            action: RkaLog::ACTION_CREATE_RKA,
            headerId: $this->record->id,
            subject: $this->record,
            description: 'RKA dibuat.',
            newValues: [
                'schedule_id' => $this->record->schedule_id,
                'department_id' => $this->record->department_id,
                'status' => $this->record->status,
            ],
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('form-indicator-and-item', ['record' => $this->record]);
    }
}
