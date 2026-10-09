<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\User;
use App\Services\Admin\OperatorInvite;
use Filament\Notifications\Notification;

/**
 * The toast shown after an operator invite is issued. It reports what really
 * happened to the email and always carries the link, so a failed send can be
 * recovered by sharing the link directly.
 */
class InviteNotice
{
    public static function send(User $user, OperatorInvite $invite): void
    {
        $notification = $invite->emailSent
            ? Notification::make()
                ->success()
                ->title(__('Activation invite emailed'))
                ->body(__('Emailed to :email. To share it directly, copy this link:', ['email' => $user->email])."\n\n".$invite->url)
            : Notification::make()
                ->warning()
                ->title(__('Invite email could not be sent'))
                ->body(__('The email to :email failed, so nothing has reached them. Copy this link and send it to them yourself:', ['email' => $user->email])."\n\n".$invite->url);

        $notification->persistent()->send();
    }
}
