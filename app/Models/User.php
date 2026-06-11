<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Operator (landlord staff) or Renter (mpangaji) user, scoped to one Client.
 *
 * Type column distinguishes operator vs renter. Filament's HasTenants contract
 * is implemented so the operator panel knows which Client this user belongs to.
 *
 * @property string|null $activation_token
 * @property Carbon|null $activation_token_expires_at
 */
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const TYPE_OPERATOR = 'operator';

    public const TYPE_RENTER = 'renter';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_ACTIVATION = 'pending_activation';

    public const STATUS_DISABLED = 'disabled';

    protected static function booted(): void
    {
        // Once the user actually picks a new password on an existing account,
        // drop the "must change on next sign-in" gate. Only fires on updates
        // ($user->exists) so we don't clobber the flag at initial provisioning
        // time, which is the whole point of the flag.
        static::updating(function (self $user): void {
            if ($user->isDirty('password') && $user->must_change_password) {
                $user->must_change_password = false;
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'email',
        'phone',
        'password',
        'locale',
        'status',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'activation_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'activation_token_expires_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Whether this user still needs the first-login onboarding tour.
     * Null timestamp = never finished/skipped it, so it auto-starts.
     */
    public function needsOnboarding(): bool
    {
        return $this->onboarding_completed_at === null;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function renter(): HasOne
    {
        return $this->hasOne(Renter::class);
    }

    public function isOperator(): bool
    {
        return $this->type === self::TYPE_OPERATOR;
    }

    public function isRenter(): bool
    {
        return $this->type === self::TYPE_RENTER;
    }

    public function isPendingActivation(): bool
    {
        return $this->status === self::STATUS_PENDING_ACTIVATION;
    }

    /* ----- Filament panel access ----- */

    /**
     * Only operator-type users may access the operator panel.
     * The admin panel uses a separate SuperAdminUser model.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'operator') {
            return false;
        }

        return $this->isOperator() && $this->status === 'active';
    }

    /* ----- Filament tenancy: each user belongs to exactly one Client ----- */

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->client ? collect([$this->client]) : collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant->getKey() === $this->tenant_id;
    }
}
