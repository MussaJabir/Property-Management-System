<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A block or building inside a property (e.g. "Block A"). Units can name the
 * building and floor they are on.
 *
 * @property int $id
 * @property string $property_id
 * @property string $name
 * @property int|null $floors
 * @property string|null $notes
 * @property-read Property|null $property
 */
class Building extends Model
{
    use Audited, SoftDeletes, TenantScopedModel;

    protected $fillable = ['property_id', 'name', 'floors', 'notes'];

    protected function casts(): array
    {
        return ['floors' => 'integer'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
