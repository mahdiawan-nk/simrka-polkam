<?php

namespace App\Filament\Resources\FundRequests;

use App\Filament\Resources\FundRequests\Pages\CreateFundRequestCustom;
use App\Filament\Resources\FundRequests\Pages\EditFundRequestCustom;
use App\Filament\Resources\FundRequests\Pages\ListFundRequests;
use App\Filament\Resources\FundRequests\Pages\VerifyFundRequest;
use App\Filament\Resources\FundRequests\Pages\ViewFundRequest;
use App\Filament\Resources\FundRequests\Schemas\FundRequestForm;
use App\Filament\Resources\FundRequests\Tables\FundRequestsTable;
use App\Models\FundRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FundRequestResource extends Resource
{
    protected static ?string $model = FundRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Pengajuan Dana';

    public static function form(Schema $schema): Schema
    {
        return FundRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FundRequestsTable::configure($table);
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
            'index' => ListFundRequests::route('/'),
            'create' => CreateFundRequestCustom::route('/create'),
            'view' => ViewFundRequest::route('/{record}'),
            'edit' => EditFundRequestCustom::route('/{record}/edit'),
            'verify' => VerifyFundRequest::route('/{record}/verify'),
        ];
    }
}
