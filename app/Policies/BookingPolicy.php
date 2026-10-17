<?php

declare(strict_types=1);

namespace App\Policies;

class BookingPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'frontdesk';
}
