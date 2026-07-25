<?php

namespace App\Notifications;

use App\Models\Courrier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class CourrierExpediteurTraiteNotification extends Notification
{
    use Queueable;

    public function __construct(public Courrier $courrier) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }

        if ($notifiable->routeNotificationFor('vonage') && config('services.vonage.key')) {
            $channels[] = 'vonage';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $numero = $this->courrier->numeroRegistreComplet();

        return (new MailMessage)
            ->subject('GED : votre courrier n° '.$numero.' a été traité')
            ->greeting('Bonjour,')
            ->line('Votre courrier n° '.$numero.' a été traité.')
            ->line('**Objet :** '.$this->courrier->objet)
            ->line('Merci de votre confiance.');
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        $numero = $this->courrier->numeroRegistreComplet();

        return (new VonageMessage)
            ->content('GED : votre courrier n° '.$numero.' a été traité.');
    }

    /**
     * @return array{message: string, message_title: string, courrier_id: int, type: string}
     */
    public function toArray(object $notifiable): array
    {
        $numero = $this->courrier->numeroRegistreComplet();

        return [
            'message' => 'Votre courrier n° '.$numero.' a été traité.',
            'message_title' => 'Courrier traité',
            'courrier_id' => $this->courrier->id,
            'type' => 'expediteur_traite',
        ];
    }
}
