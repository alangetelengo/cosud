<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DossierPartageRemovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Dossier $dossier,
        public User $retirePar
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('COSUD : Un partage de dossier a ete retire')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre acces a un dossier partage a ete retire.')
            ->line('**Dossier :** '.$this->dossier->nom)
            ->line('**Retire par :** '.$this->retirePar->name)
            ->action('Voir mes dossiers', route('dossiers.index'))
            ->line('Merci d\'utiliser COSUD.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message_title' => 'Partage de dossier retire',
            'message' => sprintf(
                'Votre acces au dossier « %s » a ete retire par %s.',
                $this->dossier->nom,
                $this->retirePar->name
            ),
            'url' => route('dossiers.index', ['filtre' => 'partages']),
            'dossier_id' => $this->dossier->id,
            'retire_par_id' => $this->retirePar->id,
        ];
    }
}
