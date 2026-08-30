<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DossierPartageNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{lecture:bool,ecriture:bool,suppression:bool}  $droits
     */
    public function __construct(
        public Dossier $dossier,
        public User $partagePar,
        public int $nombreDossiersPartages = 1,
        public array $droits = ['lecture' => true, 'ecriture' => false, 'suppression' => false],
        public ?string $dateExpiration = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $droits = $this->droitsLibelle();
        $nb = max(1, (int) $this->nombreDossiersPartages);

        $mail = (new MailMessage)
            ->subject('COSUD : Un dossier vous a ete partage')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Un dossier vous a ete partage sur COSUD.')
            ->line('**Dossier :** '.$this->dossier->nom)
            ->line('**Partage par :** '.$this->partagePar->name)
            ->line('**Droits accordes :** '.$droits)
            ->line('**Portee :** '.$nb.' dossier(s)')
            ->action('Ouvrir le dossier', route('dossiers.show', $this->dossier));

        if ($this->dateExpiration) {
            $mail->line('**Expiration :** '.$this->dateExpiration);
        }

        return $mail->line('Merci d\'utiliser COSUD.');
    }

    public function toArray(object $notifiable): array
    {
        $nb = max(1, (int) $this->nombreDossiersPartages);

        return [
            'message_title' => 'Nouveau partage de dossier',
            'message' => sprintf(
                'Le dossier « %s » vous a ete partage par %s (droits : %s, portee : %d dossier(s)).',
                $this->dossier->nom,
                $this->partagePar->name,
                $this->droitsLibelle(),
                $nb
            ),
            'url' => route('dossiers.show', $this->dossier),
            'dossier_id' => $this->dossier->id,
            'partage_par_id' => $this->partagePar->id,
            'nombre_dossiers_partages' => $nb,
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
