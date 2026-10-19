<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Selcom couldn't be asked: the workspace hasn't set it up, the network
 * failed, or the reply wasn't Selcom's JSON. A reply that says FAIL is not
 * this; it comes back as a SelcomResponse.
 */
class SelcomUnavailable extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(__('Selcom payments are not set up for this workspace.'));
    }

    public static function noAddress(string $mode): self
    {
        return new self(__('The Selcom :mode address is not configured on the server.', ['mode' => $mode]));
    }

    public static function unreachable(string $reason): self
    {
        return new self(__('Could not reach Selcom: :reason', ['reason' => $reason]));
    }

    public static function badReply(int $status): self
    {
        return new self(__('Selcom sent an unexpected reply (HTTP :status).', ['status' => $status]));
    }
}
