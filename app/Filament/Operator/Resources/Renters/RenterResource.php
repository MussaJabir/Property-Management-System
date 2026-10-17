<?php

namespace App\Filament\Operator\Resources\Renters;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Renters\Pages\CreateRenter;
use App\Filament\Operator\Resources\Renters\Pages\EditRenter;
use App\Filament\Operator\Resources\Renters\Pages\ListRenters;
use App\Filament\Operator\Resources\Renters\Schemas\RenterForm;
use App\Filament\Operator\Resources\Renters\Tables\RentersTable;
use App\Models\Renter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RenterResource extends Resource
{
    protected static ?string $model = Renter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Renters';

    protected static ?string $modelLabel = 'Renter';

    protected static ?string $pluralModelLabel = 'Renters';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::RentersLeases;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'full_name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'phone', 'email'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Renter $record */
        return array_filter([__('Phone') => (string) $record->phone]);
    }

    public static function form(Schema $schema): Schema
    {
        return RenterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRenters::route('/'),
            'create' => CreateRenter::route('/create'),
            'edit' => EditRenter::route('/{record}/edit'),
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
