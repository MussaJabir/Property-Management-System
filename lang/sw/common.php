<?php

// Common UI strings used across the operator panel, renter portal,
// and tenant CMS. Add new keys here as features land.

return [
    // Navigation
    'dashboard' => 'Dashibodi',
    'properties' => 'Mali',
    'units' => 'Vyumba',
    'renters' => 'Wapangaji',
    'leases' => 'Mikataba',
    'invoices' => 'Ankara',
    'payments' => 'Malipo',
    'receipts' => 'Risiti',
    'maintenance' => 'Matengenezo',
    'expenses' => 'Gharama',
    'reports' => 'Ripoti',
    'settings' => 'Mipangilio',
    'profile' => 'Wasifu',
    'sign_out' => 'Toka',
    'sign_in' => 'Ingia',

    // Actions
    'save' => 'Hifadhi',
    'cancel' => 'Ghairi',
    'delete' => 'Futa',
    'edit' => 'Hariri',
    'create' => 'Tengeneza',
    'view' => 'Tazama',
    'search' => 'Tafuta',
    'filter' => 'Chuja',
    'export' => 'Pakua',
    'pay_now' => 'Lipa Sasa',
    'submit' => 'Wasilisha',

    // Status
    'active' => 'Inafanya kazi',
    'inactive' => 'Haifanyi kazi',
    'pending' => 'Inasubiri',
    'completed' => 'Imekamilika',
    'paid' => 'Imelipwa',
    'unpaid' => 'Haijalipwa',
    'overdue' => 'Imechelewa',
    'partial' => 'Sehemu',

    // Renter portal
    'welcome' => 'Karibu',
    'client' => 'Mteja',
    'clients' => 'Wateja',
    'mpangaji' => 'Mpangaji',
    'total_balance' => 'Salio la Jumla',
    'due_in_days' => 'Inakatika baada ya siku :days',
    'recent_activity' => 'Shughuli za Hivi Karibuni',
    'active_lease' => 'Mkataba Unaoendelea',

    // Generic
    'yes' => 'Ndio',
    'no' => 'Hapana',
    'language' => 'Lugha',
    'english' => 'Kiingereza',
    'swahili' => 'Kiswahili',

    // Ziara ya utangulizi (driver.js) — paneli ya opereta + lango la mpangaji
    'onboarding' => [
        'next' => 'Endelea',
        'previous' => 'Rudi',
        'done' => 'Maliza',
        'progress' => 'Hatua {{current}} kati ya {{total}}',
        'replay' => 'Rudia ziara',

        'operator' => [
            'welcome_title' => 'Karibu kwenye eneo lako la kazi 👋',
            'welcome_body' => 'Pitia ziara fupi kuona jinsi ya kuweka mali zako na kuanza kukusanya kodi. Unaweza kuruka wakati wowote.',
            'locations_title' => '1. Maeneo',
            'locations_body' => 'Anza hapa. Ongeza maeneo ambapo mali zako zipo (kwa mfano Kariakoo au Ilala). Kila mali ina eneo lake.',
            'properties_title' => '2. Mali',
            'properties_body' => 'Ongeza majengo yako hapa, kila moja chini ya eneo lake.',
            'units_title' => '3. Vyumba/Vitengo',
            'units_body' => 'Ongeza vitengo vinavyopangishwa ndani ya kila mali — vyumba, nyumba au fremu za biashara.',
            'renters_title' => '4. Wapangaji',
            'renters_body' => 'Ongeza watu wanaopanga vitengo vyako (wapangaji) na mawasiliano yao.',
            'leases_title' => '5. Mikataba',
            'leases_body' => 'Unganisha mpangaji na kitengo kwa mkataba. Hii huweka kiasi cha kodi na mzunguko wa malipo.',
            'billing_title' => '6. Ankara na malipo',
            'billing_body' => 'Ankara za kodi hutengenezwa hapa. Rekodi malipo yanapopokelewa na utoe risiti kwa wapangaji wako.',
            'maintenance_title' => '7. Matengenezo',
            'maintenance_body' => 'Fuatilia maombi ya matengenezo na uwakabidhi wafanyakazi wako.',
            'reports_title' => '8. Ripoti',
            'reports_body' => 'Chini ya menyu ya Ripoti unaweza kuona makusanyo na ukaaji na kuvipakua kwa Excel au PDF.',
            'finish_title' => 'Umemaliza 🎉',
            'finish_body' => 'Hiyo ndiyo ziara. Unaweza kuirudia wakati wowote kupitia menyu chini ya jina lako (juu kulia).',
        ],

        'renter' => [
            'welcome_title' => 'Karibu kwenye lango lako 👋',
            'welcome_body' => 'Hii ni ziara fupi ya wapi kupata kila kitu. Unaweza kuruka wakati wowote.',
            'summary_title' => 'Upangaji wako kwa muhtasari',
            'summary_body' => 'Kitengo chako, tarehe ya malipo yanayofuata, na salio lako linaloodaiwa huonyeshwa hapa.',
            'invoices_title' => 'Ankara',
            'invoices_body' => 'Angalia ankara zako za kodi na pakua risiti za malipo uliyofanya.',
            'maintenance_title' => 'Matengenezo',
            'maintenance_body' => 'Ripoti tatizo la matengenezo na ufuatilie hatua zake hadi litatuliwe.',
            'notifications_title' => 'Arifa',
            'notifications_body' => 'Tutakuarifu hapa kuhusu ankara mpya na mabadiliko ya maombi yako.',
            'profile_title' => 'Wasifu na lugha',
            'profile_body' => 'Sasisha taarifa zako, na ubadilishe kati ya Kiingereza na Kiswahili wakati wowote.',
            'finish_title' => 'Umemaliza 🎉',
            'finish_body' => 'Hiyo ndiyo ziara. Unaweza kuirudia wakati wowote kwa kitufe cha “Rudia ziara”.',
        ],
    ],
];
