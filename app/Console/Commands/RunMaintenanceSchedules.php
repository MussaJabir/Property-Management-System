<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\MaintenanceSchedule;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

class RunMaintenanceSchedules extends Command
{
    protected $signature = 'maintenance:run-schedules';

    protected $description = 'Open work orders for preventive maintenance that is due.';

    public function handle(): int
    {
        $created = 0;

        foreach (Client::query()->where('status', 'active')->cursor() as $client) {
            try {
                Tenancy::initialize($client);
                app(PermissionRegistrar::class)->setPermissionsTeamId($client->getKey());

                MaintenanceSchedule::query()
                    ->where('is_active', true)
                    ->whereDate('next_due_on', '<=', today())
                    ->each(function (MaintenanceSchedule $schedule) use (&$created): void {
                        $schedule->createWorkOrder();
                        $created++;
                    });
            } catch (Throwable $e) {
                $this->error("[{$client->getKey()}] {$e->getMessage()}");
            } finally {
                Tenancy::end();
                app(PermissionRegistrar::class)->setPermissionsTeamId(null);
            }
        }

        $this->info("Work orders opened: {$created}");

        return self::SUCCESS;
    }
}
