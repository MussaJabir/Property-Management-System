<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_tzs' => 'integer',
            'max_properties' => 'integer',
            'max_units' => 'integer',
            'max_operators' => 'integer',
            'features' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Price formatted for display ("TZS 49,000").
     *
     * Stored as minor units (cents). Divide by 100 for display.
     */
    public function getFormattedPriceAttribute(): string
    {
        $major = $this->price_tzs / 100;

        return 'TZS '.number_format($major, 0, '.', ',');
    }
}
