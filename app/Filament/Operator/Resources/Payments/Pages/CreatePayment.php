<?php

namespace App\Filament\Operator\Resources\Payments\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Stamp who recorded it. tenant_id is auto-filled by the
        // TenantScopedModel trait from the active tenant context.
        $data['received_by_user_id'] = auth()->id();

        return $data;
    }
}
