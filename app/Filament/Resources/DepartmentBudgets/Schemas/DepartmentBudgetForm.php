<?php

namespace App\Filament\Resources\DepartmentBudgets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DepartmentBudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_id')
                    ->relationship('department', 'name')
                    ->required(),
                Select::make('account_id')
                    ->relationship('account', 'name')
                    ->required(),
                TextInput::make('fiscal_year')
                    ->required(),
                TextInput::make('allocated_amount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
