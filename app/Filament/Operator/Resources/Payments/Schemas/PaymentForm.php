<?php

namespace App\Filament\Operator\Resources\Payments\Schemas;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Which invoice?')
                    ->columns(2)
                    ->components([
                        Select::make('invoice_id')
                            ->label('Invoice')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn () => Invoice::query()
                                ->outstanding()
                                ->with(['lease.renter'])
                                ->get()
                                ->mapWithKeys(function (Invoice $inv): array {
                                    $renterModel = $inv->lease?->renter;
                                    $renter = $renterModel ? $renterModel->display_name : '—';
                                    $number = $inv->invoice_number ?? 'draft';
                                    $balance = $inv->formatted_balance;

                                    return [$inv->id => "{$number} · {$renter} · balance {$balance}"];
                                }))
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }
                                $invoice = Invoice::find($state);
                                if (! $invoice) {
                                    return;
                                }

                                $set('currency', $invoice->currency);
                                // Pre-fill the outstanding balance — operator can override.
                                if (! $get('amount')) {
                                    $set('amount', (int) ($invoice->balanceDue() / 100));
                                }
                            }),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn (Get $get) => $get('currency') ?? 'TZS')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->step(1)
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
                            ->native(false),

                        DatePicker::make('payment_date')
                            ->required()
                            ->default(now())
                            ->displayFormat('d/m/Y')
                            ->native(false),
                    ]),

                Section::make('Method')
                    ->columns(2)
                    ->components([
                        Select::make('method')
                            ->required()
                            ->options([
                                Payment::METHOD_CASH => 'Cash',
                                Payment::METHOD_BANK_TRANSFER => 'Bank transfer',
                                Payment::METHOD_MOBILE_MONEY => 'Mobile money',
                                Payment::METHOD_CHEQUE => 'Cheque',
                                Payment::METHOD_CARD => 'Card',
                            ])
                            ->default(Payment::METHOD_CASH)
                            ->live()
                            ->native(false),

                        Select::make('mobile_money_provider')
                            ->label('Mobile money provider')
                            ->options([
                                Payment::PROVIDER_MPESA => 'M-Pesa',
                                Payment::PROVIDER_TIGOPESA => 'Tigo Pesa',
                                Payment::PROVIDER_AIRTELMONEY => 'Airtel Money',
                                Payment::PROVIDER_HALOPESA => 'Halo Pesa',
                            ])
                            ->visible(fn (Get $get) => $get('method') === Payment::METHOD_MOBILE_MONEY)
                            ->required(fn (Get $get) => $get('method') === Payment::METHOD_MOBILE_MONEY)
                            ->native(false),

                        TextInput::make('reference_number')
                            ->label('Reference / cheque no.')
                            ->maxLength(60),

                        TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->visible(fn (Get $get) => $get('method') === Payment::METHOD_MOBILE_MONEY)
                            ->maxLength(60),

                        Select::make('status')
                            ->required()
                            ->options([
                                Payment::STATUS_PENDING => 'Pending',
                                Payment::STATUS_COMPLETED => 'Completed',
                                Payment::STATUS_FAILED => 'Failed',
                                Payment::STATUS_REFUNDED => 'Refunded',
                            ])
                            ->default(Payment::STATUS_COMPLETED)
                            ->helperText('Completed payments issue a receipt automatically.')
                            ->native(false),
                    ]),

                Section::make('Notes')
                    ->collapsed()
                    ->components([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(2),
                    ]),
            ]);
    }
}
