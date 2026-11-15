<?php

namespace App\Filament\Resources\DepartmentBudgets\Pages;

use App\Filament\Resources\DepartmentBudgets\DepartmentBudgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Imports\DepartmentBudgetImporter;
use Filament\Actions\ImportAction;

class ListDepartmentBudgets extends ListRecords
{
    protected static string $resource = DepartmentBudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make('Import Anggaran Departemen')
                ->label('Import Anggaran Departemen')
                ->icon('heroicon-o-document-arrow-up')
                ->importer(DepartmentBudgetImporter::class)
                ->color('primary'),
        ];
    }
}
