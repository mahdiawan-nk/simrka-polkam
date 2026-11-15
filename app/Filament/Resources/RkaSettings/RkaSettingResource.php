<?php

namespace App\Filament\Resources\RkaSettings;

use App\Filament\Resources\RkaSettings\Pages\CreateRkaSetting;
use App\Filament\Resources\RkaSettings\Pages\EditRkaSetting;
use App\Filament\Resources\RkaSettings\Pages\ListRkaSettings;
use App\Filament\Resources\RkaSettings\Schemas\RkaSettingForm;
use App\Filament\Resources\RkaSettings\Tables\RkaSettingsTable;
use App\Models\RkaSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RkaSettingResource extends Resource
{
    protected static ?string $model = RkaSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected static string | UnitEnum | null $navigationGroup = 'Manajeman RKA';
    protected static ?string $navigationLabel = 'Pengaturan RKA';
    public static function form(Schema $schema): Schema
    {
        return RkaSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RkaSettingsTable::configure($table);
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
            'index' => ListRkaSettings::route('/'),
            'create' => CreateRkaSetting::route('/create'),
            'edit' => EditRkaSetting::route('/{record}/edit'),
        ];
    }
}
