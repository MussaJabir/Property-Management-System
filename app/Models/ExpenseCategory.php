<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-tenant classification for Expense rows. Seeded with six defaults on
 * client creation via ClientObserver::created.
 *
 * No soft deletes — categories are referenced by expenses; rename instead.
 *
 * @property string $name
 * @property string|null $color hex like '#ef4444'
 */
class ExpenseCategory extends Model
{
    use HasFactory, TenantScopedModel;

    /**
     * Seed values used by ClientObserver. Kept as a constant so tests +
     * code-readers can find the source-of-truth in one place.
     */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Repair', 'color' => '#ef4444'],
        ['name' => 'Cleaning', 'color' => '#22c55e'],
        ['name' => 'Security', 'color' => '#3b82f6'],
        ['name' => 'Utilities', 'color' => '#eab308'],
        ['name' => 'Tax', 'color' => '#a855f7'],
        ['name' => 'Other', 'color' => '#6b7280'],
    ];

    protected $fillable = [
        'name',
        'color',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }
}
