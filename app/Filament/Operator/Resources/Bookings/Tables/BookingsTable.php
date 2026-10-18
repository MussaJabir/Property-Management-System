<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Bookings\Tables;

use App\Filament\Operator\Actions\FrontDeskActions;
use App\Filament\Operator\Resources\Guests\GuestResource;
use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('check_in_date', 'desc')
            ->columns([
                TextColumn::make('guest.full_name')
                    ->label(__('Guest'))
                    ->weight('semibold')
                    ->description(fn (Booking $record): string => (string) $record->guest?->phone)
                    ->url(fn (Booking $record): ?string => $record->guest !== null && GuestResource::canEdit($record->guest)
                        ? GuestResource::getUrl('edit', ['record' => $record->guest])
                        : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('guest', fn (Builder $g) => $g
                        ->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', '%'.ltrim($search, '0').'%'))),

                TextColumn::make('unit.code')
                    ->label(__('Room'))
                    ->description(fn (Booking $record): ?string => $record->unit?->property?->name),

                TextColumn::make('check_in_date')
                    ->label(__('Stay'))
                    ->sortable()
                    ->formatStateUsing(fn (Booking $record): string => $record->check_in_date->format('d/m/Y').' → '.$record->check_out_date->format('d/m/Y'))
                    ->description(fn (Booking $record): string => trans_choice(':count night|:count nights', $record->nights())),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Booking::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Booking::STATUS_CHECKED_IN => 'success',
                        Booking::STATUS_RESERVED => 'info',
                        Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('invoice.total_amount')
                    ->label(__('Bill'))
                    ->formatStateUsing(fn (Booking $record): string => (string) $record->invoice?->formatted_total)
                    ->description(fn (Booking $record): ?string => ($record->invoice?->balanceDue() ?? 0) > 0
                        ? __('owed :balance', ['balance' => $record->invoice->formatted_balance ?? ''])
                        : null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(Booking::statusLabels())
                    ->multiple(),
            ])
            ->recordActions([
                FrontDeskActions::checkIn()->button()->size('sm'),
                FrontDeskActions::checkOut()->button()->size('sm'),
                ActionGroup::make([
                    FrontDeskActions::extend(),
                    FrontDeskActions::noShow(),
                    FrontDeskActions::askForRating(),
                    Action::make('openBill')
                        ->label(__('Open bill'))
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->visible(fn (Booking $record): bool => $record->invoice !== null && InvoiceResource::canEdit($record->invoice))
                        ->url(fn (Booking $record): ?string => $record->invoice !== null ? InvoiceResource::getUrl('edit', ['record' => $record->invoice]) : null),
                    FrontDeskActions::cancel(),
                ]),
            ])
            ->emptyStateHeading(__('No bookings yet'))
            ->emptyStateDescription(__('Check a guest in or take a booking with the buttons above.'));
    }
}
