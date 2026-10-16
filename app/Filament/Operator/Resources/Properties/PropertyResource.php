<?php

namespace App\Filament\Operator\Resources\Properties;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Properties\Pages\CreateProperty;
use App\Filament\Operator\Resources\Properties\Pages\EditProperty;
use App\Filament\Operator\Resources\Properties\Pages\ListProperties;
use App\Filament\Operator\Resources\Properties\Schemas\PropertyForm;
use App\Filament\Operator\Resources\Properties\Tables\PropertiesTable;
use App\Models\Property;
use App\Services\Plans\PlanUsage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Properties';

    protected static ?string $modelLabel = 'Property';

    protected static ?string $pluralModelLabel = 'Properties';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Properties;

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return parent::canCreate()
            && (PlanUsage::forCurrentUser()?->canAdd(PlanUsage::PROPERTIES) ?? true);
    }

    public static function form(Schema $schema): Schema
    {
        return PropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'edit' => EditProperty::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
