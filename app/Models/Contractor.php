<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Propaganistas\LaravelPhone\Casts\E164PhoneNumberCast;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * An outside company or person who does maintenance work for the client.
 *
 * @property int $id
 * @property string $name
 * @property string|null $company
 * @property string|null $trade
 * @property PhoneNumber|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property bool $is_active
 */
class Contractor extends Model
{
    use Audited, SoftDeletes, TenantScopedModel;

    protected $fillable = ['name', 'company', 'trade', 'phone', 'email', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'phone' => E164PhoneNumberCast::class.':TZ'];
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    /**
     * @param  Builder<Contractor>  $query
     * @return Builder<Contractor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** "Name (Company)" for pick lists. */
    public function displayName(): string
    {
        return $this->company ? "{$this->name} ({$this->company})" : $this->name;
    }
}
