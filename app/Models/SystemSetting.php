<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Convenience static accessor.
     *
     *     SystemSetting::value('platform.maintenance_mode', false);
     */
    public static function value(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);

        return $setting?->value ?? $default;
    }

    /**
     * Convenience static setter.
     */
    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()],
        );
    }
}
