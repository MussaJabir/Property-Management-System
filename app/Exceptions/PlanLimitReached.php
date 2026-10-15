<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when something tries to create a property, unit or staff account
 * past the client's plan limit. The panels hide "create" before this point;
 * this is the safety net for every other path.
 */
class PlanLimitReached extends RuntimeException
{
    public static function for(string $resource, int $limit): self
    {
        return new self("This workspace has reached its plan limit of {$limit} {$resource}.");
    }
}
