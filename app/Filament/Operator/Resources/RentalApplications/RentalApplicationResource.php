<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\RentalApplications;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\RentalApplications\Pages\CreateRentalApplication;
use App\Filament\Operator\Resources\RentalApplications\Pages\EditRentalApplication;
use App\Filament\Operator\Resources\RentalApplications\Pages\ListRentalApplications;
use App\Filament\Operator\Resources\RentalApplications\Schemas\RentalApplicationForm;
use App\Filament\Operator\Resources\RentalApplications\Tables\RentalApplicationsTable;
use App\Models\RentalApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RentalApplicationResource extends Resource
{
    protected static ?string $model = RentalApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'Rental application';

    protected static ?string $pluralModelLabel = 'Rental applications';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::RentersLeases;

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return RentalApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentalApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentalApplications::route('/'),
            'create' => CreateRentalApplication::route('/create'),
            'edit' => EditRentalApplication::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['unit.property', 'renter']);
    }

    /** Applications still waiting for a decision. */
    public static function getNavigationBadge(): ?string
    {
        $open = RentalApplication::query()->where('status', RentalApplication::STATUS_RECEIVED)->count();

        return $open > 0 ? (string) $open : null;
    }
}
