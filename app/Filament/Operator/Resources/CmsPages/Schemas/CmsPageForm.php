<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsPages\Schemas;

use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Models\CmsPage;
use App\Services\Cms\PageEditor;
use App\Support\Files;
use App\Support\RequestAlerts;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * The Website editor: each page is a few fixed sections in plain words,
 * with a switch to show or hide them. See App\Services\Cms\PageEditor for
 * how the sections are stored.
 */
class CmsPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(fn (?CmsPage $record): array => match ($record?->slug) {
            CmsPage::SLUG_HOME => self::home(),
            CmsPage::SLUG_ABOUT => [self::pageSettings(__('About us')), self::about(false), self::photos(false)],
            CmsPage::SLUG_CONTACT => [self::pageSettings(__('Contact')), self::contact(false)],
            CmsPage::SLUG_NEWS => [self::pageSettings(__('News'))],
            default => [self::pageSettings(__('Page'))],
        })->columns(1);
    }

    /** @return list<Component> */
    private static function home(): array
    {
        return [
            self::banner(),
            Section::make(__('Available rooms'))
                ->description(__('Your vacant rooms show here by themselves, with photos and prices. Nothing to type.'))
                ->afterHeader([self::switch('rooms')])
                ->schema([
                    Select::make('rooms.limit')
                        ->label(__('How many to show'))
                        ->options([3 => __(':n rooms', ['n' => 3]), 6 => __(':n rooms', ['n' => 6]), 9 => __(':n rooms', ['n' => 9]), 12 => __(':n rooms', ['n' => 12])])
                        ->default(6)
                        ->selectablePlaceholder(false)
                        ->extraFieldWrapperAttributes(['class' => 'max-w-xs']),
                ]),
            self::about(true),
            self::photos(true),
            Section::make(__('Latest news'))
                ->description(__('Your 3 newest news posts. Write them under Website → News.'))
                ->afterHeader([self::switch('news')]),
            self::contact(true),
        ];
    }

    private static function banner(): Section
    {
        return Section::make(__('Top banner'))
            ->description(__('The big picture and title at the top of your website.'))
            ->schema([
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Grid::make(1)->schema([
                        TextInput::make('banner.heading')
                            ->label(__('Big title'))
                            ->placeholder(__('e.g. Welcome to :name', ['name' => tenant()->name ?? '']))
                            ->maxLength(120)
                            ->live(onBlur: true),
                        Textarea::make('banner.subheading')
                            ->label(__('Short line under the title'))
                            ->rows(2)
                            ->maxLength(200)
                            ->live(onBlur: true),
                        FileUpload::make('banner.background_image')
                            ->label(__('Banner photo'))
                            ->helperText(__('A wide photo works best. A tall one shows whole beside the title on computers. No photo? We use your brand color.'))
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->disk(Files::publicDisk())
                            ->visibility('public')
                            ->directory('cms/hero')
                            ->maxSize(8192)
                            ->live(),
                        ToggleButtons::make('banner.focus')
                            ->label(__('Photo focus'))
                            ->helperText(__('Which part of the photo stays in view when it is cropped, e.g. Top to keep a sign on the roof.'))
                            ->options(['top' => __('Top'), 'center' => __('Middle'), 'bottom' => __('Bottom')])
                            ->default('center')
                            ->inline()
                            ->grouped()
                            ->live()
                            ->visible(fn (Get $get): bool => filled($get('banner.background_image'))),
                        View::make('filament.operator.website.note')
                            ->viewData(['text' => __('The banner button always says “See rooms” and opens your rooms page. Nothing to set up.')]),
                    ]),
                    View::make('filament.operator.website.banner-preview')
                        ->viewData(fn (Get $get): array => [
                            'heading' => filled($get('banner.heading')) ? $get('banner.heading') : __('Welcome to :name', ['name' => tenant()->name ?? '']),
                            'subheading' => $get('banner.subheading'),
                            'photo' => self::previewUrl($get('banner.background_image')),
                            'position' => match ($get('banner.focus')) {
                                'top' => 'center top',
                                'bottom' => 'center bottom',
                                default => 'center',
                            },
                        ]),
                ]),
            ]);
    }

    private static function about(bool $onHome): Section
    {
        return Section::make(__('About you'))
            ->description(__('A few lines on why people should stay or rent with you.'))
            ->afterHeader($onHome ? [self::switch('about')] : [])
            ->schema([
                TextInput::make('about.heading')
                    ->label(__('Heading'))
                    ->placeholder(__('e.g. Why stay with us'))
                    ->maxLength(120),
                Textarea::make('about.body')
                    ->label(__('Text'))
                    ->rows(5)
                    ->maxLength(3000)
                    ->placeholder(__('e.g. A new building with furnished rooms, 24-hour security and parking. Short stays and business frames available.'))
                    ->helperText(__('Write it the way you would say it to a guest.')),
            ]);
    }

    private static function photos(bool $onHome): Section
    {
        return Section::make(__('Photos'))
            ->description(__('Show off the building, rooms and area. Drag to change the order.'))
            ->afterHeader($onHome ? [self::switch('photos')] : [])
            ->schema([
                FileUpload::make('photos.images')
                    ->hiddenLabel()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->panelLayout('grid')
                    ->maxFiles(PageEditor::MAX_PHOTOS)
                    ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->imageEditor()
                    ->disk(Files::publicDisk())
                    ->visibility('public')
                    ->directory('cms/photos')
                    ->maxSize(8192)
                    ->helperText(__('Pick photos from your phone or computer. Up to :n.', ['n' => PageEditor::MAX_PHOTOS])),
                Hidden::make('photos.links'),
            ]);
    }

    private static function contact(bool $onHome): Section
    {
        $client = tenant();
        $details = collect([
            $client?->contact_phone ? __('Phone and WhatsApp: :phone', ['phone' => RequestAlerts::display($client->contact_phone)]) : null,
            $client?->contact_email ? __('Email: :email', ['email' => $client->contact_email]) : null,
        ])->filter()->implode(' · ');

        return Section::make(__('Contact'))
            ->description(__('Your phone, WhatsApp and email, plus a short form visitors can send you.'))
            ->afterHeader($onHome ? [self::switch('contact')] : [])
            ->schema([
                View::make('filament.operator.website.note')
                    ->viewData([
                        'text' => ($details !== '' ? $details.'. ' : '').__('These come from Settings → Business.'),
                        'link' => WorkspaceSettings::canAccess() ? WorkspaceSettings::getUrl() : null,
                        'linkLabel' => __('Change contact details'),
                    ]),
                TextInput::make('contact.heading')
                    ->label(__('Heading above the form'))
                    ->placeholder(__('Send us a message'))
                    ->maxLength(120),
                TextInput::make('contact.note')
                    ->label(__('Short note under it (optional)'))
                    ->placeholder(__('e.g. We reply within a day.'))
                    ->maxLength(200),
            ]);
    }

    private static function pageSettings(string $name): Section
    {
        return Section::make(__('Page'))
            ->afterHeader([
                Toggle::make('show_page')
                    ->label(__('Show on website'))
                    ->inline(),
            ])
            ->schema([
                TextInput::make('title')
                    ->label(__('Page title'))
                    ->placeholder($name)
                    ->required()
                    ->maxLength(150),
                TextInput::make('subtitle')
                    ->label(__('Short line under the title (optional)'))
                    ->maxLength(200),
            ]);
    }

    private static function switch(string $section): Toggle
    {
        return Toggle::make($section.'.show')
            ->label(__('Show on website'))
            ->inline();
    }

    /** Address of the banner photo for the preview, whether just picked or already saved. */
    private static function previewUrl(mixed $state): ?string
    {
        $file = is_array($state) ? collect($state)->first() : $state;

        try {
            return match (true) {
                $file instanceof TemporaryUploadedFile => $file->isPreviewable() ? $file->temporaryUrl() : null,
                is_string($file) && $file !== '' => Files::publicUrl($file),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }
}
