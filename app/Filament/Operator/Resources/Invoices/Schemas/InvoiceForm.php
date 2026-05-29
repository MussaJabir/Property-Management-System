<?php

namespace App\Filament\Operator\Resources\Invoices\Schemas;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice header')
                    ->columns(2)
                    ->components([
                        Select::make('lease_id')
                            ->label('Lease')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn () => Lease::query()
                                ->whereIn('status', [Lease::STATUS_ACTIVE, Lease::STATUS_PENDING])
                                ->with(['renter', 'unit.property'])
                                ->get()
                                ->mapWithKeys(function (Lease $lease): array {
                                    $renter = $lease->renter ? $lease->renter->display_name : '—';
                                    $unit = $lease->unit ? ($lease->unit->property?->name.' / '.$lease->unit->code) : '—';

                                    return [$lease->id => "{$renter} — {$unit}"];
                                }))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }

                                $lease = Lease::find($state);
                                if (! $lease) {
                                    return;
                                }

                                // Snapshot the lease's currency onto the invoice.
                                $set('currency', $lease->currency);

                                // Seed a default rent line if no items have been entered yet.
                                if (empty($get('items'))) {
                                    $unitCode = $lease->unit ? $lease->unit->code : 'unit';
                                    $set('items', [[
                                        'description' => 'Rent — '.$unitCode,
                                        'type' => InvoiceItem::TYPE_RENT,
                                        'quantity' => 1,
                                        'unit_price' => (int) ($lease->rent_amount / 100),
                                    ]]);
                                }
                            }),

                        TextInput::make('invoice_number')
                            ->label('Invoice number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated on Issue')
                            ->helperText('Assigned when you issue the invoice — kept blank while drafting.'),

                        DatePicker::make('billing_period_start')
                            ->label('Period start')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->startOfMonth()),

                        DatePicker::make('billing_period_end')
                            ->label('Period end')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->endOfMonth())
                            ->after('billing_period_start'),

                        DatePicker::make('due_date')
                            ->label('Due date')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->addDays(7)),

                        Select::make('currency')
                            ->required()
                            ->options(['TZS' => 'TZS', 'USD' => 'USD'])
                            ->default('TZS')
                            ->native(false),
                    ]),

                Section::make('Line items')
                    ->description('At least one item is required before you can issue this invoice.')
                    ->components([
                        Repeater::make('items')
                            ->relationship('items')
                            ->hiddenLabel()
                            ->columns(12)
                            ->addActionLabel('Add line')
                            ->minItems(1)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(5),

                                Select::make('type')
                                    ->options([
                                        InvoiceItem::TYPE_RENT => 'Rent',
                                        InvoiceItem::TYPE_UTILITY => 'Utility',
                                        InvoiceItem::TYPE_FEE => 'Fee',
                                        InvoiceItem::TYPE_DEPOSIT => 'Deposit',
                                        InvoiceItem::TYPE_OTHER => 'Other',
                                    ])
                                    ->default(InvoiceItem::TYPE_RENT)
                                    ->native(false)
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->columnSpan(2),

                                TextInput::make('unit_price')
                                    ->label('Unit price')
                                    ->prefix(fn (Get $get) => $get('../../currency') ?? 'TZS')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(1)
                                    ->required()
                                    ->placeholder('350000')
                                    ->columnSpan(3)
                                    ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : (int) round(((float) $state) * 100))
                                    ->afterStateHydrated(function (TextInput $component, $state): void {
                                        if ($state !== null) {
                                            $component->state(((int) $state) / 100);
                                        }
                                    }),
                            ]),
                    ]),

                Section::make('Notes')
                    ->collapsed()
                    ->components([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3)
                            ->placeholder('Internal notes or text to print at the bottom of the invoice.'),
                    ]),
            ]);
    }
}
