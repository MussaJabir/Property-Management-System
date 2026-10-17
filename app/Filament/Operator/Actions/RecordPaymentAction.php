<?php

declare(strict_types=1);

namespace App\Filament\Operator\Actions;

use App\Filament\Operator\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Renter;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * One-step "Record payment": a small modal with the amount already filled in
 * and the method as one tap (Cash, M-Pesa, Mixx by Yas, Airtel Money…).
 * Saves a completed payment, which issues the receipt (PaymentObserver), and
 * offers the receipt PDF straight away.
 *
 * Works in three places:
 *  - on an invoice row (record = Invoice): that invoice, no picker
 *  - with an `invoice` argument (Today's overdue list): that invoice
 *  - anywhere else (Today header, renter page): pick the invoice; on a
 *    renter page (record = Renter) only that renter's open invoices
 */
class RecordPaymentAction
{
    /** Method buttons: mobile networks first-class, stored as method + provider. */
    public static function channels(): array
    {
        return [
            Payment::METHOD_CASH => __('Cash'),
            ...Payment::providerLabels(),
            Payment::METHOD_BANK_TRANSFER => __('Bank'),
            Payment::METHOD_CHEQUE => __('Cheque'),
            Payment::METHOD_CARD => __('Card'),
        ];
    }

    public static function make(string $name = 'recordPayment'): Action
    {
        return Action::make($name)
            ->label(__('Record payment'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->authorize(fn (): bool => PaymentResource::canCreate())
            ->modalHeading(__('Record payment'))
            ->modalDescription(fn (?Model $record, array $arguments): ?string => ($invoice = self::fixedInvoice($record, $arguments)) ? self::describe($invoice) : null)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Save payment'))
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('recordAnother', arguments: ['another' => true])
                    ->label(__('Save & record another'))
                    ->color('gray'),
            ])
            ->fillForm(fn (?Model $record, array $arguments): array => self::defaults(self::fixedInvoice($record, $arguments) ?? self::onlyOpenInvoice($record)))
            ->schema(fn (?Model $record, array $arguments): array => [
                Select::make('invoice_id')
                    ->label(__('Invoice'))
                    ->hidden(fn (): bool => self::fixedInvoice($record, $arguments) !== null)
                    ->required(fn (): bool => self::fixedInvoice($record, $arguments) === null)
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->options(fn (): array => self::openInvoices($record)
                        ->with(['lease.renter', 'lease.unit', 'booking.guest', 'booking.unit'])
                        ->orderBy('due_date')
                        ->get()
                        ->mapWithKeys(fn (Invoice $invoice): array => [$invoice->id => self::describe($invoice)])
                        ->all())
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $invoice = $state !== null ? Invoice::query()->outstanding()->find($state) : null;
                        $set('amount', $invoice !== null ? (int) round($invoice->balanceDue() / 100) : null);
                        $set('currency', $invoice !== null ? $invoice->currency : 'TZS');
                    }),

                Hidden::make('currency'),

                TextInput::make('amount')
                    ->label(__('Amount received'))
                    ->prefix(fn (Get $get): string => (string) ($get('currency') ?: 'TZS'))
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->step(1)
                    ->autofocus(),

                ToggleButtons::make('channel')
                    ->label(__('Paid by'))
                    ->options(self::channels())
                    ->inline()
                    ->required()
                    ->live(),

                TextInput::make('reference')
                    ->label(fn (Get $get): string => isset(Payment::providerLabels()[(string) $get('channel')])
                        ? __('Transaction ID')
                        : __('Reference'))
                    ->placeholder(__('Optional'))
                    ->maxLength(60)
                    ->hidden(fn (Get $get): bool => $get('channel') === Payment::METHOD_CASH),

                DatePicker::make('payment_date')
                    ->label(__('Date received'))
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(fn (): Carbon => today()->endOfDay()),
            ])
            ->action(function (array $data, array $arguments, ?Model $record, Action $action, Schema $schema, Component $livewire): void {
                $invoice = self::fixedInvoice($record, $arguments)
                    ?? self::openInvoices($record)->find($data['invoice_id'] ?? null);

                if (! $invoice instanceof Invoice) {
                    Notification::make()->danger()->title(__('That invoice is no longer open'))->send();
                    $action->halt();

                    return;
                }

                $payment = self::record(
                    $invoice,
                    (int) round(((float) $data['amount']) * 100),
                    (string) $data['channel'],
                    $data['reference'] ?? null,
                    (string) $data['payment_date'],
                );

                self::notify($payment, $invoice);
                // Let the dashboard cards and lists refresh (RefreshesAfterPayment).
                $livewire->dispatch('pms-payment-recorded');

                if ($arguments['another'] ?? false) {
                    // Keep the modal open for the next one. On an invoice
                    // row that is the same invoice with its new balance.
                    $schema->fill(self::defaults(self::fixedInvoice($record, $arguments)));
                    $action->halt();
                }
            });
    }

    /**
     * Save a completed payment against $invoice (which issues the receipt).
     * $channel is a key of channels(): mobile networks are stored as
     * method mobile_money + provider. Also used by the front desk.
     */
    public static function record(Invoice $invoice, int $amount, string $channel, ?string $reference = null, ?string $date = null): Payment
    {
        $isMobile = isset(Payment::providerLabels()[$channel]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'currency' => $invoice->currency,
            'payment_date' => $date ?? today()->toDateString(),
            'method' => $isMobile ? Payment::METHOD_MOBILE_MONEY : $channel,
            'mobile_money_provider' => $isMobile ? $channel : null,
            'transaction_id' => $isMobile ? $reference : null,
            'reference_number' => $isMobile ? null : $reference,
            'received_by_user_id' => auth()->id(),
            'status' => Payment::STATUS_COMPLETED,
        ]);
    }

    /** The invoice this action is tied to, if any (and still open). */
    private static function fixedInvoice(?Model $record, array $arguments): ?Invoice
    {
        if ($record instanceof Invoice) {
            return $record->isOutstanding() ? $record->fresh() : null;
        }

        $id = $arguments['invoice'] ?? null;

        return is_string($id) ? Invoice::query()->outstanding()->find($id) : null;
    }

    /** On a renter page with a single open invoice, start with it chosen. */
    private static function onlyOpenInvoice(?Model $record): ?Invoice
    {
        if (! $record instanceof Renter) {
            return null;
        }

        $open = self::openInvoices($record)->limit(2)->get();

        return $open->count() === 1 ? $open->first() : null;
    }

    /** @return Builder<Invoice> */
    private static function openInvoices(?Model $record): Builder
    {
        return Invoice::query()
            ->outstanding()
            ->when($record instanceof Renter, fn (Builder $q) => $q->whereHas('lease', fn (Builder $l) => $l->where('renter_id', $record?->getKey())));
    }

    /** @return array<string, mixed> */
    private static function defaults(?Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice?->id,
            'amount' => $invoice !== null ? (int) round($invoice->balanceDue() / 100) : null,
            'currency' => $invoice !== null ? $invoice->currency : 'TZS',
            'channel' => Payment::METHOD_CASH,
            'reference' => null,
            'payment_date' => today()->toDateString(),
        ];
    }

    /** "INV-2026-000012 · Hassan Omar · A4 · balance TZS 750,000" */
    private static function describe(Invoice $invoice): string
    {
        return collect([
            $invoice->invoice_number ?? __('Draft invoice'),
            $invoice->bill_to_name,
            $invoice->unit_code,
            __('balance :amount', ['amount' => $invoice->formatted_balance]),
        ])->filter()->implode(' · ');
    }

    private static function notify(Payment $payment, Invoice $invoice): void
    {
        // Issued by PaymentObserver when the payment was created.
        $receipt = $payment->receipt;

        Notification::make()
            ->success()
            ->title(__('Payment recorded'))
            ->body(collect([
                __(':amount from :renter', [
                    'amount' => $payment->formatted_amount,
                    'renter' => $invoice->bill_to_name,
                ]),
                $receipt !== null ? __('Receipt :number', ['number' => $receipt->receipt_number]) : null,
            ])->filter()->implode(' · '))
            ->actions($receipt !== null ? [
                Action::make('downloadReceipt')
                    ->label(__('Download receipt'))
                    ->button()
                    ->url(route('filament.operator.receipts.pdf', ['receipt' => $receipt->getKey()]), shouldOpenInNewTab: true),
            ] : [])
            ->duration(12000)
            ->send();
    }
}
