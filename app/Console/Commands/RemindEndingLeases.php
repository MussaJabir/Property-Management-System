<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Lease;
use App\Models\User;
use App\Notifications\LeaseEndingNotification;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

/**
 * Daily: tell owners and managers about leases ending in 90, 60 and 30 days,
 * and the renter at 60 days. Leases already renewed are skipped. Also ends
 * leases that are past their end date once their renewal has started.
 */
class RemindEndingLeases extends Command
{
    protected $signature = 'leases:remind-ending';

    protected $description = 'Remind staff and renters about leases ending soon; end leases whose renewal has started.';

    /** Days before the end date when the renter hears about it. */
    private const RENTER_DAYS = 60;

    public function handle(): int
    {
        $reminded = 0;
        $ended = 0;

        foreach (Client::query()->where('status', 'active')->cursor() as $client) {
            try {
                Tenancy::initialize($client);
                app(PermissionRegistrar::class)->setPermissionsTeamId($client->getKey());

                $staff = User::query()
                    ->where('tenant_id', $client->getKey())
                    ->where('type', User::TYPE_OPERATOR)
                    ->where('status', User::STATUS_ACTIVE)
                    ->get()
                    ->filter(fn (User $user): bool => $user->hasAnyRole(['owner', 'manager']));

                $dates = collect(Lease::ENDING_REMINDER_DAYS)->map(fn (int $days): string => today()->addDays($days)->toDateString());

                Lease::query()
                    ->active()
                    ->whereIn('end_date', $dates->all())
                    ->whereDoesntHave('renewal')
                    ->with(['renter.user', 'unit.property'])
                    ->each(function (Lease $lease) use ($staff, &$reminded): void {
                        $days = (int) $lease->daysLeft();
                        $staff->each(fn (User $user) => $user->notify(new LeaseEndingNotification($lease, $days)));
                        $renterUser = $lease->renter?->user;
                        if ($days === self::RENTER_DAYS && $renterUser instanceof User) {
                            $renterUser->notify(new LeaseEndingNotification($lease, $days, forRenter: true));
                        }
                        $reminded++;
                    });

                // The old lease steps aside once its renewal is running.
                Lease::query()
                    ->active()
                    ->whereDate('end_date', '<', today())
                    ->whereHas('renewal', fn ($q) => $q->where('status', Lease::STATUS_ACTIVE)->whereDate('start_date', '<=', today()))
                    ->each(function (Lease $lease) use (&$ended): void {
                        $lease->end();
                        $ended++;
                    });
            } catch (Throwable $e) {
                $this->error("[{$client->getKey()}] {$e->getMessage()}");
            } finally {
                Tenancy::end();
                app(PermissionRegistrar::class)->setPermissionsTeamId(null);
            }
        }

        $this->info("Reminded about {$reminded} lease(s); ended {$ended} renewed lease(s).");

        return self::SUCCESS;
    }
}
