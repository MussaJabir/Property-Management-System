<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Contractors\Schemas;

use App\Models\MaintenanceRequest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContractorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('Contact name'))
                    ->required()
                    ->maxLength(150)
                    ->placeholder(__('e.g. Juma Hassan')),

                TextInput::make('company')
                    ->label(__('Company'))
                    ->maxLength(150)
                    ->placeholder(__('Optional')),

                Select::make('trade')
                    ->label(__('Main work'))
                    ->options(MaintenanceRequest::CATEGORIES)
                    ->native(false)
                    ->placeholder(__('Any')),

                TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel()
                    ->maxLength(20)
                    ->placeholder('+255712345678 or 0712345678'),

                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->maxLength(150)
                    ->placeholder(__('Optional')),

                Toggle::make('is_active')
                    ->label(__('Available for new jobs'))
                    ->default(true)
                    ->inline(false),

                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
