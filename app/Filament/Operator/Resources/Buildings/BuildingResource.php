<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Buildings;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Buildings\Pages\CreateBuilding;
use App\Filament\Operator\Resources\Buildings\Pages\EditBuilding;
use App\Filament\Operator\Resources\Buildings\Pages\ListBuildings;
use App\Filament\Operator\Resources\Buildings\Schemas\BuildingForm;
use App\Filament\Operator\Resources\Buildings\Tables\BuildingsTable;
use App\Models\Building;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Buildings';

    protected static ?string $modelLabel = 'Building';

    protected static ?string $pluralModelLabel = 'Buildings';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Properties;

    protected static ?int $navigationSort = 11;

    /** Kept out of the menu to keep it short. Reached from Properties ("Buildings" button). */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return BuildingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BuildingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuildings::route('/'),
            'create' => CreateBuilding::route('/create'),
            'edit' => EditBuilding::route('/{record}/edit'),
        ];
    }
}
