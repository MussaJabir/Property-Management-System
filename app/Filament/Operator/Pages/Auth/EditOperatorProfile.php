<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages\Auth;

use Filament\Auth\Pages\EditProfile;

/**
 * Operator profile page. Identical to Filament's default except we redirect
 * to the panel dashboard after save instead of reloading the profile page.
 *
 * Particularly relevant for force-change-password flow: once the user picks
 * a new password the ForceOperatorPasswordChange middleware should release
 * them, and they should land on the dashboard rather than the form they
 * just submitted.
 */
class EditOperatorProfile extends EditProfile
{
    protected function getRedirectUrl(): ?string
    {
        return filament()->getUrl();
    }
}
