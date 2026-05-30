<?php

namespace App\Filament\Operator\Resources\ExpenseCategories\Pages;

use App\Filament\Operator\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpenseCategory extends EditRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
