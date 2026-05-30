<?php

namespace App\Filament\Operator\Resources\ExpenseCategories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('e.g. Landscaping'),

                ColorPicker::make('color')
                    ->label('Color')
                    ->helperText('Used in charts and category badges.'),
            ]);
    }
}
