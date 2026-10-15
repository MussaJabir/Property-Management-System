<?php

declare(strict_types=1);

namespace App\Filament\Operator\Concerns;

use App\Models\SuperAdminUser;
use App\Models\User;
use App\Notifications\PlanUpgradeRequestedNotification;
use App\Services\Plans\PlanUsage;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * For list pages of plan-limited records (properties, units, staff): shows
 * usage against the plan under the page title and offers "Request an
 * upgrade" once the client is close to or at the limit.
 */
trait ShowsPlanUsage
{
    /** One of the PlanUsage::PROPERTIES / UNITS / STAFF constants. */
    abstract protected function planResource(): string;

    public function getSubheading(): ?string
    {
        $usage = PlanUsage::forCurrentUser();

        if ($usage === null) {
            return null;
        }

        $resource = $this->planResource();
        $line = $usage->summary($resource);

        if ($usage->isAtLimit($resource) && $usage->isWaived()) {
            return $line.' '.__('Your limit is paused until :date, so you can keep adding for now.', [
                'date' => $usage->client()->limits_waived_until?->format('d/m/Y'),
            ]);
        }

        if ($usage->isAtLimit($resource)) {
            return $line.' '.__('You have reached your plan limit. Everything you already have keeps working. Request an upgrade to add more.');
        }

        if ($usage->isNearLimit($resource)) {
            return $line.' '.__('You are close to your plan limit.');
        }

        return $line;
    }

    protected function requestUpgradeAction(): Action
    {
        return Action::make('requestUpgrade')
            ->label(__('Request an upgrade'))
            ->icon('heroicon-o-arrow-trending-up')
            ->color('warning')
            ->visible(fn (): bool => PlanUsage::forCurrentUser()?->isNearLimit($this->planResource()) ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('Request a bigger plan'))
            ->modalDescription(__('We will let the BJP team know. They will contact you to agree the new plan.'))
            ->modalSubmitActionLabel(__('Send request'))
            ->action(function (): void {
                $usage = PlanUsage::forCurrentUser();
                $user = auth()->user();

                if ($usage === null || ! $user instanceof User) {
                    return;
                }

                $key = 'plan-upgrade-request:'.$usage->client()->getKey();

                if (RateLimiter::tooManyAttempts($key, 1)) {
                    Notification::make()
                        ->info()
                        ->title(__('Request already sent'))
                        ->body(__('The BJP team has your request and will be in touch.'))
                        ->send();

                    return;
                }

                RateLimiter::hit($key, 3600);

                try {
                    NotificationFacade::send(
                        SuperAdminUser::all(),
                        new PlanUpgradeRequestedNotification($usage->client(), $user, $usage->summary($this->planResource())),
                    );
                } catch (Throwable $e) {
                    Log::warning('Plan upgrade request email failed', [
                        'client_id' => $usage->client()->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }

                Notification::make()
                    ->success()
                    ->title(__('Upgrade request sent'))
                    ->body(__('The BJP team will contact you to agree the new plan.'))
                    ->send();
            });
    }
}
