<?php

namespace App\Filament\Operator\Resources\ExpenseCategories\Pages;

use App\Filament\Operator\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenseCategories extends ListRecords
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
