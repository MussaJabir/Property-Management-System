<?php

namespace App\Filament\Operator\Resources\Payments\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = PaymentResource::class;

    /**
     * "Record payment" links from Today pass ?invoice=<id>: start with that
     * invoice chosen and its balance as the amount (tenant-scoped lookup).
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $invoiceId = request()->query('invoice');
        $invoice = is_string($invoiceId) ? Invoice::query()->outstanding()->find($invoiceId) : null;

        if ($invoice instanceof Invoice) {
            $this->form->fill([
                ...$this->form->getRawState(),
                'invoice_id' => $invoice->id,
                'currency' => $invoice->currency,
                'amount' => $invoice->balanceDue(),
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Stamp who recorded it. tenant_id is auto-filled by the
        // TenantScopedModel trait from the active tenant context.
        $data['received_by_user_id'] = auth()->id();

        return $data;
    }
}
