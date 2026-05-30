<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests;

use App\Filament\Operator\Resources\MaintenanceRequests\Pages\CreateMaintenanceRequest;
use App\Filament\Operator\Resources\MaintenanceRequests\Pages\EditMaintenanceRequest;
use App\Filament\Operator\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use App\Filament\Operator\Resources\MaintenanceRequests\RelationManagers\UpdatesRelationManager;
use App\Filament\Operator\Resources\MaintenanceRequests\Schemas\MaintenanceRequestForm;
use App\Filament\Operator\Resources\MaintenanceRequests\Tables\MaintenanceRequestsTable;
use App\Models\MaintenanceRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaintenanceRequestResource extends Resource
{
    protected static ?string $model = MaintenanceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?string $modelLabel = 'Maintenance request';

    protected static ?string $pluralModelLabel = 'Maintenance requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRequests::route('/'),
            'create' => CreateMaintenanceRequest::route('/create'),
            'edit' => EditMaintenanceRequest::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            UpdatesRelationManager::class,
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
        return parent::getEloquentQuery()->with(['unit.property', 'reportedBy', 'assignedTo']);
    }
}
