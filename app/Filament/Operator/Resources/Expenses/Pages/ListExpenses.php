<?php

namespace App\Filament\Operator\Resources\Expenses\Pages;

use App\Filament\Operator\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Operator\Resources\Expenses\ExpenseResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')
                ->label(__('Categories'))
                ->icon(Heroicon::OutlinedTag)
                ->color('gray')
                ->visible(fn (): bool => ExpenseCategoryResource::canAccess())
                ->url(fn (): string => ExpenseCategoryResource::getUrl()),
            CreateAction::make()->label('Record expense'),
        ];
    }
}
