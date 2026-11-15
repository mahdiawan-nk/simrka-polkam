<?php

namespace App\Filament\Resources\RkaHeaders\Pages;

use App\Filament\Resources\RkaHeaders\RkaHeaderResource;
use App\Models\RkaHeader;
use App\Models\RkaLog;
use App\Services\RkaActivityLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRkaHeader extends EditRecord
{
    protected static string $resource = RkaHeaderResource::class;

    protected function afterSave(): void
    {
        app(RkaActivityLogService::class)->log(
            action: RkaLog::ACTION_UPDATE_RKA,
            headerId: $this->record->id,
            subject: $this->record,
            description: 'Data RKA Header diperbarui.',
            newValues: [
                'status' => $this->record->status,
                'is_locked' => $this->record->is_locked,
                'pagu_budget' => $this->record->pagu_budget,
            ],
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (RkaHeader $record): void {
                    app(RkaActivityLogService::class)->log(
                        action: RkaLog::ACTION_DELETE_RKA,
                        headerId: null,
                        subject: $record,
                        description: "RKA Header #{$record->id} dihapus.",
                        oldValues: [
                            'rka_header_id' => $record->id,
                            'department_id' => $record->department_id,
                            'status' => $record->status,
                            'pagu_budget' => $record->pagu_budget,
                        ],
                    );
                }),
        ];
    }
}
