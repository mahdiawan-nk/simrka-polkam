<?php

namespace App\Filament\Resources\RkaGenerateConfigs\Pages;

use App\Filament\Resources\RkaGenerateConfigs\RkaGenerateConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRkaGenerateConfigs extends ListRecords
{
    protected static string $resource = RkaGenerateConfigResource::class;

    public function mount(): void
    {
        parent::mount();

        // Ambil data pertama dari database
        $record = static::getResource()::getModel()::first();

        if ($record) {
            // Jika data ada, alihkan ke halaman View (atau Edit)
            $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
        } else {
            // Jika data kosong, alihkan ke halaman Create
            $this->redirect(static::getResource()::getUrl('create'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
