<?php

namespace App\Notifications;

use App\Models\Courrier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class CourrierFournisseurRecouvrementNotification extends Notification
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
            ->subject('GED : chèque signé — courrier n° '.$numero.' (recouvrement)')
            ->greeting('Bonjour,')
            ->line('Le chèque relatif à votre facture (courrier n° '.$numero.') a été signé.')
            ->line('**Objet :** '.$this->courrier->objet)
            ->line('Vous pouvez procéder au recouvrement auprès de l’ACSI.')
            ->line('Merci de votre confiance.');
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        $numero = $this->courrier->numeroRegistreComplet();

        return (new VonageMessage)
            ->content('GED : chèque signé pour le courrier n° '.$numero.'. Vous pouvez procéder au recouvrement.');
    }

    /**
     * @return array{message: string, message_title: string, courrier_id: int, type: string}
     */
    public function toArray(object $notifiable): array
    {
        $numero = $this->courrier->numeroRegistreComplet();

        return [
            'message' => 'Chèque signé pour le courrier n° '.$numero.' — recouvrement possible.',
            'message_title' => 'Chèque signé — recouvrement',
            'courrier_id' => $this->courrier->id,
            'type' => 'fournisseur_recouvrement',
        ];
    }
}
