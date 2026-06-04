<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Operating expense against a property (or null for general overhead).
 * amount in TZS cents. Receipt photo attached via Spatie Media.
 *
 * @property int $amount cents
 * @property string $currency
 * @property Carbon $expense_date
 * @property string|null $description
 * @property int|null $recorded_by_user_id
 * @property-read Property|null $property
 * @property-read ExpenseCategory|null $category
 * @property-read User|null $recordedBy
 */
class Expense extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    protected $fillable = [
        'property_id',
        'category_id',
        'amount',
        'currency',
        'expense_date',
        'description',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expense_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function registerMediaCollections(): void
    {
        // Single receipt photo / scan per expense.
        $this->addMediaCollection('receipt')
            ->singleFile()
            ->useDisk(config('filesystems.default'));
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency.' '.number_format($this->amount / 100, 0, '.', ',');
    }
}
