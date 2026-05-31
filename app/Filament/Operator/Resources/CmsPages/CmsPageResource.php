<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsPages;

use App\Filament\Operator\Resources\CmsPages\Pages\EditCmsPage;
use App\Filament\Operator\Resources\CmsPages\Pages\ListCmsPages;
use App\Filament\Operator\Resources\CmsPages\Schemas\CmsPageForm;
use App\Filament\Operator\Resources\CmsPages\Tables\CmsPagesTable;
use App\Models\CmsPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * CMS Pages — the five fixed public pages (home/about/units/news/contact).
 * Operator edits block content via the Filament Builder field on the Edit
 * page; create/delete are intentionally disabled.
 */
class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $modelLabel = 'Page';

    protected static ?string $pluralModelLabel = 'Pages';

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return CmsPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CmsPagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsPages::route('/'),
            'edit' => EditCmsPage::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
