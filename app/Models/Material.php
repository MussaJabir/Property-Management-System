<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something kept in stock for repairs (taps, bulbs, paint) and issued against
 * work orders.
 *
 * @property int $id
 * @property string $name
 * @property string $unit_of_measure
 * @property int $unit_cost cents
 * @property string $stock_quantity
 * @property string|null $reorder_level
 */
class Material extends Model
{
    use Audited, SoftDeletes, TenantScopedModel;

    /** value => label */
    public const UNITS = [
        'piece' => 'Piece',
        'metre' => 'Metre',
        'litre' => 'Litre',
        'kg' => 'Kilogram',
        'bag' => 'Bag',
        'box' => 'Box',
        'roll' => 'Roll',
        'set' => 'Set',
    ];

    protected $fillable = ['name', 'unit_of_measure', 'unit_cost', 'stock_quantity', 'reorder_level'];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'stock_quantity' => 'decimal:2',
            'reorder_level' => 'decimal:2',
        ];
    }

    /** @return HasMany<MaterialIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function needsReorder(): bool
    {
        return $this->reorder_level !== null && (float) $this->stock_quantity <= (float) $this->reorder_level;
    }

    public function unitLabel(): string
    {
        return self::UNITS[$this->unit_of_measure] ?? $this->unit_of_measure;
    }
}
