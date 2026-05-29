<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Location = a region/district/ward catalog entry per client. Properties live
 * inside Locations so the operator can group "all my Kariakoo properties"
 * without typing the address every time.
 */
class Location extends Model
{
    use HasFactory, SoftDeletes, TenantScopedModel;

    protected $guarded = [];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function getDisplayAddressAttribute(): string
    {
        return collect([$this->ward, $this->district, $this->region])
            ->filter()
            ->implode(', ');
    }
}
