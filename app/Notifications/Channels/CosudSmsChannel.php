<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

/**
 * Canal SMS COSUD (Wirepick / Infobip) — route on-demand : Notification::route('cosud_sms', $telephone).
 */
class CosudSmsChannel
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toCosudSms')) {
            return;
        }

        /** @var string $message */
        $message = $notification->toCosudSms($notifiable);
        if (! is_string($message) || trim($message) === '') {
            return;
        }

        $to = $notifiable->routeNotificationFor('cosud_sms', $notification);
        if (! is_string($to) || trim($to) === '') {
            return;
        }

        $this->sms->send($to, $message);
    }
}
