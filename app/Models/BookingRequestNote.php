<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line in a request's history: a staff note, a call or WhatsApp tap,
 * an alert sent, or a status change.
 *
 * @property int $id
 * @property string $booking_request_id
 * @property int|null $user_id
 * @property string $type
 * @property string|null $body
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class BookingRequestNote extends Model
{
    use TenantScopedModel;

    public const TYPE_NOTE = 'note';

    public const TYPE_CALL = 'call';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_ALERT = 'alert';

    public const TYPE_STATUS = 'status';

    protected $fillable = [
        'booking_request_id',
        'user_id',
        'type',
        'body',
    ];

    /** @return BelongsTo<BookingRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class, 'booking_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
