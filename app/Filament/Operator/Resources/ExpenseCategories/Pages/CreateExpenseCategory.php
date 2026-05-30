<?php

namespace App\Filament\Operator\Resources\ExpenseCategories\Pages;

use App\Filament\Operator\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseCategory extends CreateRecord
{
    protected static string $resource = ExpenseCategoryResource::class;
}
