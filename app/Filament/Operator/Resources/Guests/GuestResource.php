<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Guests;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Guests\Pages\EditGuest;
use App\Filament\Operator\Resources\Guests\Pages\ListGuests;
use App\Filament\Operator\Resources\Guests\Schemas\GuestForm;
use App\Filament\Operator\Resources\Guests\Tables\GuestsTable;
use App\Filament\Operator\Support\ShortStays;
use App\Models\Guest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Guest register: everyone who has stayed, with their ID details. Guests
 * are created at check-in; this is where the details are completed.
 */
class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::FrontDesk;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationLabel(): string
    {
        return __('Guests');
    }

    public static function getModelLabel(): string
    {
        return __('Guest');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Guests');
    }

    public static function canAccess(): bool
    {
        return ShortStays::enabled() && parent::canAccess();
    }

    /** Guests come in through check-in, not a separate form. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'phone'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Guest $record */
        return [__('Phone') => (string) $record->phone];
    }

    public static function form(Schema $schema): Schema
    {
        return GuestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('bookings')
            ->withMax('bookings', 'check_in_date');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'edit' => EditGuest::route('/{record}/edit'),
        ];
    }
}
