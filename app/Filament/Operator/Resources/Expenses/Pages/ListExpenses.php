<?php

namespace App\Filament\Operator\Resources\Expenses\Pages;

use App\Filament\Operator\Resources\Expenses\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Record expense'),
        ];
    }
}
