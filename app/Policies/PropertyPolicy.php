<?php

declare(strict_types=1);

namespace App\Policies;

class PropertyPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'inventory';
}
