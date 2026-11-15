<?php

namespace App\Filament\Resources\RkaGenerateConfigs\Pages;

use App\Filament\Resources\RkaGenerateConfigs\RkaGenerateConfigResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewRkaGenerateConfig extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RkaGenerateConfigResource::class;

    protected string $view = 'filament.resources.rka-generate-configs.pages.view-rka-generate-config';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        // Eager load relasi untuk optimasi kueri Blade
        $this->record->loadMissing(['rkaSetting', 'creator']);
    }

    public function getTitle(): string
    {
        return "Detail Konfigurasi: {$this->record->config_name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}