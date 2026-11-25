<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\RentalApplications\Schemas;

use App\Filament\Operator\Support\DocumentsSection;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RentalApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Applicant'))
                    ->columns(2)
                    ->components([
                        TextInput::make('applicant_name')
                            ->label(__('Full name'))
                            ->required()
                            ->maxLength(150),

                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->placeholder('+255712345678 or 0712345678'),

                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->maxLength(150),

                        TextInput::make('id_number')
                            ->label(__('ID number'))
                            ->maxLength(50),

                        TextInput::make('employer')
                            ->label(__('Employer or business'))
                            ->maxLength(150),

                        TextInput::make('monthly_income')
                            ->label(__('Monthly income'))
                            ->prefix('TZS')
                            ->numeric()
                            ->minValue(0)
                            ->formatStateUsing(fn ($state) => $state === null ? null : (int) round(((int) $state) / 100))
                            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round(((float) $state) * 100)),
                    ]),

                Section::make(__('What they want'))
                    ->columns(2)
                    ->components([
                        Select::make('unit_id')
                            ->label(__('Unit'))
                            ->options(fn (): array => Unit::query()->with('property')->get()
                                ->mapWithKeys(fn (Unit $u): array => [$u->id => $u->property->name.' — '.$u->code])->all())
                            ->searchable()
                            ->placeholder(__('Not decided yet')),

                        DatePicker::make('move_in_on')
                            ->label(__('Wants to move in on'))
                            ->displayFormat('d/m/Y')
                            ->native(false),
                    ]),

                Section::make(__('Screening'))
                    ->components([
                        Textarea::make('screening_notes')
                            ->label(__('Screening notes'))
                            ->rows(4)
                            ->placeholder(__('References called, documents checked, previous landlord, anything else that matters.')),
                    ]),

                DocumentsSection::make(__('ID copy, payslips, bank statements, references. Only staff can open them.')),
            ]);
    }
}
