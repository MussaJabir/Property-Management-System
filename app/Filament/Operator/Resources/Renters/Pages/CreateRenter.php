<?php

namespace App\Filament\Operator\Resources\Renters\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\Unit;
use Filament\Resources\Pages\CreateRecord;

/**
 * A new renter usually needs a unit next, so saving opens a new lease with
 * them already chosen, when there is a vacant unit to give them.
 */
class CreateRenter extends CreateRecord
{
    use RedirectsToIndex {
        getRedirectUrl as indexUrl;
    }

    protected static string $resource = RenterResource::class;

    protected function getRedirectUrl(): string
    {
        return LeaseResource::canCreate() && Unit::query()->vacant()->longTerm()->exists()
            ? LeaseResource::getUrl('create', ['renter' => $this->getRecord()->getKey()])
            : $this->indexUrl();
    }
}
