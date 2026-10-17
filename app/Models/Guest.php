<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Propaganistas\LaravelPhone\Casts\E164PhoneNumberCast;

/**
 * Guest = someone staying by the night (short stays). Only name and phone
 * are needed at check-in; ID details can be added later. A returning guest
 * is matched by phone number.
 *
 * @property string $full_name
 * @property string|null $email
 * @property string|null $id_type
 * @property string|null $id_number
 * @property string|null $nationality
 */
class Guest extends Model
{
    use HasUuids, SoftDeletes, TenantScopedModel;

    public const ID_NIDA = 'nida';

    public const ID_PASSPORT = 'passport';

    public const ID_DRIVING_LICENCE = 'driving_licence';

    public const ID_OTHER = 'other';

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'id_type',
        'id_number',
        'nationality',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'phone' => E164PhoneNumberCast::class.':TZ',
            'id_number' => 'encrypted',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return array<string, string> */
    public static function idTypeOptions(): array
    {
        return [
            self::ID_NIDA => __('NIDA'),
            self::ID_PASSPORT => __('Passport'),
            self::ID_DRIVING_LICENCE => __('Driving licence'),
            self::ID_OTHER => __('Other'),
        ];
    }
}
