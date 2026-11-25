<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\MaintenanceSchedules;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\MaintenanceSchedules\Pages\CreateMaintenanceSchedule;
use App\Filament\Operator\Resources\MaintenanceSchedules\Pages\EditMaintenanceSchedule;
use App\Filament\Operator\Resources\MaintenanceSchedules\Pages\ListMaintenanceSchedules;
use App\Filament\Operator\Resources\MaintenanceSchedules\Schemas\MaintenanceScheduleForm;
use App\Filament\Operator\Resources\MaintenanceSchedules\Tables\MaintenanceSchedulesTable;
use App\Models\MaintenanceSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceScheduleResource extends Resource
{
    protected static ?string $model = MaintenanceSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Maintenance schedules';

    protected static ?string $modelLabel = 'Maintenance schedule';

    protected static ?string $pluralModelLabel = 'Maintenance schedules';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Properties;

    protected static ?int $navigationSort = 42;

    /** Kept out of the menu to keep it short. Reached from Maintenance ("Schedules" button). */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceSchedulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceSchedules::route('/'),
            'create' => CreateMaintenanceSchedule::route('/create'),
            'edit' => EditMaintenanceSchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['unit.property', 'contractor']);
    }
}
