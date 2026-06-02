<?php

namespace App\Filament\Operator\Resources\Locations\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Locations\LocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = LocationResource::class;
}
