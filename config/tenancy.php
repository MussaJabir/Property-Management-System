<?php

declare(strict_types=1);

use App\Models\Client;
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

return [
    'tenant_model' => Client::class,
    // Use slug as tenant id (set in App\Models\Tenant::booted()) instead of
    // auto-generated UUIDs — keeps URLs human-readable and lets path-based
    // identification resolve directly by primary key.
    'id_generator' => null,

    'domain_model' => Domain::class,

    /**
     * The list of domains hosting your central app.
     *
     * For path-based tenancy (v1) every URL hits the central domain — the tenant
     * is identified by the first URL segment, not the host. List both the local
     * dev hostnames and the production hostname here so PreventAccessFromCentralDomains
     * does not block legitimate tenant requests.
     */
    'central_domains' => [
        '127.0.0.1',
        'localhost',
        'localhost:8088',
        'pms.bjptechnologies.co.tz',
    ],

    /**
     * Tenancy bootstrappers are executed when tenancy is initialized.
     *
     * PMS uses **single-database** mode (one shared Postgres, tenant_id scoping
     * via BelongsToTenant trait on each model). The DatabaseTenancyBootstrapper
     * is intentionally NOT enabled — we never switch DB connections per tenant.
     *
     * CacheTenancyBootstrapper / FilesystemTenancyBootstrapper /
     * QueueTenancyBootstrapper give us per-tenant cache keys, filesystem prefixes,
     * and queue namespaces — all useful even in single-DB mode.
     */
    'bootstrappers' => [
        // Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class, // single-DB: disabled
        CacheTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
        // Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class, // Note: phpredis is needed
    ],

    /**
     * Database tenancy config. Used by DatabaseTenancyBootstrapper.
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'central'),

        /**
         * Connection used as a "template" for the dynamically created tenant database connection.
         * Note: don't name your template connection tenant. That name is reserved by package.
         */
        'template_tenant_connection' => null,

        /**
         * Tenant database names are created like this:
         * prefix + tenant_id + suffix.
         */
        'prefix' => 'tenant',
        'suffix' => '',

        /**
         * TenantDatabaseManagers are classes that handle the creation & deletion of tenant databases.
         */
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,

        /**
         * Use this database manager for MySQL to have a DB user created for each tenant database.
         * You can customize the grants given to these users by changing the $grants property.
         */
            // 'mysql' => Stancl\Tenancy\TenantDatabaseManagers\PermissionControlledMySQLDatabaseManager::class,

        /**
         * Disable the pgsql manager above, and enable the one below if you
         * want to separate tenant DBs by schemas rather than databases.
         */
            // 'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class, // Separate by schema instead of database
        ],
    ],

    /**
     * Cache tenancy config. Used by CacheTenancyBootstrapper.
     *
     * This works for all Cache facade calls, cache() helper
     * calls and direct calls to injected cache stores.
     *
     * Each key in cache will have a tag applied on it. This tag is used to
     * scope the cache both when writing to it and when reading from it.
     *
     * You can clear cache selectively by specifying the tag.
     */
    'cache' => [
        'tag_base' => 'tenant', // This tag_base, followed by the tenant_id, will form a tag that will be applied on each cache call.
    ],

    /**
     * Filesystem tenancy config. Used by FilesystemTenancyBootstrapper.
     * https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/#filesystem-tenancy-boostrapper.
     */
    'filesystem' => [
        /**
         * Each disk listed in the 'disks' array will be suffixed by the suffix_base, followed by the tenant_id.
         */
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            // 'public' is deliberately NOT suffixed per tenant.
            //
            // Suffixing it routes uploads to storage/tenant{id}/app/public/,
            // but `asset_helper_tenancy => false` (below) means the /storage
            // URL and the `public/storage` symlink point at the GLOBAL
            // storage/app/public/ — so every tenant-uploaded image 404s
            // (file is written to the tenant path, URL reads the global path).
            //
            // Public CMS images (property photos, hero banners) are served to
            // the whole internet anyway, so per-tenant physical isolation buys
            // nothing here — the media rows stay tenant-scoped in the DB.
            // Keeping `public` global lets the standard storage:link symlink
            // serve them. When B2 is enabled (FILESYSTEM_DISK=b2) uploads go to
            // the cloud disk via explicit Spatie paths, so this is moot there.
            // 's3',
        ],

        /**
         * Use this for local disks.
         *
         * See https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/#filesystem-tenancy-boostrapper
         */
        'root_override' => [
            // Disks whose roots should be overridden after storage_path() is suffixed.
            'local' => '%storage_path%/app/',
            // 'public' intentionally omitted — see the 'disks' note above; the
            // public disk stays global so its files are reachable via /storage.
        ],

        /**
         * Should storage_path() be suffixed.
         *
         * Note: Disabling this will likely break local disk tenancy. Only disable this if you're using an external file storage service like S3.
         *
         * For the vast majority of applications, this feature should be enabled. But in some
         * edge cases, it can cause issues (like using Passport with Vapor - see #196), so
         * you may want to disable this if you are experiencing these edge case issues.
         */
        'suffix_storage_path' => true,

        /**
         * PMS: asset_helper_tenancy DISABLED.
         *
         * When true, every asset() call gets rewritten to /tenancy/assets/{path}
         * which routes to tenant-specific storage. Filament and many other
         * Laravel packages call asset() for their own published CSS/JS — those
         * files are NOT in tenant storage, so every Filament URL would 404
         * (no styles, no JS).
         *
         * We use B2 (with explicit paths via Spatie Media Library) for tenant
         * file uploads, so this rewriting is unnecessary. If we ever need
         * tenant-specific local-disk assets, use the explicit tenant_asset()
         * helper at the call site.
         */
        'asset_helper_tenancy' => false,
    ],

    /**
     * Redis tenancy config. Used by RedisTenancyBootstrapper.
     *
     * Note: You need phpredis to use Redis tenancy.
     *
     * Note: You don't need to use this if you're using Redis only for cache.
     * Redis tenancy is only relevant if you're making direct Redis calls,
     * either using the Redis facade or by injecting it as a dependency.
     */
    'redis' => [
        'prefix_base' => 'tenant', // Each key in Redis will be prepended by this prefix_base, followed by the tenant id.
        'prefixed_connections' => [ // Redis connections whose keys are prefixed, to separate one tenant's keys from another.
            // 'default',
        ],
    ],

    /**
     * Features are classes that provide additional functionality
     * not needed for tenancy to be bootstrapped. They are run
     * regardless of whether tenancy has been initialized.
     *
     * See the documentation page for each class to
     * understand which ones you want to enable.
     */
    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
        // Stancl\Tenancy\Features\UniversalRoutes::class,
        // Stancl\Tenancy\Features\TenantConfig::class, // https://tenancyforlaravel.com/docs/v3/features/tenant-config
        // Stancl\Tenancy\Features\CrossDomainRedirect::class, // https://tenancyforlaravel.com/docs/v3/features/cross-domain-redirect
        // Stancl\Tenancy\Features\ViteBundler::class,
    ],

    /**
     * Should tenancy routes be registered.
     *
     * Tenancy routes include tenant asset routes. By default, this route is
     * enabled. But it may be useful to disable them if you use external
     * storage (e.g. S3 / Dropbox) or have a custom asset controller.
     */
    'routes' => true,

    /**
     * Parameters used by the tenants:migrate command.
     */
    'migration_parameters' => [
        '--force' => true, // This needs to be true to run migrations in production.
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /**
     * Parameters used by the tenants:seed command.
     */
    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder', // root seeder class
        // '--force' => true, // This needs to be true to seed tenant databases in production
    ],
];
