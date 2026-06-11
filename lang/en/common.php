<?php

// Common UI strings used across the operator panel, renter portal,
// and tenant CMS. Add new keys here as features land.

return [
    // Navigation
    'dashboard' => 'Dashboard',
    'properties' => 'Properties',
    'units' => 'Units',
    'renters' => 'Renters',
    'leases' => 'Leases',
    'invoices' => 'Invoices',
    'payments' => 'Payments',
    'receipts' => 'Receipts',
    'maintenance' => 'Maintenance',
    'expenses' => 'Expenses',
    'reports' => 'Reports',
    'settings' => 'Settings',
    'profile' => 'Profile',
    'sign_out' => 'Sign Out',
    'sign_in' => 'Sign In',

    // Actions
    'save' => 'Save',
    'cancel' => 'Cancel',
    'delete' => 'Delete',
    'edit' => 'Edit',
    'create' => 'Create',
    'view' => 'View',
    'search' => 'Search',
    'filter' => 'Filter',
    'export' => 'Export',
    'pay_now' => 'Pay Now',
    'submit' => 'Submit',

    // Status
    'active' => 'Active',
    'inactive' => 'Inactive',
    'pending' => 'Pending',
    'completed' => 'Completed',
    'paid' => 'Paid',
    'unpaid' => 'Unpaid',
    'overdue' => 'Overdue',
    'partial' => 'Partial',

    // Renter portal
    'welcome' => 'Welcome',
    'client' => 'Client',
    'clients' => 'Clients',
    'mpangaji' => 'Renter',
    'total_balance' => 'Total Balance',
    'due_in_days' => 'Due in :days days',
    'recent_activity' => 'Recent Activity',
    'active_lease' => 'Active Lease',

    // Generic
    'yes' => 'Yes',
    'no' => 'No',
    'language' => 'Language',
    'english' => 'English',
    'swahili' => 'Swahili',

    // Onboarding tour (driver.js) — operator panel + renter portal
    'onboarding' => [
        'next' => 'Next',
        'previous' => 'Back',
        'done' => 'Done',
        'progress' => 'Step {{current}} of {{total}}',
        'replay' => 'Replay tour',

        'operator' => [
            'welcome_title' => 'Welcome to your workspace 👋',
            'welcome_body' => 'Take a quick tour to see how to set up your properties and start collecting rent. You can skip anytime.',
            'locations_title' => '1. Locations',
            'locations_body' => 'Start here. Add the areas where your properties are (for example Kariakoo or Ilala). Every property belongs to a location.',
            'properties_title' => '2. Properties',
            'properties_body' => 'Add your buildings here, each one under a location.',
            'units_title' => '3. Units',
            'units_body' => 'Add the rentable units inside each property — rooms, apartments or business frames.',
            'renters_title' => '4. Renters',
            'renters_body' => 'Add the people renting your units (wapangaji) and their contact details.',
            'leases_title' => '5. Leases',
            'leases_body' => 'Link a renter to a unit with a lease. This sets the rent amount and billing cycle.',
            'billing_title' => '6. Invoices & payments',
            'billing_body' => 'Rent invoices are raised here. Record payments as they come in and issue receipts to your renters.',
            'maintenance_title' => '7. Maintenance',
            'maintenance_body' => 'Track repair requests and assign them to your own staff.',
            'reports_title' => '8. Reports',
            'reports_body' => 'Under the Reports menu you can view collections and occupancy and export them to Excel or PDF.',
            'finish_title' => 'You are all set 🎉',
            'finish_body' => 'That is the tour. You can replay it anytime from the menu under your name (top right).',
        ],

        'renter' => [
            'welcome_title' => 'Welcome to your portal 👋',
            'welcome_body' => 'Here is a quick tour of where to find everything. You can skip anytime.',
            'summary_title' => 'Your tenancy at a glance',
            'summary_body' => 'Your unit, your next payment due date, and your outstanding balance are shown here.',
            'invoices_title' => 'Invoices',
            'invoices_body' => 'View your rent invoices and download receipts for payments you have made.',
            'maintenance_title' => 'Maintenance',
            'maintenance_body' => 'Report a repair and follow its progress until it is resolved.',
            'notifications_title' => 'Notifications',
            'notifications_body' => 'We will alert you here about new invoices and updates to your requests.',
            'profile_title' => 'Profile & language',
            'profile_body' => 'Update your details, and switch between English and Swahili whenever you like.',
            'finish_title' => 'You are all set 🎉',
            'finish_body' => 'That is the tour. You can replay it anytime using the “Replay tour” button.',
        ],
    ],
];
