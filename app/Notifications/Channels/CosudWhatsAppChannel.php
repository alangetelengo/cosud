<?php

namespace App\Notifications\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

/**
 * Canal WhatsApp COSUD (Infobip) — route on-demand : Notification::route('cosud_whatsapp', $telephone).
 */
class CosudWhatsAppChannel
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toCosudWhatsapp')) {
            return;
        }

        /** @var string $message */
        $message = $notification->toCosudWhatsapp($notifiable);
        if (! is_string($message) || trim($message) === '') {
            return;
        }

        $to = $notifiable->routeNotificationFor('cosud_whatsapp', $notification);
        if (! is_string($to) || trim($to) === '') {
            return;
        }

        $this->whatsapp->send($to, $message);
    }
}
