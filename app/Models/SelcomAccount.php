<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A client's own Selcom merchant credentials (see the migration). One per
 * client; read it with `SelcomAccount::forClient()`.
 *
 * @property string $mode
 * @property string|null $vendor
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property bool $enabled
 * @property Carbon|null $verified_at
 */
class SelcomAccount extends Model
{
    use TenantScopedModel;

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_LIVE = 'live';

    protected $fillable = [
        'mode',
        'vendor',
        'api_key',
        'api_secret',
        'enabled',
        'verified_at',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'enabled' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * The client's account, if it has saved one. Filters by tenant_id
     * itself, so it is safe outside tenancy too.
     */
    public static function forClient(Client|string $client): ?self
    {
        $id = $client instanceof Client ? $client->getKey() : $client;

        return self::query()->where('tenant_id', $id)->first();
    }

    /** Are the vendor ID, key and secret all filled in? */
    public function hasCredentials(): bool
    {
        return filled($this->vendor) && filled($this->api_key) && filled($this->api_secret);
    }

    /** Can staff ask for payments through Selcom right now? */
    public function isReady(): bool
    {
        return $this->enabled && $this->hasCredentials() && filled($this->baseUrl());
    }

    /** Selcom's API address for this account's mode, from config. */
    public function baseUrl(): ?string
    {
        $url = config('services.selcom.'.$this->mode.'_url');

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }
}
