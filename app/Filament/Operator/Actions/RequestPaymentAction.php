<?php

declare(strict_types=1);

namespace App\Filament\Operator\Actions;

use App\Exceptions\PaymentRequestRefused;
use App\Filament\Operator\Resources\Payments\PaymentResource;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\Payments\PaymentRequester;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * "Request payment": sends a mobile money prompt for an invoice's balance
 * to the payer's phone through the workspace's Selcom account. Shown only
 * when Selcom is switched on in Settings → Payments.
 *
 * Works on an invoice row (record = Invoice) or with an `invoice` or
 * `booking` argument (front desk).
 */
class RequestPaymentAction
{
    public static function make(string $name = 'requestPayment'): Action
    {
        return Action::make($name)
            ->label(__('Request payment'))
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('gray')
            ->authorize(fn (): bool => PaymentResource::canCreate())
            ->visible(fn (?Model $record, array $arguments): bool => ($invoice = self::invoice($record, $arguments)) !== null
                && PaymentRequester::canRequest($invoice))
            ->modalHeading(__('Request payment by mobile money'))
            ->modalDescription(fn (?Model $record, array $arguments): ?string => ($invoice = self::invoice($record, $arguments)) !== null
                ? __(':name will get a prompt on their phone to pay :amount. They confirm with their mobile money PIN.', [
                    'name' => $invoice->bill_to_name,
                    'amount' => $invoice->formatted_balance,
                ])
                : null)
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('Send prompt'))
            ->fillForm(fn (?Model $record, array $arguments): array => [
                'phone' => ($invoice = self::invoice($record, $arguments)) !== null ? PaymentRequester::payerPhone($invoice) : null,
            ])
            ->schema([
                TextInput::make('phone')
                    ->label(__('Mobile money number'))
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->helperText(__('M-Pesa, Mixx by Yas, Airtel Money or HaloPesa. 0712… or +255712…')),
            ])
            ->action(function (array $data, ?Model $record, array $arguments, Action $action): void {
                $invoice = self::invoice($record, $arguments);

                if ($invoice === null) {
                    Notification::make()->danger()->title(__('That invoice is no longer open'))->send();

                    return;
                }

                try {
                    $request = app(PaymentRequester::class)->request($invoice, (string) $data['phone'], auth()->id());
                } catch (PaymentRequestRefused $e) {
                    Notification::make()->danger()->title(__('Payment request not sent'))->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Payment prompt sent'))
                    ->body(__('Ask them to check +:phone and enter their PIN. The payment is recorded automatically once Selcom confirms it.', ['phone' => $request->msisdn]))
                    ->duration(12000)
                    ->send();
            });
    }

    private static function invoice(?Model $record, array $arguments): ?Invoice
    {
        if ($record instanceof Invoice) {
            return $record;
        }

        if (is_string($id = $arguments['invoice'] ?? null)) {
            return Invoice::query()->find($id);
        }

        if (is_string($id = $arguments['booking'] ?? null)) {
            return Booking::query()->find($id)?->invoice;
        }

        return null;
    }
}
