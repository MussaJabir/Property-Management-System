<?php

namespace App\Filament\Operator\Resources\ExpenseCategories;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\ExpenseCategories\Pages\CreateExpenseCategory;
use App\Filament\Operator\Resources\ExpenseCategories\Pages\EditExpenseCategory;
use App\Filament\Operator\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Operator\Resources\ExpenseCategories\Schemas\ExpenseCategoryForm;
use App\Filament\Operator\Resources\ExpenseCategories\Tables\ExpenseCategoriesTable;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Expense categories';

    protected static ?string $modelLabel = 'Expense category';

    protected static ?string $pluralModelLabel = 'Expense categories';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Properties;

    protected static ?int $navigationSort = 60;

    /** Kept out of the menu to keep it short. Reached from Expenses ("Categories" button). */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return ExpenseCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseCategories::route('/'),
            'create' => CreateExpenseCategory::route('/create'),
            'edit' => EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
