<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\BookingRequests\Pages;

use App\Filament\Operator\Resources\BookingRequests\BookingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingRequests extends ListRecords
{
    protected static string $resource = BookingRequestResource::class;
}
