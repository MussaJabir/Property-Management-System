<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\CmsAnnouncements\CmsAnnouncementResource;
use App\Filament\Operator\Resources\CmsPages\CmsPageResource;
use App\Filament\Operator\Resources\CmsPages\Pages\EditCmsPage;
use App\Filament\Operator\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Filament\Operator\Resources\Units\UnitResource;
use App\Models\CmsAnnouncement;
use App\Models\CmsPage;
use App\Models\ContactSubmission;
use App\Models\Unit;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Website overview: the site's address, its pages with what each shows,
 * new messages and the look. Each page opens in the plain-word editor.
 */
class Website extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Website;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'website';

    protected string $view = 'filament.operator.pages.website';

    public static function getNavigationLabel(): string
    {
        return __('Your website');
    }

    public function getTitle(): string
    {
        return __('Your website');
    }

    public function getSubheading(): string
    {
        return __('Everything visitors see about :name, in one place.', ['name' => tenant()->name ?? '']);
    }

    public static function canAccess(): bool
    {
        return CmsPageResource::canViewAny();
    }

    /** Public address of the website, or of one of its pages. */
    public static function siteUrl(string $slug = CmsPage::SLUG_HOME): string
    {
        $base = url('/'.(tenant()?->getKey() ?? ''));

        return $slug === CmsPage::SLUG_HOME ? $base : $base.'/'.$slug;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $client = tenant();
        $pages = CmsPage::query()->get()->keyBy('slug');
        $posts = CmsAnnouncement::query()->published()->count();
        $rooms = Unit::query()->listedPublicly()->count();
        $site = self::siteUrl();

        $editUrl = fn (string $slug): ?string => $pages->has($slug) && CmsPageResource::canEdit($pages[$slug])
            ? EditCmsPage::getUrl(['record' => $pages[$slug]])
            : null;
        $shown = fn (string $slug): bool => (bool) $pages->get($slug)?->isPublished();

        $rows = [
            [
                'name' => __('Home page'), 'icon' => Heroicon::OutlinedHome,
                'about' => __('Banner, rooms, about you, photos, news and contact'),
                'status' => 'shown',
                'links' => [[__('Edit'), $editUrl(CmsPage::SLUG_HOME)]],
            ],
            [
                'name' => __('Rooms'), 'icon' => Heroicon::OutlinedKey,
                'about' => trans_choice(':count room showing. Filled in from your rooms and prices.|:count rooms showing. Filled in from your rooms and prices.', $rooms),
                'status' => 'auto',
                'links' => [[__('Go to rooms'), UnitResource::canViewAny() ? UnitResource::getUrl() : null]],
            ],
            [
                'name' => __('About us'), 'icon' => Heroicon::OutlinedInformationCircle,
                'about' => __('Your story and photos'),
                'status' => $shown(CmsPage::SLUG_ABOUT) ? 'shown' : 'hidden',
                'links' => [[__('Edit'), $editUrl(CmsPage::SLUG_ABOUT)]],
            ],
            [
                'name' => __('News'), 'icon' => Heroicon::OutlinedNewspaper,
                'about' => $posts > 0 ? trans_choice(':count post|:count posts', $posts) : __('No posts yet'),
                'status' => ! $shown(CmsPage::SLUG_NEWS) ? 'hidden' : ($posts > 0 ? 'shown' : 'waiting'),
                'links' => [
                    [__('Write a post'), CmsAnnouncementResource::canCreate() ? CmsAnnouncementResource::getUrl('create') : null],
                    [__('Edit page'), $editUrl(CmsPage::SLUG_NEWS)],
                ],
            ],
            [
                'name' => __('Contact'), 'icon' => Heroicon::OutlinedPhone,
                'about' => __('Phone, WhatsApp, email and a message form'),
                'status' => $shown(CmsPage::SLUG_CONTACT) ? 'shown' : 'hidden',
                'links' => [[__('Edit'), $editUrl(CmsPage::SLUG_CONTACT)]],
            ],
        ];

        return [
            'site' => $site,
            'siteLabel' => preg_replace('#^https?://#', '', $site),
            'whatsappShare' => 'https://wa.me/?text='.rawurlencode(__('See our rooms: :url', ['url' => $site])),
            'rows' => $rows,
            'newMessages' => ContactSubmission::query()->where('status', ContactSubmission::STATUS_NEW)->count(),
            'messagesUrl' => ContactSubmissionResource::canViewAny() ? ContactSubmissionResource::getUrl() : null,
            'logo' => $client?->logoUrl(),
            'icon' => $client?->iconUrl(),
            'color' => preg_match('/^#[0-9a-f]{3,8}$/i', (string) $client?->brand_primary_color) ? $client->brand_primary_color : null,
            'settingsUrl' => WorkspaceSettings::canAccess() ? WorkspaceSettings::getUrl() : null,
        ];
    }
}
