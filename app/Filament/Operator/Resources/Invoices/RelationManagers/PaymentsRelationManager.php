<?php

namespace App\Filament\Operator\Resources\Invoices\RelationManagers;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Payments attached to a single invoice. New rows here:
 *   1. auto-fill tenant_id + received_by_user_id
 *   2. trigger Invoice::recomputeStatus() via PaymentObserver (so the parent
 *      invoice status updates without a manual refresh)
 *   3. issue a Receipt automatically when status='completed'
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('Amount')
                    ->prefix(fn () => $this->getOwnerRecord()->currency ?? 'TZS')
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

                DatePicker::make('payment_date')
                    ->required()
                    ->default(now())
                    ->displayFormat('d/m/Y')
                    ->native(false),

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

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_number')
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->sortable(query: fn ($q, string $d) => $q->orderBy('amount', $d)),

                TextColumn::make('method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                TextColumn::make('reference_number')
                    ->placeholder('—'),

                TextColumn::make('receipt.receipt_number')
                    ->label('Receipt')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record payment')
                    ->mutateDataUsing(function (array $data): array {
                        /** @var Invoice $invoice */
                        $invoice = $this->getOwnerRecord();
                        $data['tenant_id'] = $invoice->tenant_id;
                        $data['currency'] = $invoice->currency;
                        $data['received_by_user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
