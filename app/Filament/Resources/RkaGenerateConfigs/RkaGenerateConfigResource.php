<?php

namespace App\Filament\Resources\RkaGenerateConfigs;

use App\Filament\Resources\RkaGenerateConfigs\Pages\CreateRkaGenerateConfig;
use App\Filament\Resources\RkaGenerateConfigs\Pages\EditRkaGenerateConfig;
use App\Filament\Resources\RkaGenerateConfigs\Pages\ListRkaGenerateConfigs;
use App\Filament\Resources\RkaGenerateConfigs\Pages\ViewRkaGenerateConfig;
use App\Filament\Resources\RkaGenerateConfigs\Schemas\RkaGenerateConfigForm;
use App\Filament\Resources\RkaGenerateConfigs\Schemas\RkaGenerateConfigInfolist;
use App\Filament\Resources\RkaGenerateConfigs\Tables\RkaGenerateConfigsTable;
use App\Models\RkaGenerateConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RkaGenerateConfigResource extends Resource
{
    protected static ?string $model = RkaGenerateConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen RKA';

    protected static ?string $navigationLabel = 'Konfigurasi Generate RKA';

    protected static ?string $modelLabel = 'Konfigurasi Generate RKA';

    protected static ?string $pluralModelLabel = 'Konfigurasi Generate RKA';

    public static function form(Schema $schema): Schema
    {
        return RkaGenerateConfigForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RkaGenerateConfigInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RkaGenerateConfigsTable::configure($table);
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
            'index' => ListRkaGenerateConfigs::route('/'),
            'create' => CreateRkaGenerateConfig::route('/create'),
            'view' => ViewRkaGenerateConfig::route('/{record}'),
            'edit' => EditRkaGenerateConfig::route('/{record}/edit'),
        ];
    }
}
