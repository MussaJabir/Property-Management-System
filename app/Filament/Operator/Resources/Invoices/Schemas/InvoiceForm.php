<?php

namespace App\Filament\Operator\Resources\Invoices\Schemas;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Services\RentInvoiceGenerator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Hand-made invoices: mostly extra charges (water, electricity, repairs),
 * since rent invoices are created automatically. Picking the renter fills
 * the current rent period and its due date by the same rules as the
 * automatic invoices, plus a rent line when that period's rent has not
 * been billed yet. The period dates sit under "More details".
 */
class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // One column, top to bottom, in the order it is filled in.
            ->columns(1)
            ->components([
                Section::make(__('Who pays'))
                    ->columns(2)
                    ->components([
                        Select::make('lease_id')
                            ->label(__('Renter'))
                            ->required(fn (?Invoice $record): bool => $record?->booking_id === null)
                            ->hidden(fn (?Invoice $record): bool => $record?->booking_id !== null)
                            ->searchable()
                            ->preload()
                            ->options(fn () => Lease::query()
                                ->whereIn('status', [Lease::STATUS_ACTIVE, Lease::STATUS_PENDING])
                                ->with(['renter', 'unit.property'])
                                ->get()
                                ->mapWithKeys(function (Lease $lease): array {
                                    $renter = $lease->renter ? $lease->renter->display_name : '—';
                                    $unit = $lease->unit ? ($lease->unit->code.' · '.$lease->unit->property?->name) : '—';

                                    return [$lease->id => "{$renter} — {$unit}"];
                                }))
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::fillFromLease($state, $set, $get)),

                        DatePicker::make('due_date')
                            ->label(__('Due date'))
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->addDays(7)),

                        // Always the lease's currency, set when it is picked.
                        Hidden::make('currency')
                            ->default('TZS'),
                    ]),

                Section::make(__('Charges'))
                    ->components([
                        Repeater::make('items')
                            ->relationship('items')
                            ->hiddenLabel()
                            ->columns(12)
                            ->addActionLabel(__('Add a charge (water, electricity, repairs…)'))
                            ->minItems(1)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->live()
                            ->schema([
                                TextInput::make('description')
                                    ->label(__('What for'))
                                    ->placeholder(__('e.g. Water, October'))
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 6]),

                                Select::make('type')
                                    ->label(__('Type'))
                                    ->options([
                                        InvoiceItem::TYPE_RENT => __('Rent'),
                                        InvoiceItem::TYPE_UTILITY => __('Utility'),
                                        InvoiceItem::TYPE_FEE => __('Fee'),
                                        InvoiceItem::TYPE_DEPOSIT => __('Deposit'),
                                        InvoiceItem::TYPE_OTHER => __('Other'),
                                    ])
                                    ->default(InvoiceItem::TYPE_UTILITY)
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                TextInput::make('quantity')
                                    ->label(__('Qty'))
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 6, 'md' => 1]),

                                TextInput::make('unit_price')
                                    ->label(__('Amount'))
                                    ->prefix(fn (Get $get) => $get('../../currency') ?? 'TZS')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(1)
                                    ->required()
                                    ->placeholder('25000')
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 12, 'md' => 3])
                                    ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : (int) round(((float) $state) * 100))
                                    ->afterStateHydrated(function (TextInput $component, $state): void {
                                        if ($state !== null) {
                                            $component->state(((int) $state) / 100);
                                        }
                                    }),
                            ]),

                        Placeholder::make('total')
                            ->label(__('Total'))
                            ->content(fn (Get $get): string => ($get('currency') ?? 'TZS').' '.number_format(self::total($get('items')), 0, '.', ',')),
                    ]),

                Section::make(__('More details'))
                    ->description(__('Billing period, invoice number and notes.'))
                    ->collapsed()
                    ->columns(2)
                    ->components([
                        DatePicker::make('billing_period_start')
                            ->label(__('Period start'))
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->startOfMonth()),

                        DatePicker::make('billing_period_end')
                            ->label(__('Period end'))
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()->endOfMonth())
                            ->after('billing_period_start'),

                        TextInput::make('invoice_number')
                            ->label(__('Invoice number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder(__('Given when the invoice is issued'))
                            // Always blank on a new invoice.
                            ->hiddenOn('create'),

                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(3)
                            ->placeholder(__('Internal notes or text to print at the bottom of the invoice.'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * The renter was picked: their currency, the lease's current rent
     * period and due date, and a rent line when there are no charges yet.
     */
    private static function fillFromLease(mixed $leaseId, Set $set, Get $get): void
    {
        $lease = is_string($leaseId) ? Lease::with('unit')->find($leaseId) : null;

        if ($lease === null) {
            return;
        }

        $set('currency', $lease->currency);

        $generator = app(RentInvoiceGenerator::class);
        $period = $generator->currentPeriod($lease);

        if ($period !== null) {
            $set('billing_period_start', $period['start']->toDateString());
            $set('billing_period_end', $period['end']->toDateString());
            $set('due_date', $generator->dueDate($lease, $period['start'], $period['end'])->toDateString());
        }

        // Rent is usually billed automatically: only offer a rent line when
        // this period has no invoice yet, so rent is never billed twice.
        $rentBilled = $period !== null && Invoice::query()
            ->where('lease_id', $lease->id)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereDate('billing_period_start', '<=', $period['end']->toDateString())
            ->whereDate('billing_period_end', '>=', $period['start']->toDateString())
            ->whereHas('items', fn ($q) => $q->where('type', InvoiceItem::TYPE_RENT))
            ->exists();

        if (empty($get('items')) && ! $rentBilled) {
            $set('items', [[
                'description' => $period !== null
                    ? __('Rent :unit · :from – :to', [
                        'unit' => $lease->unit !== null ? $lease->unit->code : '',
                        'from' => $period['start']->format('d/m/Y'),
                        'to' => $period['end']->format('d/m/Y'),
                    ])
                    : __('Rent :unit', ['unit' => $lease->unit !== null ? $lease->unit->code : '']),
                'type' => InvoiceItem::TYPE_RENT,
                'quantity' => 1,
                'unit_price' => (int) ($lease->rent_amount / 100),
            ]]);
        }
    }

    /** Sum of the charges as typed (whole shillings). */
    private static function total(mixed $items): float
    {
        return collect(is_array($items) ? $items : [])
            ->sum(fn ($item): float => (is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1)
                * (is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0));
    }
}
