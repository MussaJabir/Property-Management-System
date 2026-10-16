<?php

namespace App\Filament\Operator\Resources\Units;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Units\Pages\CreateUnit;
use App\Filament\Operator\Resources\Units\Pages\EditUnit;
use App\Filament\Operator\Resources\Units\Pages\ListUnits;
use App\Filament\Operator\Resources\Units\Schemas\UnitForm;
use App\Filament\Operator\Resources\Units\Tables\UnitsTable;
use App\Models\Unit;
use App\Services\Plans\PlanUsage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Units';

    protected static ?string $modelLabel = 'Unit';

    protected static ?string $pluralModelLabel = 'Units';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Properties;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'code';

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Unit $record */
        return [__('Property') => (string) $record->property?->name, __('Status') => ucfirst((string) $record->status)];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('property');
    }

    public static function canCreate(): bool
    {
        return parent::canCreate()
            && (PlanUsage::forCurrentUser()?->canAdd(PlanUsage::UNITS) ?? true);
    }

    public static function form(Schema $schema): Schema
    {
        return UnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnits::route('/'),
            'create' => CreateUnit::route('/create'),
            'edit' => EditUnit::route('/{record}/edit'),
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
