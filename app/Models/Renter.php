<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Propaganistas\LaravelPhone\Casts\E164PhoneNumberCast;

/**
 * Renter ("mpangaji") = the person or business renting a unit. NOT the same
 * as a SaaS-level "Client" (the landlord company). See CLAUDE.md > Naming
 * glossary if unsure.
 *
 * NIDA + TIN are encrypted at rest via Laravel's `encrypted` cast.
 * Phone numbers are normalized to E.164 (assuming TZ when no country code is
 * given) via propaganistas/laravel-phone.
 */
class Renter extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScopedModel;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_BUSINESS = 'business';

    protected $fillable = [
        'type',
        'full_name',
        'business_name',
        'phone',
        'alt_phone',
        'email',
        'nida_number',
        'tin_number',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'nida_number' => 'encrypted',
            'tin_number' => 'encrypted',
            'phone' => E164PhoneNumberCast::class.':TZ',
            'alt_phone' => E164PhoneNumberCast::class.':TZ',
            'emergency_contact_phone' => E164PhoneNumberCast::class.':TZ',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLease(): ?Lease
    {
        /** @var Lease|null $lease */
        $lease = $this->leases()->where('status', Lease::STATUS_ACTIVE)->latest('start_date')->first();

        return $lease;
    }

    public function isBusiness(): bool
    {
        return $this->type === self::TYPE_BUSINESS;
    }

    /**
     * Best label for tables / picker lists.
     * Business renters lead with the business name; individuals with the
     * person's name. Falls back gracefully if a column is missing.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isBusiness() && $this->business_name) {
            return $this->business_name.' ('.$this->full_name.')';
        }

        return $this->full_name;
    }

    public function scopeIndividual(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_INDIVIDUAL);
    }

    public function scopeBusiness(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BUSINESS);
    }
}
