<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Schemas;

use App\Models\Contractor;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Models\User;
use App\Support\Files;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // One column, top to bottom, in the order it is filled in.
            ->columns(1)
            ->components([
                Section::make('Issue details')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('e.g. Kitchen tap leaking'),

                        Select::make('priority')
                            ->required()
                            ->options([
                                MaintenanceRequest::PRIORITY_LOW => 'Low',
                                MaintenanceRequest::PRIORITY_MEDIUM => 'Medium',
                                MaintenanceRequest::PRIORITY_HIGH => 'High',
                                MaintenanceRequest::PRIORITY_URGENT => 'Urgent',
                            ])
                            ->default(MaintenanceRequest::PRIORITY_MEDIUM)
                            ->native(false),

                        Select::make('category')
                            ->label(__('Fault type'))
                            ->options(MaintenanceRequest::CATEGORIES)
                            ->required()
                            ->native(false),

                        TextInput::make('work_order_number')
                            ->label(__('Work order'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?MaintenanceRequest $record): bool => filled($record?->work_order_number)),

                        Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->placeholder('What is the issue? What has been tried?')
                            ->columnSpanFull(),
                    ]),

                Section::make('Location')
                    ->columns(2)
                    ->components([
                        Select::make('unit_id')
                            ->label('Unit')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn () => Unit::query()
                                ->with('property')
                                ->get()
                                ->mapWithKeys(function (Unit $unit): array {
                                    $propertyName = $unit->property ? $unit->property->name : 'Unknown property';

                                    return [$unit->id => $propertyName.' — '.$unit->code];
                                })),
                    ]),

                Section::make('Assignment')
                    ->columns(2)
                    ->components([
                        Select::make('assigned_to_user_id')
                            ->label('Assigned to')
                            // Scope to THIS client's operators. User has no global
                            // tenant scope (it authenticates from the central
                            // context), so filter by tenant_id explicitly or the
                            // list leaks every client's staff.
                            ->options(fn () => User::query()
                                ->where('tenant_id', tenant('id'))
                                ->where('type', 'operator')
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('Leave empty — assign later'),

                        Select::make('contractor_id')
                            ->label(__('Contractor'))
                            ->options(fn (): array => Contractor::query()->active()->orderBy('name')->get()
                                ->mapWithKeys(fn (Contractor $c): array => [$c->id => $c->displayName()])->all())
                            ->searchable()
                            ->placeholder(__('None — done by our staff')),
                    ]),

                Section::make(__('Costs'))
                    ->description(__('Whole shillings. The actual cost is labour plus materials unless you enter it yourself.'))
                    ->columns(2)
                    ->components([
                        self::money('estimated_cost', __('Estimated cost')),
                        self::money('labour_cost', __('Labour')),
                        self::money('materials_cost', __('Materials')),
                        self::money('cost', __('Actual cost')),
                    ]),

                Section::make('Photos')
                    ->description('Photos of the problem, before work starts. Max 5 MB each.')
                    ->components([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->hiddenLabel()
                            ->collection('photos')
                            // Private bucket on B2: previews need short-lived signed links.
                            ->visibility(Files::usesB2() ? 'private' : null)
                            ->multiple()
                            ->reorderable()
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Photos after the work'))
                    ->description(__('Taken when the job is done, to compare with the photos above.'))
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->components([
                        SpatieMediaLibraryFileUpload::make('after_photos')
                            ->hiddenLabel()
                            ->collection('after_photos')
                            ->visibility(Files::usesB2() ? 'private' : null)
                            ->multiple()
                            ->reorderable()
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Money input shown in whole shillings, stored in cents. */
    private static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('TZS')
            ->numeric()
            ->minValue(0)
            ->step(1)
            ->formatStateUsing(fn ($state) => $state === null ? null : (int) round(((int) $state) / 100))
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round(((float) $state) * 100));
    }
}
