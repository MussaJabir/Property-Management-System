<?php

declare(strict_types=1);

namespace App\Policies;

class MaintenanceSchedulePolicy extends OperatorResourcePolicy
{
    protected string $domain = 'maintenance';
}
