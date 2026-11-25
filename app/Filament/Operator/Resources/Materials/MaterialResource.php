<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Materials;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Materials\Pages\CreateMaterial;
use App\Filament\Operator\Resources\Materials\Pages\EditMaterial;
use App\Filament\Operator\Resources\Materials\Pages\ListMaterials;
use App\Filament\Operator\Resources\Materials\Schemas\MaterialForm;
use App\Filament\Operator\Resources\Materials\Tables\MaterialsTable;
use App\Models\Material;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Materials';

    protected static ?string $modelLabel = 'Material';

    protected static ?string $pluralModelLabel = 'Materials';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Properties;

    protected static ?int $navigationSort = 43;

    /** Kept out of the menu to keep it short. Reached from Maintenance ("Materials" button). */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return MaterialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaterialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterials::route('/'),
            'create' => CreateMaterial::route('/create'),
            'edit' => EditMaterial::route('/{record}/edit'),
        ];
    }
}
