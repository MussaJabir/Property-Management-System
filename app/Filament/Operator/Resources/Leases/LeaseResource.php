<?php

namespace App\Filament\Operator\Resources\Leases;

use App\Filament\Operator\Resources\Leases\Pages\CreateLease;
use App\Filament\Operator\Resources\Leases\Pages\EditLease;
use App\Filament\Operator\Resources\Leases\Pages\ListLeases;
use App\Filament\Operator\Resources\Leases\Schemas\LeaseForm;
use App\Filament\Operator\Resources\Leases\Tables\LeasesTable;
use App\Models\Lease;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeaseResource extends Resource
{
    protected static ?string $model = Lease::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Leases';

    protected static ?string $modelLabel = 'Lease';

    protected static ?string $pluralModelLabel = 'Leases';

    protected static string|\UnitEnum|null $navigationGroup = 'Leasing';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return LeaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeases::route('/'),
            'create' => CreateLease::route('/create'),
            'edit' => EditLease::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['renter', 'unit.property']);
    }
}
