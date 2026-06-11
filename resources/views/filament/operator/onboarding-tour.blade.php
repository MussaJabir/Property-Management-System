@php
    /** @var \App\Models\User|null $tourUser */
    $tourUser = auth()->user();
@endphp

@if ($tourUser && $tourUser->isOperator())
    @php
        // Sidebar links live on every panel page, so the tour works (and can be
        // replayed) from anywhere. Steps with no `element`, or whose element is
        // absent (e.g. hidden by role), render as centered popovers.
        $tourSteps = [
            [
                'title' => __('common.onboarding.operator.welcome_title'),
                'description' => __('common.onboarding.operator.welcome_body'),
            ],
            [
                'element' => 'a[href$="/manage/locations"]',
                'title' => __('common.onboarding.operator.locations_title'),
                'description' => __('common.onboarding.operator.locations_body'),
            ],
            [
                'element' => 'a[href$="/manage/properties"]',
                'title' => __('common.onboarding.operator.properties_title'),
                'description' => __('common.onboarding.operator.properties_body'),
            ],
            [
                'element' => 'a[href$="/manage/units"]',
                'title' => __('common.onboarding.operator.units_title'),
                'description' => __('common.onboarding.operator.units_body'),
            ],
            [
                'element' => 'a[href$="/manage/renters"]',
                'title' => __('common.onboarding.operator.renters_title'),
                'description' => __('common.onboarding.operator.renters_body'),
            ],
            [
                'element' => 'a[href$="/manage/leases"]',
                'title' => __('common.onboarding.operator.leases_title'),
                'description' => __('common.onboarding.operator.leases_body'),
            ],
            [
                'element' => 'a[href$="/manage/invoices"]',
                'title' => __('common.onboarding.operator.billing_title'),
                'description' => __('common.onboarding.operator.billing_body'),
            ],
            [
                'element' => 'a[href$="/manage/maintenance-requests"]',
                'title' => __('common.onboarding.operator.maintenance_title'),
                'description' => __('common.onboarding.operator.maintenance_body'),
            ],
            [
                'title' => __('common.onboarding.operator.reports_title'),
                'description' => __('common.onboarding.operator.reports_body'),
            ],
            [
                'title' => __('common.onboarding.operator.finish_title'),
                'description' => __('common.onboarding.operator.finish_body'),
            ],
        ];

        $tourConfig = [
            'autostart' => $tourUser->needsOnboarding(),
            'completeUrl' => route('operator.onboarding.complete'),
            'csrf' => csrf_token(),
            'labels' => [
                'next' => __('common.onboarding.next'),
                'previous' => __('common.onboarding.previous'),
                'done' => __('common.onboarding.done'),
                'progress' => __('common.onboarding.progress'),
            ],
            'steps' => $tourSteps,
        ];
    @endphp

    <script>
        window.pmsOnboarding = @json($tourConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    </script>
    @vite('resources/js/onboarding.js')
@endif
