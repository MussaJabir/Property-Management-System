<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsPages\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CmsPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page settings')
                ->schema([
                    TextInput::make('slug')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('title')->required()->maxLength(150),
                    TextInput::make('subtitle')->maxLength(200),
                    DateTimePicker::make('published_at')
                        ->label('Published at')
                        ->helperText('Leave blank to hide this page until it is published.'),
                ])
                ->columns(2),

            Section::make('Content blocks')
                ->description('Drag to reorder. Each block renders independently on the public site.')
                ->schema([
                    Builder::make('blocks')
                        ->label('')
                        ->blocks([
                            Block::make('hero')
                                ->label('Hero banner')
                                ->schema([
                                    TextInput::make('heading')->required(),
                                    Textarea::make('subheading')->rows(2),
                                    TextInput::make('cta_label')->label('Call-to-action label'),
                                    TextInput::make('cta_link')->label('Call-to-action link (e.g. units)'),
                                ]),

                            Block::make('rich_text')
                                ->label('Rich text')
                                ->schema([
                                    TextInput::make('heading'),
                                    Textarea::make('body')->rows(6)->required(),
                                ]),

                            Block::make('image_gallery')
                                ->label('Image gallery')
                                ->schema([
                                    TextInput::make('heading'),
                                    Repeater::make('images')
                                        ->schema([
                                            TextInput::make('url')->required()->url(),
                                            TextInput::make('caption'),
                                        ])
                                        ->columns(2)
                                        ->reorderable(),
                                ]),

                            Block::make('featured_units')
                                ->label('Featured units')
                                ->schema([
                                    TextInput::make('heading')->default('Available now'),
                                    TextInput::make('limit')->numeric()->default(6)->minValue(1)->maxValue(24),
                                ]),

                            Block::make('announcements')
                                ->label('Announcements list')
                                ->schema([
                                    TextInput::make('limit')->numeric()->default(10)->minValue(1)->maxValue(50),
                                ]),

                            Block::make('contact_form')
                                ->label('Contact form')
                                ->schema([
                                    TextInput::make('heading'),
                                    Textarea::make('note')->rows(2),
                                ]),
                        ])
                        ->collapsible()
                        ->cloneable()
                        ->reorderableWithButtons(),
                ]),
        ]);
    }
}
