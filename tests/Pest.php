<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

// Auto-refresh database for feature tests that touch the DB.
// Disable per-test with: `uses()->withoutDatabaseRefresh();` if needed.
uses(RefreshDatabase::class)->in('Feature');

expect()->extend('toBeTenantScoped', function () {
    // Placeholder for a custom assertion to verify tenant_id is enforced.
    // Will land in Phase 1 when the Tenant model + middleware exist.
    return $this->toBeTrue();
});
