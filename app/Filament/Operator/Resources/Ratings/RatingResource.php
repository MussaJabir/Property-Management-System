<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Ratings;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Ratings\Pages\ListRatings;
use App\Models\Rating;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What guests and renters think: 1–5 stars and comments, newest first.
 * Read-only; ratings come from the guest link and the renter portal.
 */
class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::RentersLeases;

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('Ratings');
    }

    public static function getModelLabel(): string
    {
        return __('Rating');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ratings');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['booking.guest', 'booking.unit', 'lease.renter', 'lease.unit']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('score')
                    ->label(__('Rating'))
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state): string => $state >= 4 ? 'success' : ($state === 3 ? 'warning' : 'danger'))
                    ->sortable(),
                TextColumn::make('comment')
                    ->label(__('Comment'))
                    ->placeholder(__('No comment'))
                    ->wrap()
                    ->limit(200),
                TextColumn::make('from_name')
                    ->label(__('From'))
                    ->description(fn (Rating $record): string => collect([Rating::sourceLabels()[$record->source] ?? $record->source, $record->unit_code])->filter()->implode(' · ')),
                TextColumn::make('updated_at')
                    ->label(__('When'))
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')->label(__('From'))->options(Rating::sourceLabels()),
                SelectFilter::make('score')->label(__('Stars'))->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']),
            ])
            ->emptyStateHeading(__('No ratings yet'))
            ->emptyStateDescription(__('Renters can rate from their portal. Guests get a link at check-out (Ask for a rating on WhatsApp).'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRatings::route('/'),
        ];
    }
}
