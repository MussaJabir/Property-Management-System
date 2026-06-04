<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on an invoice. line_total is stored — recomputed by the model
 * itself on save so float math from the UI doesn't leak into the DB.
 *
 * No tenant_id column; scoping comes from the parent invoice.
 *
 * @property string $description
 * @property float $quantity
 * @property int $unit_price cents
 * @property int $line_total cents
 * @property string $type rent | utility | fee | deposit | other
 * @property-read Invoice|null $invoice
 */
class InvoiceItem extends Model
{
    use HasFactory;

    public const TYPE_RENT = 'rent';

    public const TYPE_UTILITY = 'utility';

    public const TYPE_FEE = 'fee';

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Always derive line_total server-side; never trust the form value.
        static::saving(function (self $item): void {
            $item->line_total = (int) round(((float) $item->quantity) * (int) $item->unit_price);
        });

        static::saved(fn (self $item) => $item->invoice?->recomputeTotals());
        static::deleted(fn (self $item) => $item->invoice?->recomputeTotals());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
