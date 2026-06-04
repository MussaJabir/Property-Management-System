<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only timeline entry on a MaintenanceRequest. Created either by the
 * request's state-machine methods (status_change != null) or as a plain
 * operator note (status_change == null).
 *
 * @property string $note
 * @property string|null $status_change
 * @property Carbon|null $created_at
 * @property-read MaintenanceRequest|null $request
 * @property-read User|null $user
 */
class MaintenanceUpdate extends Model
{
    use HasFactory, TenantScopedModel;

    public $timestamps = false;

    protected $fillable = [
        'maintenance_request_id',
        'user_id',
        'note',
        'status_change',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
