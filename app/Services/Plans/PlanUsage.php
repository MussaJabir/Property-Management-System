<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Exceptions\PlanLimitReached;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use InvalidArgumentException;
use Stancl\Tenancy\Database\TenantScope;

/**
 * The single place that knows a client's plan rules: how much they use, what
 * their plan allows, and which paid features it includes.
 *
 * A client with no plan assigned is treated as unlimited with every feature,
 * so workspaces created before plans existed keep working until one is set.
 */
final class PlanUsage
{
    public const PROPERTIES = 'properties';

    public const UNITS = 'units';

    public const STAFF = 'staff';

    /** Usage share at which the client is warned they are close to the limit. */
    public const WARN_AT = 0.8;

    /** Features that differ between plans and are switched off when missing. */
    public const GATED_FEATURES = ['renter_portal', 'reports', 'custom_roles', 'short_stays'];

    /** @var array<string, int> */
    private array $counts = [];

    private ?Plan $plan = null;

    private bool $planLoaded = false;

    public function __construct(private readonly Client $client) {}

    public static function for(Client $client): self
    {
        return new self($client);
    }

    /** Usage for the signed-in operator's client, or null outside a workspace. */
    public static function forCurrentUser(): ?self
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->tenant_id) {
            return null;
        }

        $client = $user->client ?? Client::find($user->tenant_id);

        return $client instanceof Client ? new self($client) : null;
    }

    /** Usage for a client id, or null when the id does not resolve. */
    public static function forClientId(?string $clientId): ?self
    {
        if ($clientId === null || $clientId === '') {
            return null;
        }

        $client = Client::find($clientId);

        return $client instanceof Client ? new self($client) : null;
    }

    public function client(): Client
    {
        return $this->client;
    }

    /** Read fresh rather than from a relation cached on a long-lived client. */
    public function plan(): ?Plan
    {
        if (! $this->planLoaded) {
            $this->plan = $this->client->plan_id ? Plan::find($this->client->plan_id) : null;
            $this->planLoaded = true;
        }

        return $this->plan;
    }

    public function used(string $resource): int
    {
        return $this->counts[$resource] ??= match ($resource) {
            self::PROPERTIES => Property::query()->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $this->client->getKey())->count(),
            self::UNITS => Unit::query()->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $this->client->getKey())->count(),
            self::STAFF => User::query()
                ->where('tenant_id', $this->client->getKey())
                ->where('type', User::TYPE_OPERATOR)
                ->where('status', '!=', User::STATUS_DISABLED)
                ->count(),
            default => throw new InvalidArgumentException("Unknown plan resource [{$resource}]."),
        };
    }

    /** The plan's limit for a resource; null means unlimited. */
    public function limit(string $resource): ?int
    {
        $plan = $this->plan();

        if ($plan === null) {
            return null;
        }

        return match ($resource) {
            self::PROPERTIES => $plan->max_properties,
            self::UNITS => $plan->max_units,
            self::STAFF => $plan->max_operators,
            default => throw new InvalidArgumentException("Unknown plan resource [{$resource}]."),
        };
    }

    public function isWaived(): bool
    {
        return $this->client->limits_waived_until !== null && $this->client->limits_waived_until->isFuture();
    }

    public function isAtLimit(string $resource): bool
    {
        $limit = $this->limit($resource);

        return $limit !== null && $this->used($resource) >= $limit;
    }

    public function isNearLimit(string $resource): bool
    {
        $limit = $this->limit($resource);

        return $limit !== null && $limit > 0 && $this->used($resource) >= $limit * self::WARN_AT;
    }

    public function canAdd(string $resource, int $count = 1): bool
    {
        $limit = $this->limit($resource);

        return $limit === null || $this->isWaived() || $this->used($resource) + $count <= $limit;
    }

    /** @throws PlanLimitReached */
    public function ensureCanAdd(string $resource): void
    {
        if (! $this->canAdd($resource)) {
            throw PlanLimitReached::for($resource, (int) $this->limit($resource));
        }
    }

    public function hasFeature(string $feature): bool
    {
        $plan = $this->plan();

        if ($plan === null || ! in_array($feature, self::GATED_FEATURES, true)) {
            return true;
        }

        return in_array($feature, (array) ($plan->features ?? []), true);
    }

    /** Short usage line, e.g. "45 of 60 units used on the Starter plan." */
    public function summary(string $resource): string
    {
        $limit = $this->limit($resource);
        $label = __(match ($resource) {
            self::PROPERTIES => 'properties',
            self::UNITS => 'units',
            default => 'staff accounts',
        });

        if ($limit === null) {
            return __(':used :label in use.', ['used' => $this->used($resource), 'label' => $label]);
        }

        return __(':used of :limit :label used on the :plan plan.', [
            'used' => $this->used($resource),
            'limit' => $limit,
            'label' => $label,
            'plan' => $this->plan()?->name,
        ]);
    }
}
