<?php

declare(strict_types=1);

namespace App\Policies;

class LocationPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'inventory';
}
