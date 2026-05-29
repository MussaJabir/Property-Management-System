<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit row for lease state transitions. Created by Lease's
 * activate() / terminate() / end() methods — do not write directly from UI.
 *
 * No soft deletes — audit rows are immutable.
 *
 * @property string $action
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property-read Lease|null $lease
 * @property-read User|null $user
 */
class LeaseHistory extends Model
{
    use HasFactory, TenantScopedModel;

    public $timestamps = false;

    protected $table = 'lease_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
