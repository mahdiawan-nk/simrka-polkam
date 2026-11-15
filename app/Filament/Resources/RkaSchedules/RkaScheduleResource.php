<?php

namespace App\Filament\Resources\RkaSchedules;

use App\Filament\Resources\RkaSchedules\Pages\CreateRkaSchedule;
use App\Filament\Resources\RkaSchedules\Pages\EditRkaSchedule;
use App\Filament\Resources\RkaSchedules\Pages\ListRkaSchedules;
use App\Filament\Resources\RkaSchedules\Schemas\RkaScheduleForm;
use App\Filament\Resources\RkaSchedules\Tables\RkaSchedulesTable;
use App\Models\RkaSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RkaScheduleResource extends Resource
{
    protected static ?string $model = RkaSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen RKA';

    protected static ?string $navigationLabel = 'Jadwal Pengusulan RKA';

    public static function form(Schema $schema): Schema
    {
        return RkaScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RkaSchedulesTable::configure($table);
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
            'index' => ListRkaSchedules::route('/'),
            'create' => CreateRkaSchedule::route('/create'),
            'edit' => EditRkaSchedule::route('/{record}/edit'),
        ];
    }
}
