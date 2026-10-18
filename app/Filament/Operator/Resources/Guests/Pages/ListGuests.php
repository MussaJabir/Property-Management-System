<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Guests\Pages;

use App\Filament\Operator\Resources\Guests\GuestResource;
use Filament\Resources\Pages\ListRecords;

class ListGuests extends ListRecords
{
    protected static string $resource = GuestResource::class;
}
