<?php

namespace App\Filament\Resources\RkaHeaders;

use App\Filament\Resources\RkaHeaders\Pages\CreateRkaHeader;
use App\Filament\Resources\RkaHeaders\Pages\EditRkaHeader;
use App\Filament\Resources\RkaHeaders\Pages\ListRkaHeaders;
use App\Filament\Resources\RkaHeaders\Pages\ReviewRkaPage;
use App\Filament\Resources\RkaHeaders\Pages\RkaFormIndicatorAndItem;
use App\Filament\Resources\RkaHeaders\Schemas\RkaHeaderForm;
use App\Filament\Resources\RkaHeaders\Tables\RkaHeadersTable;
use App\Models\RkaHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RkaHeaderResource extends Resource
{
    protected static ?string $model = RkaHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen RKA';

    protected static ?string $navigationLabel = 'Pengusulan & Review RKA';

    public static function form(Schema $schema): Schema
    {
        return RkaHeaderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RkaHeadersTable::configure($table);
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
            'index' => ListRkaHeaders::route('/'),
            'create' => CreateRkaHeader::route('/create'),
            'edit' => EditRkaHeader::route('/{record}/edit'),
            'form-indicator-and-item' => RkaFormIndicatorAndItem::route('/{record}/form-indicator-and-item'),
            'view' => RkaFormIndicatorAndItem::route('/{record}/view'),
            'review' => ReviewRkaPage::route('/{record}/review'),
        ];
    }
}
