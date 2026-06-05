<?php

namespace App\Filament\Operator\Resources\Expenses\Schemas;

use App\Models\ExpenseCategory;
use App\Models\Property;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense')
                    ->columns(2)
                    ->components([
                        Select::make('category_id')
                            ->label('Category')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn () => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id'))
                            ->createOptionForm([
                                TextInput::make('name')->required()->maxLength(50),
                            ])
                            ->createOptionUsing(fn (array $data) => ExpenseCategory::create($data)->id),

                        Select::make('property_id')
                            ->label('Property')
                            ->options(fn () => Property::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('— General overhead —')
                            ->helperText('Leave empty for expenses not tied to a specific property.'),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn (Get $get) => $get('currency') ?? 'TZS')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->step(1)
                            ->helperText('Whole shillings.')
                            ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : (int) round(((float) $state) * 100))
                            ->afterStateHydrated(function (TextInput $component, $state): void {
                                if ($state !== null) {
                                    $component->state(((int) $state) / 100);
                                }
                            }),

                        Select::make('currency')
                            ->required()
                            ->options(['TZS' => 'TZS', 'USD' => 'USD'])
                            ->default('TZS')
                            ->live()
                            ->native(false),

                        DatePicker::make('expense_date')
                            ->label('Expense date')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(now()),

                        Textarea::make('description')
                            ->rows(3)
                            ->placeholder('What was bought, who was paid, anything to remember.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Receipt')
                    ->description('Attach a photo or scan of the receipt if you have one.')
                    ->components([
                        SpatieMediaLibraryFileUpload::make('receipt')
                            ->hiddenLabel()
                            ->collection('receipt')
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
