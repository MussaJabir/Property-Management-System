<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Guests\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Guests\GuestResource;
use Filament\Resources\Pages\EditRecord;

class EditGuest extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = GuestResource::class;
}
