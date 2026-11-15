<?php

namespace App\Filament\Resources\FundRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FundRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('document_number')
                    ->required(),
                DatePicker::make('request_date')
                    ->required(),
                TextInput::make('fiscal_year')
                    ->required(),
                Select::make('department_id')
                    ->relationship('department', 'name')
                    ->required(),
                TextInput::make('activity_name')
                    ->required(),
                DatePicker::make('execution_date'),
                TextInput::make('responsible_person')
                    ->required(),
                TextInput::make('disbursement_type')
                    ->required(),
                TextInput::make('recipient_name')
                    ->required(),
                TextInput::make('bank_account_info'),
                TextInput::make('attachments'),
                TextInput::make('total_requested')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_approved')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Select::make('status')
                    ->options([
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'verified_finance' => 'Verified finance',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ])
                    ->default('draft')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
