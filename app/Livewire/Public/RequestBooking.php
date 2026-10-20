<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\BookingRequest;
use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingRequestReceivedNotification;
use App\Services\FrontDesk\ShortStayService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * The booking box form on a public room page. No sign-in: name and phone
 * are enough. A room let by the night asks for dates and guests (a stay);
 * a room let on a lease asks for a move-in date (an enquiry). Rooms let
 * both ways offer both. The request lands in the workspace's Requests
 * list and staff are told; nothing is booked until they confirm.
 */
class RequestBooking extends Component
{
    /** Requests allowed per visitor and room before a pause. */
    private const MAX_REQUESTS = 5;

    private const DECAY_SECONDS = 3600;

    #[Locked]
    public string $unitId = '';

    public string $kind = BookingRequest::KIND_STAY;

    public string $full_name = '';

    public string $phone = '';

    public string $check_in = '';

    public string $check_out = '';

    public int $guests = 1;

    public string $move_in = '';

    public string $message = '';

    /** Honeypot: hidden from people, filled by bots. */
    public string $website = '';

    public bool $sent = false;

    public function mount(Unit $unit): void
    {
        $this->unitId = $unit->getKey();
        $this->kind = $unit->takesShortStays() ? BookingRequest::KIND_STAY : BookingRequest::KIND_ENQUIRY;
        $this->check_in = today()->toDateString();
        $this->check_out = today()->addDay()->toDateString();
    }

    public function submit(ShortStayService $stays): void
    {
        $unit = $this->unit();

        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $this->validate($this->rules($unit), [], [
            'full_name' => __('name'),
            'check_in' => __('check-in'),
            'check_out' => __('check-out'),
            'move_in' => __('move-in date'),
        ]);

        $phone = $this->normalisedPhone();
        if ($phone === null) {
            $this->addError('phone', __('Enter a valid Tanzanian phone number, e.g. 0712 345 678.'));

            return;
        }

        if ($this->kind === BookingRequest::KIND_STAY) {
            $in = Carbon::parse($this->check_in)->startOfDay();
            $out = Carbon::parse($this->check_out)->startOfDay();

            if ($in->diffInDays($out) > BookingRequest::MAX_NIGHTS) {
                $this->addError('check_out', __('Online requests are for up to :n nights. Message us for longer stays.', ['n' => BookingRequest::MAX_NIGHTS]));

                return;
            }

            if ($unit->leases()->where('status', Lease::STATUS_ACTIVE)->exists() || ! $stays->isAvailable($unit, $in, $out)) {
                $this->addError('check_in', __('Those dates are taken. Try other dates, or message us on WhatsApp.'));

                return;
            }
        }

        $key = 'booking-request:'.$unit->getKey().':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS)) {
            $this->addError('full_name', __('You have sent several requests already. Please wait a little, or message us on WhatsApp.'));

            return;
        }
        RateLimiter::hit($key, self::DECAY_SECONDS);

        $request = BookingRequest::create([
            'unit_id' => $unit->getKey(),
            'kind' => $this->kind,
            'full_name' => trim($this->full_name),
            'phone' => $phone,
            'check_in_date' => $this->kind === BookingRequest::KIND_STAY ? $this->check_in : null,
            'check_out_date' => $this->kind === BookingRequest::KIND_STAY ? $this->check_out : null,
            'move_in_date' => $this->kind === BookingRequest::KIND_ENQUIRY && $this->move_in !== '' ? $this->move_in : null,
            'guests_count' => $this->kind === BookingRequest::KIND_STAY ? $this->guests : 1,
            'message' => trim($this->message) !== '' ? trim($this->message) : null,
            'ip' => request()->ip(),
        ]);

        $this->notifyStaff($request);
        $this->sent = true;
    }

    public function render(): View
    {
        $unit = $this->unit();
        $nights = 0;

        if ($this->kind === BookingRequest::KIND_STAY && $this->check_in !== '' && $this->check_out !== '') {
            try {
                $nights = max(0, (int) Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out), false));
            } catch (Throwable) {
                $nights = 0;
            }
        }

        return view('livewire.public.request-booking', [
            'unit' => $unit,
            'nights' => $nights,
            'total' => $nights > 0 && $unit->nightly_rate ? number_format($nights * $unit->nightly_rate / 100, 0, '.', ',') : null,
            'offersBoth' => $unit->rental_mode === Unit::RENTAL_BOTH,
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(Unit $unit): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', 'in:'.implode(',', $this->allowedKinds($unit))],
        ];

        return $this->kind === BookingRequest::KIND_STAY
            ? $rules + [
                'check_in' => ['required', 'date', 'after_or_equal:today'],
                'check_out' => ['required', 'date', 'after:check_in'],
                'guests' => ['required', 'integer', 'min:1', 'max:20'],
            ]
            : $rules + [
                'move_in' => ['nullable', 'date', 'after_or_equal:today'],
            ];
    }

    /** @return list<string> */
    private function allowedKinds(Unit $unit): array
    {
        return match ($unit->rental_mode) {
            Unit::RENTAL_SHORT_STAY => [BookingRequest::KIND_STAY],
            Unit::RENTAL_BOTH => [BookingRequest::KIND_STAY, BookingRequest::KIND_ENQUIRY],
            default => [BookingRequest::KIND_ENQUIRY],
        };
    }

    private function unit(): Unit
    {
        return Unit::query()->listedPublicly()->findOrFail($this->unitId);
    }

    private function normalisedPhone(): ?string
    {
        try {
            $number = new PhoneNumber(trim($this->phone), 'TZ');

            return $number->isValid() ? $number->formatE164() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function notifyStaff(BookingRequest $request): void
    {
        try {
            User::query()
                ->where('tenant_id', $request->tenant_id)
                ->where('type', User::TYPE_OPERATOR)
                ->where('status', User::STATUS_ACTIVE)
                ->get()
                ->each(fn (User $user) => $user->notify(new BookingRequestReceivedNotification($request)));
        } catch (Throwable $e) {
            Log::warning('Booking request notification failed', ['request_id' => $request->id, 'error' => $e->getMessage()]);
        }
    }
}
