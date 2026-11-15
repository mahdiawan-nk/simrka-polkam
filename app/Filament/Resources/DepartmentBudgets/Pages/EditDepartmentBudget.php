<?php

namespace App\Filament\Resources\DepartmentBudgets\Pages;

use App\Filament\Resources\DepartmentBudgets\DepartmentBudgetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartmentBudget extends EditRecord
{
    protected static string $resource = DepartmentBudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
