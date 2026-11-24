<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Leases;

use App\Filament\Operator\Support\RentFields;
use App\Models\Lease;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The lease state changes (activate, end, terminate, renew), shared by the
 * Leases list and the lease page.
 */
final class LeaseActions
{
    public static function activate(): Action
    {
        return Action::make('activate')
            ->label(__('Activate'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('Activate this lease?'))
            ->modalDescription(__('The linked unit will be marked occupied.'))
            ->visible(fn (Lease $record): bool => $record->isPending() && LeaseResource::canEdit($record))
            ->action(function (Lease $record): void {
                try {
                    $record->activate(auth()->id());
                    Notification::make()->title(__('Lease activated'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title(__('Could not activate'))->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function end(): Action
    {
        return Action::make('end')
            ->label(__('End (natural)'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Mark this lease as ended?'))
            ->modalDescription(__('Use this when the lease reaches its agreed end date. The unit becomes vacant unless its renewal has started.'))
            ->visible(fn (Lease $record): bool => $record->isActive() && LeaseResource::canEdit($record))
            ->action(function (Lease $record): void {
                try {
                    $record->end(auth()->id());
                    Notification::make()->title(__('Lease ended'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title(__('Could not end lease'))->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function terminate(): Action
    {
        return Action::make('terminate')
            ->label(__('Terminate'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Lease $record): bool => $record->isActive() && LeaseResource::canEdit($record))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->required()
                    ->rows(3)
                    ->placeholder(__('Why is this lease being terminated early?')),
            ])
            ->modalHeading(__('Terminate this lease early?'))
            ->modalDescription(__('Recorded in lease history. The unit becomes vacant.'))
            ->action(function (array $data, Lease $record): void {
                try {
                    $record->terminate($data['reason'] ?? null, auth()->id());
                    Notification::make()->title(__('Lease terminated'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title(__('Could not terminate'))->body($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * The next lease for the same renter and unit, starting the day after this
     * one ends, with the rent increase (if any) already applied.
     */
    public static function renew(): Action
    {
        return Action::make('renew')
            ->label(__('Renew'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (Lease $record): bool => $record->isActive() && ! $record->renewal()->exists() && LeaseResource::canCreate())
            ->modalHeading(__('Renew this lease'))
            ->modalDescription(__('Creates the next lease for the same renter and unit, ready to start the day after this one ends.'))
            ->modalSubmitActionLabel(__('Create renewal'))
            ->fillForm(fn (Lease $record): array => self::renewalDefaults($record))
            ->schema(fn (Lease $record): array => [
                Grid::make(2)->schema([
                    DatePicker::make('start_date')
                        ->label(__('Starts'))
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->after(fn (): ?string => $record->end_date?->toDateString()),
                    DatePicker::make('end_date')
                        ->label(__('Ends'))
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->after('start_date')
                        ->helperText(__('Leave empty for an open-ended lease.')),
                    TextInput::make('monthly_rent')
                        ->label(__('Rent a month'))
                        ->prefix($record->currency)
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->helperText($record->nextIncreaseOn() !== null
                            ? __('Includes the :pct% increase in the lease.', ['pct' => rtrim(rtrim((string) $record->increase_percent, '0'), '.')])
                            : null),
                    TextInput::make('deposit')
                        ->label(__('Deposit'))
                        ->prefix($record->currency)
                        ->numeric()
                        ->minValue(0),
                ]),
                Toggle::make('carry_deposit')
                    ->label(__('Carry over the current deposit'))
                    ->helperText(__('Only a top-up above it is billed.'))
                    ->visible($record->deposit_amount > 0),
                Toggle::make('activate')
                    ->label(__('It is signed: start it on its start date'))
                    ->helperText(__('Off keeps it pending until you activate it.')),
            ])
            ->action(function (array $data, Lease $record): void {
                $months = RentFields::monthsFor($record->billing_cycle, $record->billing_cycle_months);

                try {
                    $renewal = $record->renew([
                        'start_date' => Carbon::parse($data['start_date'])->toDateString(),
                        'end_date' => filled($data['end_date'] ?? null) ? Carbon::parse($data['end_date'])->toDateString() : null,
                        'rent_amount' => (int) round(((float) $data['monthly_rent']) * $months * 100),
                        'deposit_amount' => (int) round(((float) ($data['deposit'] ?? 0)) * 100),
                        'carry_deposit' => (bool) ($data['carry_deposit'] ?? false),
                        'activate' => (bool) ($data['activate'] ?? false),
                    ], auth()->id());
                } catch (Throwable $e) {
                    Notification::make()->title(__('Could not renew'))->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->success()
                    ->title(__('Renewal created'))
                    ->body(__('Starts :date. :state', [
                        'date' => $renewal->start_date?->format('d/m/Y'),
                        'state' => $renewal->isActive() ? __('It is active and will be billed from its start date.') : __('It stays pending until you activate it.'),
                    ]))
                    ->send();
            });
    }

    /** @return array<string, mixed> */
    public static function renewalDefaults(Lease $lease): array
    {
        $months = RentFields::monthsFor($lease->billing_cycle, $lease->billing_cycle_months);
        $start = ($lease->end_date ?? today())->copy()->addDay();
        $length = $lease->start_date !== null && $lease->end_date !== null
            ? max(1, (int) round($lease->start_date->diffInMonths($lease->end_date->copy()->addDay())))
            : 12;
        $rent = $lease->nextIncreaseOn() !== null ? $lease->rentAfterIncrease() : $lease->rent_amount;

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addMonthsNoOverflow($length)->subDay()->toDateString(),
            'monthly_rent' => RentFields::monthlyFrom($rent, $months),
            'deposit' => $lease->deposit_amount / 100,
            'carry_deposit' => $lease->deposit_amount > 0,
            'activate' => true,
        ];
    }
}
