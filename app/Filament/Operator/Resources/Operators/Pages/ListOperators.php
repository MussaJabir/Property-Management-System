<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Pages;

use App\Filament\Operator\Resources\Operators\OperatorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperators extends ListRecords
{
    protected static string $resource = OperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Invite member'),
        ];
    }
}
