<?php

declare(strict_types=1);

namespace App\Filament\Operator\Actions;

use App\Filament\Operator\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Renter;
use App\Services\Payments\PaymentAllocator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * "Record payment": who paid, how much, and how it was paid. A renter's
 * payment is shared across their unpaid invoices, oldest first (editable),
 * and any money left over is kept as credit for their next invoice. Saves a
 * completed payment, which issues the receipt (PaymentObserver), and offers
 * the receipt PDF straight away.
 *
 * Works in three places:
 *  - on an invoice row (record = Invoice), or with an `invoice` argument
 *    (Today's overdue list): that invoice first; extra money goes to the
 *    renter's other unpaid invoices, then credit
 *  - on a renter page (record = Renter): that renter
 *  - anywhere else (Today header): pick the renter (or a guest's invoice)
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
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('Save payment'))
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('recordAnother', arguments: ['another' => true])
                    ->label(__('Save & record another'))
                    ->color('gray'),
            ])
            ->fillForm(fn (?Model $record, array $arguments): array => self::defaults($record, $arguments))
            ->schema(fn (?Model $record, array $arguments): array => [
                Select::make('payer')
                    ->label(__('Who paid'))
                    ->hidden(fn (): bool => self::fixedInvoice($record, $arguments) !== null || $record instanceof Renter)
                    ->required(fn (): bool => self::fixedInvoice($record, $arguments) === null && ! $record instanceof Renter)
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->options(fn (): array => self::payerOptions())
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        foreach (self::stateFor($state) as $key => $value) {
                            $set($key, $value);
                        }
                    }),
                Hidden::make('currency'),
                TextInput::make('amount')
                    ->label(__('Amount received'))
                    ->prefix(fn (Get $get): string => (string) ($get('currency') ?: 'TZS'))
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->step(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set) use ($record, $arguments): void {
                        if (self::fixedInvoice($record, $arguments) === null) {
                            $renterId = $record instanceof Renter ? (string) $record->getKey() : self::renterId($get('payer'));
                            $set('split', self::split($renterId, (int) round(((float) $get('amount')) * 100)));
                        }
                    })
                    ->autofocus(),
                Repeater::make('split')
                    ->label(__('Where the money goes'))
                    ->helperText(__('Oldest first. Change an amount if the tenant asked you to pay something else.'))
                    ->visible(fn (Get $get): bool => self::fixedInvoice($record, $arguments) === null && filled($get('split')))
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->schema([
                        Hidden::make('invoice_id'),
                        Hidden::make('owed'),
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            View::make('filament.operator.payments.split-label')
                                ->viewData(fn (Get $get): array => ['label' => $get('label'), 'owed' => $get('owed'), 'currency' => $get('../../currency')])
                                ->columnSpan(['sm' => 2]),
                            TextInput::make('pay')
                                ->hiddenLabel()
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(fn (Get $get): float => (float) $get('owed'))
                                ->live(onBlur: true),
                        ]),
                        Hidden::make('label'),
                    ]),
                View::make('filament.operator.payments.split-summary')
                    ->viewData(fn (Get $get): array => self::summary($get, $record, $arguments)),
                ToggleButtons::make('channel')
                    ->label(__('Paid by'))
                    ->options(self::channels())
                    ->inline()
                    ->required()
                    ->live(),
                Grid::make(2)->schema([
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
                ]),
            ])
            ->action(function (array $data, array $arguments, ?Model $record, Action $action, Schema $schema, Component $livewire): void {
                $amount = (int) round(((float) $data['amount']) * 100);
                $invoice = self::fixedInvoice($record, $arguments);
                $renterId = $record instanceof Renter ? (string) $record->getKey() : self::renterId($data['payer'] ?? null);
                $plan = null;

                if ($invoice === null && $renterId === null) {
                    // A guest's invoice (front desk stays): pay that invoice.
                    $invoice = self::invoiceFromPayer($data['payer'] ?? null);
                }

                if ($invoice === null && $renterId === null) {
                    Notification::make()->danger()->title(__('That invoice is no longer open'))->send();
                    $action->halt();

                    return;
                }

                // From here on, no invoice means a renter's payment.
                if ($invoice === null && filled($data['split'] ?? null)) {
                    // Only this payer's own unpaid invoices, whatever the form sent.
                    $own = self::openFor((string) $renterId)->pluck('id')->map(fn ($id): string => (string) $id)->all();
                    $plan = collect($data['split'])
                        ->mapWithKeys(fn (array $row): array => [(string) $row['invoice_id'] => (int) round(((float) ($row['pay'] ?? 0)) * 100)])
                        ->filter(fn (int $minor, string $invoiceId): bool => $minor > 0 && in_array($invoiceId, $own, true))
                        ->all();

                    if (array_sum($plan) > $amount) {
                        Notification::make()->danger()->title(__('The shares add up to more than the amount received'))->send();
                        $action->halt();

                        return;
                    }
                }

                // Without a plan the money goes oldest first; that invoice is the payment's main one.
                if ($invoice === null && $plan === null) {
                    $invoice = self::openFor((string) $renterId)->first();
                }

                $payment = self::record(
                    $invoice,
                    $amount,
                    (string) $data['channel'],
                    $data['reference'] ?? null,
                    (string) $data['payment_date'],
                    renterId: $renterId,
                    plan: $plan,
                    currency: $data['currency'] ?? null,
                );

                self::notify($payment);

                // Let the dashboard cards and lists refresh (RefreshesAfterPayment).
                $livewire->dispatch('pms-payment-recorded');

                if ($arguments['another'] ?? false) {
                    // Keep the modal open for the next one.
                    $schema->fill(self::defaults($record, $arguments));
                    $action->halt();
                }
            });
    }

    /**
     * Save a completed payment (which issues the receipt). With an invoice it
     * pays that invoice first; with a plan (invoice id => minor units) it is
     * shared as planned; otherwise oldest first. What is left is credit.
     * $channel is a key of channels(): mobile networks are stored as method
     * mobile_money + provider. Also used by the front desk.
     *
     * @param  array<string, int>|null  $plan
     */
    public static function record(?Invoice $invoice, int $amount, string $channel, ?string $reference = null, ?string $date = null, ?string $renterId = null, ?array $plan = null, ?string $currency = null): Payment
    {
        $isMobile = isset(Payment::providerLabels()[$channel]);

        $payment = new Payment([
            'invoice_id' => $invoice !== null ? $invoice->id : ($plan !== null ? array_key_first($plan) : null),
            'renter_id' => $renterId ?? $invoice?->lease?->renter_id,
            'amount' => $amount,
            'currency' => $invoice !== null ? $invoice->currency : ($currency ?: 'TZS'),
            'payment_date' => $date ?? today()->toDateString(),
            'method' => $isMobile ? Payment::METHOD_MOBILE_MONEY : $channel,
            'mobile_money_provider' => $isMobile ? $channel : null,
            'transaction_id' => $isMobile ? $reference : null,
            'reference_number' => $isMobile ? null : $reference,
            'received_by_user_id' => auth()->id(),
            'status' => Payment::STATUS_COMPLETED,
        ]);
        $payment->allocationPlan = $plan;
        $payment->save();

        return $payment;
    }

    /**
     * Rows of the split: the renter's unpaid invoices oldest first, with the
     * amount shared out of $amount (minor units). Amounts are whole money.
     *
     * @return list<array{invoice_id: string, label: string, owed: float, pay: float}>
     */
    public static function split(?string $renterId, int $amount): array
    {
        if ($renterId === null) {
            return [];
        }

        $allocator = app(PaymentAllocator::class);
        $left = max(0, $amount);

        return self::openFor($renterId)->map(function (Invoice $invoice) use ($allocator, &$left): array {
            $owed = $allocator->owed($invoice);
            $pay = min($owed, $left);
            $left -= $pay;

            return [
                'invoice_id' => (string) $invoice->id,
                'label' => collect([$invoice->invoice_number ?? __('Draft invoice'), $invoice->unit_code, __('due :date', ['date' => $invoice->due_date?->format('d/m/Y')])])->filter()->implode(' · '),
                'owed' => $owed / 100,
                'pay' => $pay / 100,
            ];
        })->values()->all();
    }

    /** @return Collection<int, Invoice> */
    private static function openFor(string $renterId): Collection
    {
        return Invoice::query()
            ->outstanding()
            ->whereHas('lease', fn (Builder $q) => $q->where('renter_id', $renterId))
            ->with('lease.unit')
            ->orderBy('due_date')
            ->orderBy('issued_at')
            ->get();
    }

    /**
     * Renters who owe (and their total), plus guests' open invoices.
     *
     * @return array<string, string>
     */
    private static function payerOptions(): array
    {
        $allocator = app(PaymentAllocator::class);
        $open = Invoice::query()->outstanding()->with(['lease.renter', 'booking.guest', 'booking.unit'])->get();

        $renters = $open->filter(fn (Invoice $i): bool => $i->lease?->renter !== null)
            ->groupBy(fn (Invoice $i): string => (string) $i->lease?->renter_id)
            ->mapWithKeys(function ($invoices, string $renterId) use ($allocator): array {
                /** @var Invoice $first */
                $first = $invoices->first();
                $owed = $invoices->sum(fn (Invoice $i): int => $allocator->owed($i));

                return ['renter:'.$renterId => __(':name · owes :amount on :count', [
                    'name' => $first->bill_to_name,
                    'amount' => $first->currency.' '.number_format($owed / 100, 0, '.', ','),
                    'count' => trans_choice(':count invoice|:count invoices', $invoices->count()),
                ])];
            });

        $guests = $open->filter(fn (Invoice $i): bool => $i->lease_id === null)
            ->mapWithKeys(fn (Invoice $i): array => ['invoice:'.$i->id => self::describe($i)]);

        return $renters->sortBy(fn (string $label): string => $label)->merge($guests)->all();
    }

    /**
     * Form values for a chosen payer.
     *
     * @return array<string, mixed>
     */
    private static function stateFor(?string $payer): array
    {
        $renterId = self::renterId($payer);
        if ($renterId !== null) {
            $invoices = self::openFor($renterId);
            $owed = $invoices->sum(fn (Invoice $i): int => app(PaymentAllocator::class)->owed($i));

            return [
                'amount' => $owed > 0 ? (int) round($owed / 100) : null,
                'currency' => $invoices->first()->currency ?? 'TZS',
                'split' => self::split($renterId, $owed),
            ];
        }

        $invoice = self::invoiceFromPayer($payer);

        return [
            'amount' => $invoice !== null ? (int) round($invoice->balanceDue() / 100) : null,
            'currency' => $invoice !== null ? $invoice->currency : 'TZS',
            'split' => [],
        ];
    }

    /**
     * Totals under the form: received, paid on invoices, left over as credit.
     *
     * @return array<string, mixed>
     */
    private static function summary(Get $get, ?Model $record, array $arguments): array
    {
        $amount = (int) round(((float) $get('amount')) * 100);
        $currency = (string) ($get('currency') ?: 'TZS');
        $fixed = self::fixedInvoice($record, $arguments);

        if ($fixed !== null) {
            // That invoice first, then the renter's others, oldest first.
            $renterId = $fixed->lease?->renter_id;
            $owed = app(PaymentAllocator::class)->owed($fixed)
                + ($renterId !== null ? self::openFor($renterId)->reject(fn (Invoice $i): bool => $i->is($fixed))->sum(fn (Invoice $i): int => app(PaymentAllocator::class)->owed($i)) : 0);
            $onInvoices = min($amount, $owed);
            $hasRenter = $renterId !== null;
        } else {
            $onInvoices = (int) collect($get('split') ?? [])->sum(fn (array $row): int => (int) round(((float) ($row['pay'] ?? 0)) * 100));
            $hasRenter = self::renterId($get('payer')) !== null || $record instanceof Renter;
        }

        return [
            'currency' => $currency,
            'received' => $amount,
            'onInvoices' => $onInvoices,
            'credit' => $amount - $onInvoices,
            'hasRenter' => $hasRenter,
            'show' => $amount > 0,
        ];
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

    private static function renterId(?string $payer): ?string
    {
        $id = is_string($payer) && str_starts_with($payer, 'renter:') ? substr($payer, 7) : '';

        return $id !== '' ? $id : null;
    }

    private static function invoiceFromPayer(?string $payer): ?Invoice
    {
        $id = is_string($payer) && str_starts_with($payer, 'invoice:') ? substr($payer, 8) : '';

        return $id !== '' ? Invoice::query()->outstanding()->find($id) : null;
    }

    /** @return array<string, mixed> */
    private static function defaults(?Model $record, array $arguments): array
    {
        $base = [
            'payer' => null,
            'amount' => null,
            'currency' => 'TZS',
            'split' => [],
            'channel' => Payment::METHOD_CASH,
            'reference' => null,
            'payment_date' => today()->toDateString(),
        ];

        $invoice = self::fixedInvoice($record, $arguments);
        if ($invoice !== null) {
            return [...$base, 'amount' => (int) round($invoice->balanceDue() / 100), 'currency' => $invoice->currency];
        }

        if ($record instanceof Renter) {
            return [...$base, 'payer' => 'renter:'.$record->getKey(), ...self::stateFor('renter:'.$record->getKey())];
        }

        return $base;
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

    private static function notify(Payment $payment): void
    {
        // Issued by PaymentObserver when the payment was created.
        $receipt = $payment->receipt;
        $credit = $payment->fresh()?->unappliedAmount() ?? 0;
        $name = $payment->renter !== null ? $payment->renter->display_name : ($payment->invoice !== null ? $payment->invoice->bill_to_name : '');

        Notification::make()
            ->success()
            ->title(__('Payment recorded'))
            ->body(collect([
                __(':amount from :renter', ['amount' => $payment->formatted_amount, 'renter' => $name]),
                $credit > 0 ? __(':amount kept as credit', ['amount' => $payment->currency.' '.number_format($credit / 100, 0, '.', ',')]) : null,
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
