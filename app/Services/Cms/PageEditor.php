<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\CmsPage;
use Illuminate\Support\Arr;

/**
 * Turns a page's stored blocks into the fixed sections of the Website editor,
 * and back. The website still renders `blocks`, so nothing needs migrating:
 * pages saved by the old block builder open here as they are.
 *
 * A section switched off stays in `blocks` with `hidden: true`, so turning it
 * back on brings back what was written.
 */
final class PageEditor
{
    /**
     * Home page sections, in the order the website shows them.
     *
     * @var list<'banner'|'rooms'|'about'|'photos'|'news'|'contact'>
     */
    public const HOME_SECTIONS = ['banner', 'rooms', 'about', 'photos', 'news', 'contact'];

    public const MAX_PHOTOS = 12;

    /** Block type behind each section. */
    private const BLOCK_TYPES = [
        'banner' => 'hero',
        'rooms' => 'featured_units',
        'about' => 'rich_text',
        'photos' => 'image_gallery',
        'news' => 'announcements',
        'contact' => 'contact_form',
    ];

    /** Placeholder text from the old starter pages, shown as empty so nobody publishes it by mistake. */
    private const OLD_STARTER_TEXT = [
        "We've been helping renters and owners across Tanzania since day one. Transparent leases, responsive maintenance, and bilingual support — that's the difference.",
        'We exist to make renting in Tanzania simpler, fairer, and a lot less stressful — for both landlords and renters. Tell our story here.',
    ];

    /** Whether $text is placeholder text from the old starter pages. */
    public static function isOldStarterText(mixed $text): bool
    {
        return in_array($text, self::OLD_STARTER_TEXT, true);
    }

    /**
     * Sections each page offers in the editor. The rooms page fills itself.
     *
     * @return list<'banner'|'rooms'|'about'|'photos'|'news'|'contact'>
     */
    public static function sectionsFor(string $slug): array
    {
        return match ($slug) {
            CmsPage::SLUG_HOME => self::HOME_SECTIONS,
            CmsPage::SLUG_ABOUT => ['about', 'photos'],
            CmsPage::SLUG_CONTACT => ['contact'],
            CmsPage::SLUG_NEWS => ['news'],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public static function toForm(CmsPage $page): array
    {
        $blocks = collect($page->blocks ?? []);
        $find = fn (string $section): ?array => $blocks->first(fn ($block): bool => data_get($block, 'type') === self::BLOCK_TYPES[$section]);

        $form = [
            'title' => $page->title,
            'subtitle' => $page->subtitle,
            'show_page' => $page->isPublished(),
        ];

        foreach (self::sectionsFor($page->slug) as $section) {
            $block = $find($section);
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            // A section the page never had starts switched off.
            $show = $block !== null && empty($data['hidden']);

            $form[$section] = match ($section) {
                'banner' => [
                    'heading' => $data['heading'] ?? null,
                    'subheading' => $data['subheading'] ?? null,
                    'background_image' => $data['background_image'] ?? null,
                ],
                'rooms' => ['show' => $show, 'heading' => $data['heading'] ?? null, 'limit' => (int) ($data['limit'] ?? 6)],
                'about' => [
                    'show' => $show,
                    'heading' => $data['heading'] ?? null,
                    'body' => self::isOldStarterText($data['body'] ?? null) ? null : ($data['body'] ?? null),
                ],
                'photos' => [
                    'show' => $show,
                    'heading' => $data['heading'] ?? null,
                    'images' => collect($data['images'] ?? [])->pluck('path')->filter()->values()->all(),
                    // Photos added by web address before uploads existed: kept as they are.
                    'links' => collect($data['images'] ?? [])->filter(fn ($image): bool => empty($image['path']) && ! empty($image['url']))->values()->all(),
                ],
                'news' => ['show' => $show, 'heading' => $data['heading'] ?? null],
                'contact' => ['show' => $show, 'heading' => $data['heading'] ?? null, 'note' => $data['note'] ?? null],
            };
        }

        return $form;
    }

    /**
     * The page's columns for what was entered in the editor.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function toRecord(CmsPage $page, array $form): array
    {
        $home = $page->slug === CmsPage::SLUG_HOME;
        $blocks = [];

        foreach (self::sectionsFor($page->slug) as $section) {
            $values = is_array($form[$section] ?? null) ? $form[$section] : [];
            // Only the home page switches sections; elsewhere the whole page is switched.
            $hidden = $home && $section !== 'banner' && empty($values['show']);

            $data = match ($section) {
                'banner' => [
                    'heading' => self::text($values['heading'] ?? null),
                    'subheading' => self::text($values['subheading'] ?? null),
                    'background_image' => self::firstPath($values['background_image'] ?? null),
                ],
                'rooms' => ['heading' => self::text($values['heading'] ?? null), 'limit' => max(1, min(24, (int) ($values['limit'] ?? 6)))],
                'about' => ['heading' => self::text($values['heading'] ?? null), 'body' => self::text($values['body'] ?? null)],
                'photos' => [
                    'heading' => self::text($values['heading'] ?? null),
                    'images' => [
                        ...collect(Arr::wrap($values['images'] ?? []))->filter(fn ($path): bool => is_string($path) && $path !== '')
                            ->take(self::MAX_PHOTOS)->map(fn (string $path): array => ['path' => $path])->values()->all(),
                        ...collect(Arr::wrap($values['links'] ?? []))->filter(fn ($link): bool => is_array($link) && ! empty($link['url']))->values()->all(),
                    ],
                ],
                'news' => ['heading' => self::text($values['heading'] ?? null), 'limit' => $home ? 3 : 10, 'compact' => $home],
                'contact' => ['heading' => self::text($values['heading'] ?? null), 'note' => self::text($values['note'] ?? null)],
            };

            $blocks[] = [
                'type' => self::BLOCK_TYPES[$section],
                'data' => array_filter($data, fn ($value): bool => $value !== null) + ($hidden ? ['hidden' => true] : []),
            ];
        }

        $record = ['blocks' => $blocks];

        if (! $home) {
            $record['title'] = self::text($form['title'] ?? null) ?? $page->title;
            $record['subtitle'] = self::text($form['subtitle'] ?? null);
            $record['published_at'] = ! empty($form['show_page'])
                ? ($page->isPublished() ? $page->published_at : now())
                : null;
        }

        return $record;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function firstPath(mixed $value): ?string
    {
        $path = is_array($value) ? Arr::first($value) : $value;

        return is_string($path) && $path !== '' ? $path : null;
    }
}
