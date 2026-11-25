<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Inspections;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Inspections\Pages\CreateInspection;
use App\Filament\Operator\Resources\Inspections\Pages\EditInspection;
use App\Filament\Operator\Resources\Inspections\Pages\ListInspections;
use App\Filament\Operator\Resources\Inspections\Pages\ViewInspection;
use App\Filament\Operator\Resources\Inspections\Schemas\InspectionForm;
use App\Filament\Operator\Resources\Inspections\Tables\InspectionsTable;
use App\Models\Inspection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Inspections';

    protected static ?string $modelLabel = 'Inspection';

    protected static ?string $pluralModelLabel = 'Inspections';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::RentersLeases;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return InspectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InspectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInspections::route('/'),
            'create' => CreateInspection::route('/create'),
            'view' => ViewInspection::route('/{record}'),
            'edit' => EditInspection::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['unit.property', 'lease.renter', 'inspector']);
    }
}
