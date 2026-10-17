<?php

namespace App\Filament\Operator\Resources\Leases\Schemas;

use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Form schema for leases. Powers two surfaces:
 *
 *   - Edit page: configure() returns a flat section layout. Renter and Unit
 *     are read-only after creation (changing them would silently invalidate
 *     invoices, payments, and lease history). Change those via terminating
 *     the lease and creating a new one.
 *   - Create page: createForm() is one screen — unit, renter (added inline
 *     if new), rent, and "Start the lease now"; the rest under More details.
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
     * The create page: one screen. Pick a vacant unit (its rent fills in),
     * pick or add the renter on the spot, and start the lease. Rarely
     * changed terms sit under "More details".
     */
    public static function createForm(Schema $schema): Schema
    {
        $terms = collect(self::termsComponents())->keyBy(fn ($field) => $field->getName());

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Unit and renter'))
                    ->columns(2)
                    ->components([
                        Select::make('unit_id')
                            ->label(__('Vacant unit'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn (): array => Unit::query()
                                ->vacant()
                                ->longTerm()
                                ->with('property')
                                ->get()
                                ->mapWithKeys(fn (Unit $unit): array => [$unit->id => self::unitLabel($unit)])
                                ->all())
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $unit = $state !== null ? Unit::find($state) : null;

                                foreach ($unit !== null ? self::unitDefaults($unit) : [] as $key => $value) {
                                    $set($key, $value);
                                }
                            })
                            ->helperText(__('Rent and billing fill in from the unit.')),

                        Select::make('renter_id')
                            ->label(__('Renter'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => Renter::query()
                                ->orderBy('full_name')
                                ->get()
                                ->mapWithKeys(fn (Renter $renter): array => [$renter->id => $renter->display_name])
                                ->all())
                            ->createOptionForm([
                                TextInput::make('full_name')
                                    ->label(__('Full name'))
                                    ->required()
                                    ->maxLength(150),
                                TextInput::make('phone')
                                    ->label(__('Phone'))
                                    ->tel()
                                    ->required()
                                    ->placeholder('0712345678')
                                    ->maxLength(20),
                                TextInput::make('email')
                                    ->label(__('Email'))
                                    ->email()
                                    ->placeholder(__('Optional'))
                                    ->maxLength(150),
                            ])
                            ->createOptionModalHeading(__('New renter'))
                            ->createOptionUsing(fn (array $data): string => Renter::create([
                                'type' => Renter::TYPE_INDIVIDUAL,
                                'full_name' => $data['full_name'],
                                'phone' => $data['phone'],
                                'email' => $data['email'] ?? null,
                            ])->getKey())
                            ->helperText(__('New renter? Use + to add their name and phone. The rest can wait.')),
                    ]),

                Section::make(__('Rent'))
                    ->columns(3)
                    ->components([
                        $terms['start_date'],
                        $terms['rent_amount'],
                        $terms['deposit_amount'],

                        Toggle::make('activate_now')
                            ->label(__('Start the lease now'))
                            ->default(true)
                            ->dehydrated(false)
                            ->helperText(__('Marks the unit occupied and issues the first invoice. Turn off to keep it pending until it is signed.'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('More details'))
                    ->description(__('End date, billing cycle, due day, currency and notes.'))
                    ->collapsed()
                    ->columns(2)
                    ->components([
                        $terms['end_date'],
                        $terms['payment_due_day'],
                        $terms['billing_cycle'],
                        $terms['currency'],
                        $terms['billing_cycle_months'],
                        $terms['terms_notes'],
                    ]),
            ]);
    }

    /**
     * Form state taken from a unit: its rent (whole shillings) and billing.
     *
     * @return array<string, mixed>
     */
    public static function unitDefaults(Unit $unit): array
    {
        return [
            'rent_amount' => (int) ($unit->rent_amount / 100),
            'currency' => $unit->rent_currency,
            'billing_cycle' => $unit->billing_cycle,
            'billing_cycle_months' => $unit->billing_cycle_months,
        ];
    }

    private static function unitLabel(Unit $unit): string
    {
        return ($unit->property !== null ? $unit->property->name : __('Unknown property')).' — '.$unit->code;
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
