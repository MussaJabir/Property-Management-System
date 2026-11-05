<?php

namespace App\Filament\Operator\Resources\Units\Tables;

use App\Models\Unit;
use App\Services\Plans\PlanUsage;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('')
                    ->collection('photos')
                    ->conversion('thumb')
                    // Show only the cover (first) photo, not the whole gallery —
                    // a unit may have many images but the list needs one thumbnail.
                    ->limit(1)
                    ->imageWidth(56)
                    ->imageHeight(40)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover'])
                    ->defaultImageUrl(fn ($record): ?string => $record->property?->getFirstMediaUrl('photos', 'thumb') ?: null),

                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('property.name')
                    ->label('Property')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'business_frame' => 'Business frame',
                        default => ucfirst($state),
                    })
                    ->color('gray'),

                // Nightly rate for short stays, rent for leases, both for "both".
                TextColumn::make('formatted_rent')
                    ->label('Rent')
                    ->getStateUsing(fn (Unit $record): array => array_map(
                        fn (array $price): string => $price['currency'].' '.$price['amount'].' / '.$price['per'],
                        $record->publicPrices(),
                    ) ?: [$record->formatted_rent])
                    ->listWithLineBreaks()
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('rent_amount', $direction)),

                TextColumn::make('billing_cycle')
                    ->label('Cycle')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state, Unit $record): string => $record->rental_mode === Unit::RENTAL_SHORT_STAY ? __('Nightly') : match ($state) {
                        'monthly' => 'Monthly',
                        'quarterly' => 'Every 3 months',
                        'semi_annual' => 'Every 6 months',
                        'annual' => 'Yearly',
                        'custom' => 'Every '.($record->billing_cycle_months ?? '?').' months',
                        default => ucfirst($state),
                    })
                    ->toggleable(),

                IconColumn::make('on_marketplace')
                    ->label(__('Marketplace'))
                    ->boolean()
                    ->trueIcon(fn (Unit $record): string => $record->isFeatured() ? 'heroicon-s-star' : 'heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->tooltip(fn (Unit $record): ?string => $record->isFeatured()
                        ? __('Featured until :date', ['date' => $record->featured_until?->format('d/m/Y')])
                        : null)
                    ->visible(fn (): bool => self::marketplaceEnabled())
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vacant' => 'success',
                        'occupied' => 'info',
                        'maintenance' => 'warning',
                        'reserved' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'vacant' => 'Vacant',
                        'occupied' => 'Occupied',
                        'maintenance' => 'Maintenance',
                        'reserved' => 'Reserved',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'room' => 'Room',
                        'apartment' => 'Apartment',
                        'business_frame' => 'Business frame',
                        'office' => 'Office',
                        'shop' => 'Shop',
                        'warehouse' => 'Warehouse',
                    ]),
                TernaryFilter::make('on_marketplace')
                    ->label(__('On the marketplace'))
                    ->visible(fn (): bool => self::marketplaceEnabled()),
                SelectFilter::make('billing_cycle')
                    ->label('Billing cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'semi_annual' => 'Semi-annual',
                        'annual' => 'Yearly',
                        'custom' => 'Custom',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('listOnMarketplace')
                        ->label(__('List on marketplace'))
                        ->icon('heroicon-o-building-storefront')
                        ->visible(fn (): bool => self::marketplaceEnabled())
                        ->action(fn (Collection $records) => self::setMarketplace($records, true))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('removeFromMarketplace')
                        ->label(__('Remove from marketplace'))
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->visible(fn (): bool => self::marketplaceEnabled())
                        ->action(fn (Collection $records) => self::setMarketplace($records, false))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }

    /** The client's plan includes the PMS marketplace. */
    private static function marketplaceEnabled(): bool
    {
        return PlanUsage::forCurrentUser()?->hasFeature('marketplace_listing') ?? true;
    }

    /** @param  Collection<int, Unit>  $records */
    private static function setMarketplace(Collection $records, bool $on): void
    {
        $records->each(fn (Unit $unit) => $unit->update(['on_marketplace' => $on]));

        Notification::make()
            ->success()
            ->title($on
                ? trans_choice(':count unit listed on the marketplace|:count units listed on the marketplace', $records->count())
                : trans_choice(':count unit removed from the marketplace|:count units removed from the marketplace', $records->count()))
            ->body($on ? __('Only vacant units are shown to renters.') : null)
            ->send();
    }
}
