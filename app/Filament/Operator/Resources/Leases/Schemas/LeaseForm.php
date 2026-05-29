<?php

namespace App\Filament\Operator\Resources\Leases\Schemas;

use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Form schema for leases. Powers two surfaces:
 *
 *   - Edit page: configure() returns a flat section layout. Renter and Unit
 *     are read-only after creation (changing them would silently invalidate
 *     invoices, payments, and lease history). Change those via terminating
 *     the lease and creating a new one.
 *   - Create page: wizardSteps() returns four Wizard steps — Renter → Unit
 *     → Terms → Confirm.
 *
 * Rent values are entered in WHOLE shillings (e.g. 350000) and dehydrated
 * to minor units (cents) for storage. Matches the convention from UnitForm.
 */
class LeaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parties')
                    ->columns(2)
                    ->components([
                        Select::make('renter_id')
                            ->label('Renter')
                            ->relationship('renter', 'full_name')
                            ->getOptionLabelFromRecordUsing(fn (Renter $record): string => $record->display_name)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Renter is locked after lease creation. Terminate and create a new lease to reassign.'),

                        Select::make('unit_id')
                            ->label('Unit')
                            ->options(fn () => Unit::with('property')->get()->mapWithKeys(
                                fn (Unit $unit) => [$unit->id => $unit->property?->name.' — '.$unit->code],
                            ))
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Unit is locked after lease creation.'),
                    ]),

                Section::make('Terms')
                    ->columns(2)
                    ->components(self::termsComponents()),

                Section::make('Notes')
                    ->collapsed()
                    ->components([
                        Textarea::make('terms_notes')
                            ->hiddenLabel()
                            ->rows(4)
                            ->placeholder('Any clauses, conditions, or notes worth recording.'),
                    ]),
            ]);
    }

    /**
     * Wizard steps for the create flow. Each step gates progression on its
     * own validation; rent/currency/billing_cycle auto-fill from the chosen
     * unit when entering step 2.
     *
     * @return array<int, Step>
     */
    public static function wizardSteps(): array
    {
        return [
            Step::make('Renter')
                ->description('Who is renting?')
                ->schema([
                    Select::make('renter_id')
                        ->label('Renter')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn () => Renter::query()
                            ->orderBy('full_name')
                            ->get()
                            ->mapWithKeys(fn (Renter $r) => [$r->id => $r->display_name]))
                        ->helperText('Pick an existing renter, or open the Renters page first to add one.'),
                ]),

            Step::make('Unit')
                ->description('Which unit are they renting?')
                ->schema([
                    Select::make('unit_id')
                        ->label('Vacant unit')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(fn () => Unit::query()
                            ->where('status', Unit::STATUS_VACANT)
                            ->with('property')
                            ->get()
                            ->mapWithKeys(function (Unit $unit): array {
                                $propertyName = $unit->property ? $unit->property->name : 'Unknown property';

                                return [$unit->id => $propertyName.' — '.$unit->code];
                            }))
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (! $state) {
                                return;
                            }

                            $unit = Unit::find($state);
                            if (! $unit) {
                                return;
                            }

                            // Snapshot the unit's current rent into the form (in
                            // whole shillings — the TextInput's
                            // dehydrateStateUsing will convert back to cents
                            // when the form submits).
                            $set('rent_amount', (int) ($unit->rent_amount / 100));
                            $set('currency', $unit->rent_currency);
                            $set('billing_cycle', $unit->billing_cycle);
                            $set('billing_cycle_months', $unit->billing_cycle_months);
                        })
                        ->helperText('Only vacant units are shown. Mark a unit vacant first if it isn\'t listed.'),
                ]),

            Step::make('Terms')
                ->description('Dates, rent, deposit')
                ->columns(2)
                ->schema(self::termsComponents()),

            Step::make('Confirm')
                ->description('Review and create')
                ->schema([
                    Placeholder::make('summary')
                        ->hiddenLabel()
                        ->content(function (Get $get) {
                            $renter = $get('renter_id') ? Renter::find($get('renter_id')) : null;
                            $unit = $get('unit_id') ? Unit::with('property')->find($get('unit_id')) : null;

                            $rent = $get('rent_amount');
                            $currency = $get('currency') ?: 'TZS';
                            $deposit = $get('deposit_amount');
                            $start = $get('start_date');
                            $end = $get('end_date');
                            $cycle = $get('billing_cycle');
                            $cycleMonths = $get('billing_cycle_months');

                            $cycleLabel = match ($cycle) {
                                'monthly' => 'Monthly',
                                'quarterly' => 'Every 3 months',
                                'semi_annual' => 'Every 6 months',
                                'annual' => 'Yearly',
                                'custom' => 'Every '.($cycleMonths ?? '?').' months',
                                default => ucfirst((string) $cycle),
                            };

                            $renterLabel = $renter ? $renter->display_name : '—';
                            $unitLabel = $unit
                                ? (($unit->property->name ?? 'Unknown property').' — '.$unit->code)
                                : '—';

                            $lines = [
                                'Renter: '.$renterLabel,
                                'Unit: '.$unitLabel,
                                'Period: '.($start ?? '—').' → '.($end ?: 'open-ended'),
                                'Rent: '.$currency.' '.number_format((float) $rent, 0, '.', ',').' ('.$cycleLabel.')',
                                'Deposit: '.$currency.' '.number_format((float) ($deposit ?? 0), 0, '.', ','),
                                'Due day: '.($get('payment_due_day') ?? 1).' of each billing period',
                            ];

                            return new HtmlString(
                                '<div class="space-y-1 text-sm">'.
                                collect($lines)->map(fn ($l) => '<div>'.e($l).'</div>')->implode('').
                                '</div>'.
                                '<div class="mt-3 text-xs text-gray-500">'.
                                e('The lease starts in "pending" status. Activate it from the lease page once signed.').
                                '</div>'
                            );
                        }),
                ]),
        ];
    }

    /**
     * Term fields shared between the edit form and the wizard's Terms step.
     *
     * @return array<int, mixed>
     */
    protected static function termsComponents(): array
    {
        return [
            DatePicker::make('start_date')
                ->label('Start date')
                ->required()
                ->displayFormat('d/m/Y')
                ->native(false)
                ->default(now()),

            DatePicker::make('end_date')
                ->label('End date')
                ->displayFormat('d/m/Y')
                ->native(false)
                ->after('start_date')
                ->helperText('Leave empty for an open-ended lease.'),

            TextInput::make('rent_amount')
                ->label('Rent per period')
                ->prefix(fn (Get $get) => $get('currency') ?? 'TZS')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(1)
                ->placeholder('350000')
                ->helperText('Whole shillings.')
                ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : (int) round(((float) $state) * 100))
                ->afterStateHydrated(function (TextInput $component, $state): void {
                    if ($state !== null) {
                        $component->state(((int) $state) / 100);
                    }
                }),

            Select::make('currency')
                ->required()
                ->options(['TZS' => 'TZS', 'USD' => 'USD'])
                ->default('TZS')
                ->live()
                ->native(false),

            TextInput::make('deposit_amount')
                ->label('Deposit (security)')
                ->prefix(fn (Get $get) => $get('currency') ?? 'TZS')
                ->numeric()
                ->minValue(0)
                ->step(1)
                ->default(0)
                ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : (int) round(((float) $state) * 100))
                ->afterStateHydrated(function (TextInput $component, $state): void {
                    if ($state !== null) {
                        $component->state(((int) $state) / 100);
                    }
                }),

            TextInput::make('payment_due_day')
                ->label('Payment due day')
                ->numeric()
                ->minValue(1)
                ->maxValue(28)
                ->default(1)
                ->required()
                ->helperText('Day of each billing period invoices fall due (1–28).'),

            Select::make('billing_cycle')
                ->label('Billing cycle')
                ->required()
                ->options([
                    Lease::BILLING_MONTHLY => 'Monthly',
                    Lease::BILLING_QUARTERLY => 'Every 3 months (Quarterly)',
                    Lease::BILLING_SEMI_ANNUAL => 'Every 6 months',
                    Lease::BILLING_ANNUAL => 'Yearly',
                    Lease::BILLING_CUSTOM => 'Custom — pick the months',
                ])
                ->default(Lease::BILLING_MONTHLY)
                ->live()
                ->native(false),

            TextInput::make('billing_cycle_months')
                ->label('Custom cycle (months)')
                ->placeholder('e.g. 9')
                ->numeric()
                ->minValue(1)
                ->maxValue(60)
                ->step(1)
                ->required(fn (Get $get) => $get('billing_cycle') === Lease::BILLING_CUSTOM)
                ->visible(fn (Get $get) => $get('billing_cycle') === Lease::BILLING_CUSTOM)
                ->columnSpanFull(),

            Textarea::make('terms_notes')
                ->label('Notes')
                ->rows(3)
                ->placeholder('Any clauses, conditions, or notes worth recording.')
                ->columnSpanFull(),
        ];
    }
}
