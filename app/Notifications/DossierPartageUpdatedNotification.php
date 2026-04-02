<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DossierPartageUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{lecture:bool,ecriture:bool,suppression:bool}  $droits
     */
    public function __construct(
        public Dossier $dossier,
        public User $modifiePar,
        public array $droits = ['lecture' => true, 'ecriture' => false, 'suppression' => false],
        public ?string $dateExpiration = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('GED : Vos droits de partage ont ete mis a jour')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Vos droits sur un dossier partage ont ete modifies.')
            ->line('**Dossier :** '.$this->dossier->nom)
            ->line('**Modifie par :** '.$this->modifiePar->name)
            ->line('**Nouveaux droits :** '.$this->droitsLibelle())
            ->action('Ouvrir le dossier', route('dossiers.show', $this->dossier));

        if ($this->dateExpiration) {
            $mail->line('**Expiration :** '.$this->dateExpiration);
        }

        return $mail->line('Merci d\'utiliser GED.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message_title' => 'Partage de dossier mis a jour',
            'message' => sprintf(
                'Vos droits sur le dossier « %s » ont ete modifies par %s (droits : %s).',
                $this->dossier->nom,
                $this->modifiePar->name,
                $this->droitsLibelle()
            ),
            'url' => route('dossiers.show', $this->dossier),
            'dossier_id' => $this->dossier->id,
            'modifie_par_id' => $this->modifiePar->id,
            'droits' => $this->droits,
            'date_expiration' => $this->dateExpiration,
        ];
    }

    private function droitsLibelle(): string
    {
        $labels = [];
        if (! empty($this->droits['lecture'])) {
            $labels[] = 'lecture';
        }
        if (! empty($this->droits['ecriture'])) {
            $labels[] = 'ecriture';
        }
        if (! empty($this->droits['suppression'])) {
            $labels[] = 'suppression';
        }

        return $labels === [] ? 'aucun' : implode(', ', $labels);
    }
}
