<?php

declare(strict_types=1);

namespace App\Policies;

class MaintenanceRequestPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'maintenance';
}
