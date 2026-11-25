<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Contractors\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Contractors\ContractorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContractor extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = ContractorResource::class;
}
