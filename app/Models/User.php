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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Operator (landlord staff) or Renter (mpangaji) user, scoped to one Client.
 *
 * Type column distinguishes operator vs renter. Filament's HasTenants contract
 * is implemented so the operator panel knows which Client this user belongs to.
 */
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const TYPE_OPERATOR = 'operator';

    public const TYPE_RENTER = 'renter';

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'email',
        'phone',
        'password',
        'locale',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function isOperator(): bool
    {
        return $this->type === self::TYPE_OPERATOR;
    }

    public function isRenter(): bool
    {
        return $this->type === self::TYPE_RENTER;
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
