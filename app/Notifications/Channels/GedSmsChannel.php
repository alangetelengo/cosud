<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

/**
 * Canal SMS GED (Wirepick / Infobip) — route on-demand : Notification::route('ged_sms', $telephone).
 */
class GedSmsChannel
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toGedSms')) {
            return;
        }

        /** @var string $message */
        $message = $notification->toGedSms($notifiable);
        if (! is_string($message) || trim($message) === '') {
            return;
        }

        $to = $notifiable->routeNotificationFor('ged_sms', $notification);
        if (! is_string($to) || trim($to) === '') {
            return;
        }

        $this->sms->send($to, $message);
    }
}
