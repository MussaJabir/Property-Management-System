<?php

declare(strict_types=1);

namespace App\Services\FrontDesk;

use App\Exceptions\RoomNotAvailable;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;
use RuntimeException;
use Throwable;

/**
 * Books short stays and keeps their bill in step.
 *
 * book() creates the booking and its invoice in one go (rate × nights,
 * issued and due on arrival day), optionally checking the guest in at once
 * (walk-ins). extend() moves the check-out date later and bills the extra
 * nights on the same invoice. Overlaps are refused by Postgres; that error
 * comes back as RoomNotAvailable with a message fit for the receptionist.
 *
 * Callers must run inside the client's tenancy.
 */
class ShortStayService
{
    /**
     * @param  Guest|array{full_name: string, phone: string, email?: string|null}  $guest
     */
    public function book(
        Unit $unit,
        Guest|array $guest,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $nightlyRate = null,
        int $guestsCount = 1,
        bool $checkInNow = false,
        ?string $notes = null,
    ): Booking {
        $checkIn = $checkIn->copy()->startOfDay();
        $checkOut = $checkOut->copy()->startOfDay();

        if ($checkOut->lte($checkIn)) {
            throw new InvalidArgumentException(__('Check-out must be at least one night after check-in.'));
        }

        $this->ensureBookable($unit, $checkIn, $checkOut);

        $booking = $this->guardOverlap($unit, function () use ($unit, $guest, $checkIn, $checkOut, $nightlyRate, $guestsCount, $checkInNow, $notes): Booking {
            return DB::transaction(function () use ($unit, $guest, $checkIn, $checkOut, $nightlyRate, $guestsCount, $checkInNow, $notes): Booking {
                $guest = $guest instanceof Guest ? $guest : $this->findOrCreateGuest($guest);

                $booking = Booking::create([
                    'unit_id' => $unit->id,
                    'guest_id' => $guest->id,
                    'check_in_date' => $checkIn->toDateString(),
                    'check_out_date' => $checkOut->toDateString(),
                    'nightly_rate' => $nightlyRate ?? (int) $unit->nightly_rate,
                    'currency' => $unit->rent_currency ?: 'TZS',
                    'status' => Booking::STATUS_RESERVED,
                    'guests_count' => max(1, $guestsCount),
                    'created_by_user_id' => auth()->id(),
                    'notes' => $notes,
                ]);
                $booking->setRelation('unit', $unit);

                $this->createInvoice($booking);

                if ($checkInNow) {
                    $booking->checkIn();
                }

                return $booking;
            });
        });

        $booking->invoice?->issue();

        return $booking->refresh();
    }

    /** Stay longer: move check-out later and bill the extra nights. */
    public function extend(Booking $booking, Carbon $newCheckOut): Booking
    {
        $newCheckOut = $newCheckOut->copy()->startOfDay();

        if (! $booking->isLive()) {
            throw new RuntimeException(__('Only a current booking can be extended.'));
        }

        if ($newCheckOut->lte($booking->check_out_date)) {
            throw new InvalidArgumentException(__('Pick a check-out date after :date.', ['date' => $booking->check_out_date->format('d/m/Y')]));
        }

        $unit = $booking->unit;
        if ($unit === null) {
            throw new RuntimeException(__('This booking has no unit.'));
        }

        $this->guardOverlap($unit, function () use ($booking, $newCheckOut): void {
            DB::transaction(function () use ($booking, $newCheckOut): void {
                $from = $booking->check_out_date->copy();
                $extraNights = (int) $from->diffInDays($newCheckOut);

                $booking->check_out_date = $newCheckOut;
                $booking->save();

                $invoice = $booking->invoice;
                if ($invoice === null) {
                    return;
                }

                $invoice->items()->create([
                    'description' => __('Extra nights :from – :to', [
                        'from' => $from->format('d/m/Y'),
                        'to' => $newCheckOut->format('d/m/Y'),
                    ]),
                    'type' => InvoiceItem::TYPE_RENT,
                    'quantity' => $extraNights,
                    'unit_price' => $booking->nightly_rate,
                ]);

                $invoice->refresh();
                $invoice->update(['billing_period_end' => $newCheckOut->toDateString()]);
                // A paid bill is open again for the extra nights.
                $invoice->recomputeStatus();
            });
        });

        return $booking->refresh();
    }

    /**
     * Is the unit free for every night in [in, out)? Ignores $except (the
     * booking being changed).
     */
    public function isAvailable(Unit $unit, Carbon $in, Carbon $out, ?Booking $except = null): bool
    {
        return ! Booking::query()
            ->overlapping($unit->id, $in, $out)
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except?->getKey()))
            ->exists();
    }

    /** @param  array{full_name: string, phone: string, email?: string|null}  $data */
    public function findOrCreateGuest(array $data): Guest
    {
        try {
            $phone = (string) (new PhoneNumber($data['phone'], 'TZ'))->formatE164();
        } catch (Throwable) {
            $phone = $data['phone'];
        }

        $guest = Guest::query()->where('phone', $phone)->first();

        if ($guest !== null) {
            // Same phone, name typed afresh: keep the record, refresh the name.
            $guest->fill(array_filter([
                'full_name' => $data['full_name'],
                'email' => $data['email'] ?? null,
            ]))->save();

            return $guest;
        }

        return Guest::create([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
        ]);
    }

    private function ensureBookable(Unit $unit, Carbon $in, Carbon $out): void
    {
        if (! $unit->takesShortStays()) {
            throw RoomNotAvailable::notShortStay($unit->code);
        }

        if ($unit->leases()->where('status', Lease::STATUS_ACTIVE)->exists()) {
            throw RoomNotAvailable::leased($unit->code);
        }

        if (! $this->isAvailable($unit, $in, $out)) {
            throw RoomNotAvailable::overlapping($unit->code);
        }
    }

    private function createInvoice(Booking $booking): Invoice
    {
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'status' => Invoice::STATUS_DRAFT,
            'currency' => $booking->currency,
            'billing_period_start' => $booking->check_in_date->toDateString(),
            'billing_period_end' => $booking->check_out_date->toDateString(),
            'due_date' => $booking->check_in_date->toDateString(),
        ]);

        $invoice->items()->create([
            'description' => __(':unit · :nights :from – :to', [
                'unit' => $booking->unit !== null ? $booking->unit->code : '',
                'nights' => trans_choice(':count night|:count nights', $booking->nights()),
                'from' => $booking->check_in_date->format('d/m/Y'),
                'to' => $booking->check_out_date->format('d/m/Y'),
            ]),
            'type' => InvoiceItem::TYPE_RENT,
            'quantity' => $booking->nights(),
            'unit_price' => $booking->nightly_rate,
        ]);

        return $invoice;
    }

    /**
     * Run $work, turning Postgres' overlap refusal (bookings_no_overlap,
     * SQLSTATE 23P01) into RoomNotAvailable.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function guardOverlap(Unit $unit, callable $work): mixed
    {
        try {
            return $work();
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23P01') {
                throw RoomNotAvailable::overlapping($unit->code);
            }

            throw $e;
        }
    }
}
