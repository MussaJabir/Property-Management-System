<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\CmsPage;

/**
 * Sample block content seeded for every new Client so their public site is
 * immediately demo-able. Operators can edit/delete from Filament.
 */
class DefaultPageContent
{
    /**
     * @return array<int, array{slug: string, title: string, subtitle: ?string, blocks: array<int, array{type: string, data: array<string, mixed>}>}>
     */
    public static function forClient(string $clientName): array
    {
        return [
            [
                'slug' => CmsPage::SLUG_HOME,
                'title' => $clientName,
                'subtitle' => 'Property management, the modern way.',
                'blocks' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'heading' => 'Welcome to '.$clientName,
                            'subheading' => 'Quality homes, business frames, and a team that takes care of the rest.',
                            'cta_label' => 'Browse units',
                            'cta_link' => 'units',
                        ],
                    ],
                    [
                        'type' => 'featured_units',
                        'data' => ['limit' => 6, 'heading' => 'Available now'],
                    ],
                    [
                        'type' => 'rich_text',
                        'data' => [
                            'heading' => 'Why choose us',
                            'body' => "We've been helping renters and owners across Tanzania since day one. Transparent leases, responsive maintenance, and bilingual support — that's the difference.",
                        ],
                    ],
                ],
            ],
            [
                'slug' => CmsPage::SLUG_ABOUT,
                'title' => 'About us',
                'subtitle' => null,
                'blocks' => [
                    [
                        'type' => 'rich_text',
                        'data' => [
                            'heading' => 'Our story',
                            'body' => 'We exist to make renting in Tanzania simpler, fairer, and a lot less stressful — for both landlords and renters. Tell our story here.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => CmsPage::SLUG_UNITS,
                'title' => 'Available units',
                'subtitle' => 'Filter by price, type and location.',
                'blocks' => [],
            ],
            [
                'slug' => CmsPage::SLUG_NEWS,
                'title' => 'News & updates',
                'subtitle' => 'Announcements from the office.',
                'blocks' => [
                    [
                        'type' => 'announcements',
                        'data' => ['limit' => 10],
                    ],
                ],
            ],
            [
                'slug' => CmsPage::SLUG_CONTACT,
                'title' => 'Get in touch',
                'subtitle' => 'We respond within one business day.',
                'blocks' => [
                    [
                        'type' => 'contact_form',
                        'data' => [
                            'heading' => 'Send us a message',
                            'note' => 'Your details stay private.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
