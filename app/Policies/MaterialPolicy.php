<?php

declare(strict_types=1);

namespace App\Policies;

class MaterialPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'maintenance';
}
