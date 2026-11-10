<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\CmsPage;

/**
 * Starter pages for every new Client. No made-up claims: sections the
 * landlord has not written yet stay empty and the website skips them.
 * They fill everything in from Website in the dashboard.
 */
class DefaultPageContent
{
    /**
     * @return array<int, array{slug: string, title: string, subtitle: ?string, published: bool, blocks: array<int, array{type: string, data: array<string, mixed>}>}>
     */
    public static function forClient(string $clientName): array
    {
        return [
            [
                'slug' => CmsPage::SLUG_HOME,
                'title' => $clientName,
                'subtitle' => null,
                'published' => true,
                'blocks' => [
                    ['type' => 'hero', 'data' => ['heading' => 'Welcome to '.$clientName]],
                    ['type' => 'featured_units', 'data' => ['limit' => 6]],
                    // Empty until the landlord writes them; the website skips empty sections.
                    ['type' => 'rich_text', 'data' => []],
                    ['type' => 'image_gallery', 'data' => ['images' => []]],
                    ['type' => 'announcements', 'data' => ['limit' => 3, 'compact' => true]],
                    ['type' => 'contact_form', 'data' => []],
                ],
            ],
            [
                // Hidden until they tell their story.
                'slug' => CmsPage::SLUG_ABOUT,
                'title' => 'About us',
                'subtitle' => null,
                'published' => false,
                'blocks' => [
                    ['type' => 'rich_text', 'data' => []],
                    ['type' => 'image_gallery', 'data' => ['images' => []]],
                ],
            ],
            [
                'slug' => CmsPage::SLUG_UNITS,
                'title' => 'Available units',
                'subtitle' => null,
                'published' => true,
                'blocks' => [],
            ],
            [
                // In the menu once the first post is out.
                'slug' => CmsPage::SLUG_NEWS,
                'title' => 'News',
                'subtitle' => null,
                'published' => true,
                'blocks' => [['type' => 'announcements', 'data' => ['limit' => 10]]],
            ],
            [
                'slug' => CmsPage::SLUG_CONTACT,
                'title' => 'Contact us',
                'subtitle' => null,
                'published' => true,
                'blocks' => [['type' => 'contact_form', 'data' => []]],
            ],
        ];
    }
}
