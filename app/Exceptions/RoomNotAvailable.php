<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a booking would overlap another live booking on the same unit,
 * or the unit can't take short stays right now. The message is shown to the
 * receptionist as is.
 */
class RoomNotAvailable extends RuntimeException
{
    public static function overlapping(string $unitCode): self
    {
        return new self(__(':unit is already booked for some of those nights.', ['unit' => $unitCode]));
    }

    public static function leased(string $unitCode): self
    {
        return new self(__(':unit is let on a lease and can\'t be booked.', ['unit' => $unitCode]));
    }

    public static function notShortStay(string $unitCode): self
    {
        return new self(__(':unit isn\'t set up for short stays.', ['unit' => $unitCode]));
    }
}
