<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BookingRequest;
use App\Models\BookingRequestNote;
use App\Models\Client;
use App\Services\Sms\BeemSmsSender;
use App\Support\RequestAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Texts the landlord's alert numbers about a new request from the website:
 * one SMS (160 characters at most) with a short link that opens it. Sent
 * only when the workspace switched SMS alerts on; does nothing while Beem
 * isn't configured on the server.
 */
class SendRequestSmsAlert implements ShouldQueue
{
    use Queueable;

    public const MAX_LENGTH = 160;

    public function __construct(public string $requestId) {}

    public function handle(BeemSmsSender $sms): void
    {
        $request = BookingRequest::query()->withoutGlobalScopes()->with('unit.property')->find($this->requestId);
        $client = $request !== null ? Client::find($request->tenant_id) : null;

        if ($request === null || $client === null || ! RequestAlerts::smsEnabled($client)) {
            return;
        }

        $message = self::message($request);
        $sent = array_values(array_filter(
            RequestAlerts::smsNumbers($client),
            fn (string $number): bool => $sms->send($number, $message),
        ));

        if ($sent !== []) {
            $request->log(BookingRequestNote::TYPE_ALERT, __('SMS sent to :numbers', [
                'numbers' => implode(', ', array_map(RequestAlerts::display(...), $sent)),
            ]));
        }
    }

    /** "New request: Neema Juma (0712 345 678) wants Room 2 · Kariakoo Heights, moving in around 01/12. Open: https://…/r/8f3k1a2b" */
    public static function message(BookingRequest $request): string
    {
        $wants = $request->isStay()
            ? __(':nights from :date', [
                'nights' => trans_choice(':count night|:count nights', $request->nights()),
                'date' => $request->check_in_date?->format('d/m'),
            ])
            : ($request->move_in_date !== null
                ? __('moving in around :date', ['date' => $request->move_in_date->format('d/m')])
                : __('asking about it'));

        $link = route('requests.short', ['code' => $request->shortCode()]);
        $name = Str::limit($request->full_name, 30, '');
        $room = $request->unit?->publicTitle() ?? '';

        $build = fn (string $room): string => __('New request: :name (:phone) wants :room, :wants. Open: :link', [
            'name' => $name,
            'phone' => RequestAlerts::display($request->phone),
            'room' => $room,
            'wants' => $wants,
            'link' => $link,
        ]);

        // Too long for one SMS: shorten the room name, never the link.
        $over = mb_strlen($build($room)) - self::MAX_LENGTH;

        return $over > 0 ? $build(Str::limit($room, max(4, mb_strlen($room) - $over - 1), '…')) : $build($room);
    }
}
