<?php

namespace App\Filament\Operator\Resources\Renters\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Renters\RenterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRenter extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = RenterResource::class;
}
