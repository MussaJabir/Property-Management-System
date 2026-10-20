<?php

namespace App\Filament\Operator\Resources\Receipts;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Operator\Resources\Receipts\Tables\ReceiptsTable;
use App\Models\Receipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Receipts';

    protected static ?string $modelLabel = 'Receipt';

    protected static ?string $pluralModelLabel = 'Receipts';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Billing;

    protected static ?int $navigationSort = 30;

    /** Kept out of the menu to keep it short. Reached from Payments ("Receipts" button). */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        // Receipts are issued by the Payment observer, never created manually.
        // An empty form keeps Filament happy while preventing edit/create paths.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceipts::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'payment.invoice.lease.renter',
            'payment.invoice.lease.unit.property',
            'payment.invoice.booking.guest',
        ]);
    }
}
