<?php

namespace App\Filament\Admin\Resources\Plans\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\CheckboxList;
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
                    ->description('Tick what this plan includes. Shown on the pricing page.')
                    ->components([
                        CheckboxList::make('features')
                            ->hiddenLabel()
                            ->options(Plan::FEATURES)
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable(),
                    ]),
            ]);
    }
}
