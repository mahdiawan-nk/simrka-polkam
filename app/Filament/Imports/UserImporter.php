<?php

namespace App\Filament\Imports;

use App\Models\Department;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),

            ImportColumn::make('email_verified_at')
                ->rules(['nullable', 'datetime']),

            ImportColumn::make('password')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            // Menggunakan method roles() dari Trait HasRoles Spatie
            ImportColumn::make('roles')
                ->relationship(resolveUsing: 'name')
                ->rules(['nullable', 'string']),

            // Menggunakan relasi departments() BelongsToMany berdasarkan kolom 'code'
            ImportColumn::make('departments')
                ->relationship(resolveUsing: 'code')
                ->rules(['nullable', 'string']),
        ];
    }

    public function resolveRecord(): User
    {
        return User::firstOrNew([
            'email' => $this->data['email'],
        ]);
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        // Memastikan baris pivot pada user_departments terisi assignment_type = 'primary'
        if (! empty($this->data['departments'])) {
            $department = Department::where('code', $this->data['departments'])->first();

            if ($department) {
                $user->departments()->updateExistingPivot($department->id, [
                    'assignment_type' => 'primary',
                ]);
            }
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your user import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
