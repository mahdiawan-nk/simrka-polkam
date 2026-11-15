<?php

namespace App\Filament\Imports;

use App\Models\DepartmentBudget;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class DepartmentBudgetImporter extends Importer
{
    protected static ?string $model = DepartmentBudget::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('department')
                ->requiredMapping()
                ->relationship(resolveUsing: 'code')
                ->rules(['required', 'exists:departments,code']),

            ImportColumn::make('account')
                ->requiredMapping()
                ->relationship(resolveUsing: 'code')
                ->rules(['required', 'exists:accounts,code']),

            ImportColumn::make('fiscal_year')
                ->requiredMapping()
                ->rules(['required', 'max:9']),

            ImportColumn::make('allocated_amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
        ];
    }

    public function resolveRecord(): ?DepartmentBudget
    {
        // Melakukan update jika kombinasi department_id, account_id, dan fiscal_year sudah ada
        return DepartmentBudget::firstOrNew([
            'department_id' => $this->department->id ?? null,
            'account_id' => $this->account->id ?? null,
            'fiscal_year' => $this->data['fiscal_year'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your department budget import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}