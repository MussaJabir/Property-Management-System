<?php

namespace App\Filament\Operator\Resources\Expenses\Pages;

use App\Filament\Operator\Resources\Expenses\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by_user_id'] = auth()->id();

        return $data;
    }
}
