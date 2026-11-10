<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsPages\Pages;

use App\Filament\Operator\Pages\Website;
use App\Filament\Operator\Resources\CmsPages\CmsPageResource;
use App\Models\CmsPage;
use App\Services\Cms\PageEditor;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Edits one page of the website in fixed, plain-word sections. */
class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;

    /** Save stays in reach at the bottom of the screen on long pages. */
    public static bool $formActionsAreSticky = true;

    public function getTitle(): string|Htmlable
    {
        return self::pageName($this->page()->slug);
    }

    public function getSubheading(): ?string
    {
        return $this->page()->slug === CmsPage::SLUG_HOME
            ? __('What visitors see first at :url', ['url' => preg_replace('#^https?://#', '', Website::siteUrl())])
            : null;
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [Website::getUrl() => __('Website'), self::pageName($this->page()->slug)];
    }

    public static function pageName(string $slug): string
    {
        return match ($slug) {
            CmsPage::SLUG_HOME => __('Home page'),
            CmsPage::SLUG_ABOUT => __('About us'),
            CmsPage::SLUG_NEWS => __('News'),
            CmsPage::SLUG_CONTACT => __('Contact'),
            default => __('Rooms'),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')
                ->label(__('View my website'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(Website::siteUrl($this->page()->slug), shouldOpenInNewTab: true),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PageEditor::toForm($this->page());
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PageEditor::toRecord($this->page(), $data);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('Saved. Your website is updated.');
    }

    private function page(): CmsPage
    {
        /** @var CmsPage $page */
        $page = $this->getRecord();

        return $page;
    }
}
