<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Contractors;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Contractors\Pages\CreateContractor;
use App\Filament\Operator\Resources\Contractors\Pages\EditContractor;
use App\Filament\Operator\Resources\Contractors\Pages\ListContractors;
use App\Filament\Operator\Resources\Contractors\Schemas\ContractorForm;
use App\Filament\Operator\Resources\Contractors\Tables\ContractorsTable;
use App\Models\Contractor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractorResource extends Resource
{
    protected static ?string $model = Contractor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Contractors';

    protected static ?string $modelLabel = 'Contractor';

    protected static ?string $pluralModelLabel = 'Contractors';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Properties;

    protected static ?int $navigationSort = 41;

    /** Kept out of the menu to keep it short. Reached from Maintenance ("Contractors" button). */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return ContractorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractors::route('/'),
            'create' => CreateContractor::route('/create'),
            'edit' => EditContractor::route('/{record}/edit'),
        ];
    }
}
