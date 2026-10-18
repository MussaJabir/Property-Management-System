<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Bookings;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Bookings\Pages\ListBookings;
use App\Filament\Operator\Resources\Bookings\Tables\BookingsTable;
use App\Filament\Operator\Support\ShortStays;
use App\Models\Booking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every short stay: upcoming, in house and past. Bookings are made and
 * changed through the front desk actions (FrontDeskActions), not a form.
 * Only on plans with short stays (Pro, Enterprise).
 */
class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::FrontDesk;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('Bookings');
    }

    public static function getModelLabel(): string
    {
        return __('Booking');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bookings');
    }

    public static function canAccess(): bool
    {
        return ShortStays::enabled() && parent::canAccess();
    }

    public static function canCreate(): bool
    {
        return ShortStays::enabled() && parent::canCreate();
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['guest', 'unit.property', 'invoice', 'rating']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
        ];
    }
}
