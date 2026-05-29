<?php

namespace App\Filament\Admin\Resources\Plans\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan details')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create' && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(60)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),

                        TextInput::make('price_tzs')
                            ->label('Price (TZS cents)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Stored in cents. e.g. 4 900 000 = TZS 49,000'),

                        Select::make('billing_period')
                            ->required()
                            ->options([
                                'monthly' => 'Monthly',
                                'annual' => 'Annual',
                            ])
                            ->default('monthly')
                            ->native(false),

                        Toggle::make('is_public')
                            ->label('Public (shown on marketing site)')
                            ->default(true),
                    ]),

                Section::make('Limits')
                    ->description('Leave blank for unlimited.')
                    ->columns(3)
                    ->components([
                        TextInput::make('max_properties')->numeric()->minValue(1),
                        TextInput::make('max_units')->numeric()->minValue(1),
                        TextInput::make('max_operators')->numeric()->minValue(1),
                    ]),

                Section::make('Features')
                    ->description('Free-form key/value pairs surfaced on the pricing page.')
                    ->components([
                        KeyValue::make('features')
                            ->keyLabel('Feature key')
                            ->valueLabel('Value')
                            ->reorderable(),
                    ]),
            ]);
    }
}
