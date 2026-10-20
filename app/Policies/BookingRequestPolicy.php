<?php

declare(strict_types=1);

namespace App\Policies;

class BookingRequestPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'frontdesk';
}
