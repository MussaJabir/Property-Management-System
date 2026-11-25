<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Materials taken from stock for one work order, priced at the time of issue.
 * Created through MaintenanceRequest::issueMaterial().
 *
 * @property int $id
 * @property string $maintenance_request_id
 * @property int $material_id
 * @property string $quantity
 * @property int $unit_cost cents
 * @property int $total_cost cents
 * @property Carbon|null $issued_at
 * @property-read Material|null $material
 * @property-read User|null $issuedBy
 */
class MaterialIssue extends Model
{
    use Audited, TenantScopedModel;

    protected $fillable = ['maintenance_request_id', 'material_id', 'quantity', 'unit_cost', 'total_cost', 'issued_by_user_id', 'issued_at'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'integer',
            'total_cost' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    /** @return BelongsTo<MaintenanceRequest, $this> */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
