<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Ratings;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Ratings\Pages\ListRatings;
use App\Models\Rating;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What guests and renters think: 1–5 stars and comments, newest first.
 * Ratings come from the guest and renter links and the renter portal.
 * Nothing shows on the public room pages until it is approved here
 * ("Show on website"); a rating changed afterwards needs approving again.
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
                TextColumn::make('published_at')
                    ->label(__('Website'))
                    ->badge()
                    ->getStateUsing(fn (Rating $record): string => $record->isPublished() ? __('Shown') : __('Hidden'))
                    ->color(fn (Rating $record): string => $record->isPublished() ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('Show on website'))
                    ->icon(Heroicon::OutlinedEye)
                    ->authorize(fn (Rating $record): bool => auth()->user()?->can('publish', $record) ?? false)
                    ->visible(fn (Rating $record): bool => ! $record->isPublished())
                    ->action(function (Rating $record): void {
                        $record->forceFill(['published_at' => now()])->save();
                        Notification::make()->success()->title(__('Shown on your website'))->send();
                    }),
                Action::make('unpublish')
                    ->label(__('Hide'))
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->authorize(fn (Rating $record): bool => auth()->user()?->can('publish', $record) ?? false)
                    ->visible(fn (Rating $record): bool => $record->isPublished())
                    ->action(function (Rating $record): void {
                        $record->forceFill(['published_at' => null])->save();
                        Notification::make()->success()->title(__('Hidden from your website'))->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('source')->label(__('From'))->options(Rating::sourceLabels()),
                SelectFilter::make('website')
                    ->label(__('Website'))
                    ->options(['shown' => __('Shown'), 'hidden' => __('Hidden')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'shown' => $query->whereNotNull('published_at'),
                        'hidden' => $query->whereNull('published_at'),
                        default => $query,
                    }),
                SelectFilter::make('score')->label(__('Stars'))->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']),
            ])
            ->emptyStateHeading(__('No ratings yet'))
            ->emptyStateDescription(__('Guests get a link at check-out, renters from their lease (Ask for a review on WhatsApp). Approve the ones you want on your website.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRatings::route('/'),
        ];
    }
}
