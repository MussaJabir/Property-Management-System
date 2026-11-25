<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\RentDueReminderNotification;
use App\Support\RentReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

class RemindRentDue extends Command
{
    protected $signature = 'billing:remind-due';

    protected $description = 'Remind renters a few days before an unpaid invoice is due (once per invoice).';

    public function handle(): int
    {
        $sent = 0;

        foreach (Client::query()->where('status', 'active')->cursor() as $client) {
            $days = RentReminders::daysFor($client);
            if ($days <= 0) {
                continue;
            }

            try {
                Tenancy::initialize($client);

                Invoice::query()
                    ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
                    ->whereNull('due_reminder_sent_at')
                    ->whereDate('due_date', '>', today())
                    ->whereDate('due_date', '<=', today()->addDays($days))
                    ->with('lease.renter.user')
                    ->each(function (Invoice $invoice) use (&$sent): void {
                        $renter = $invoice->lease?->renter;
                        $user = $renter?->user;

                        if ($user instanceof User) {
                            $user->notify(new RentDueReminderNotification($invoice));
                        } elseif (filled($renter?->email)) {
                            Notification::route('mail', $renter->email)->notify(new RentDueReminderNotification($invoice));
                        } else {
                            return; // No way to reach them; try again tomorrow in case an email is added.
                        }

                        $invoice->forceFill(['due_reminder_sent_at' => now()])->saveQuietly();
                        $sent++;
                    });
            } catch (Throwable $e) {
                $this->error("[{$client->slug}] {$e->getMessage()}");
            } finally {
                Tenancy::end();
            }
        }

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
