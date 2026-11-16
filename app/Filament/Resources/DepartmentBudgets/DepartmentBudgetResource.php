<?php

namespace App\Filament\Resources\DepartmentBudgets;

use App\Filament\Resources\DepartmentBudgets\Pages\CreateDepartmentBudget;
use App\Filament\Resources\DepartmentBudgets\Pages\EditDepartmentBudget;
use App\Filament\Resources\DepartmentBudgets\Pages\ListDepartmentBudgets;
use App\Filament\Resources\DepartmentBudgets\Schemas\DepartmentBudgetForm;
use App\Filament\Resources\DepartmentBudgets\Tables\DepartmentBudgetsTable;
use App\Models\DepartmentBudget;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DepartmentBudgetResource extends Resource
{
    protected static ?string $model = DepartmentBudget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Anggaran';

    protected static ?string $navigationLabel = 'Alokasi Pagu Unit';

    protected static ?string $modelLabel = 'Alokasi Pagu Unit';

    protected static ?string $pluralModelLabel = 'Alokasi Pagu Unit';

    public static function form(Schema $schema): Schema
    {
        return DepartmentBudgetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentBudgetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartmentBudgets::route('/'),
            // 'create' => CreateDepartmentBudget::route('/create'),
            // 'edit' => EditDepartmentBudget::route('/{record}/edit'),
        ];
    }
}
