<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

/**
 * Envoi synchrone (comme Progcaisse) : pas de queue worker requis.
 */
class DocumentDeposeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public User $depositor
    ) {}

    /**
     * Canaux : 1) Mail (Gmail), 2) Database (in-app), 3) SMS si dossier important.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        $dossier = $this->document->dossier;
        $dossierImportant = $dossier && $dossier->isImportant();
        $aUnTelephone = ! empty($notifiable->telephone);
        $vonageConfigure = ! empty(config('services.vonage.key'));

        if ($dossierImportant && $aUnTelephone && $vonageConfigure) {
            $channels[] = 'vonage';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $titre = $this->document->titre ?: $this->document->nom_original;
        $dossier = $this->document->dossier?->chemin_complet ?? 'Sans dossier';

        return (new MailMessage)
            ->subject('GED : Nouveau document déposé')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Un nouveau document a été déposé dans GED.')
            ->line('**Document :** ' . $titre)
            ->line('**Déposé par :** ' . $this->depositor->name)
            ->line('**Dossier :** ' . $dossier)
            ->action('Voir les documents', route('documents.index'))
            ->line('Merci d\'utiliser GED.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $titre = $this->document->titre ?: $this->document->nom_original;

        return [
            'message' => sprintf(
                'Nouveau document déposé : « %s » par %s',
                $titre,
                $this->depositor->name
            ),
            'message_title' => 'Document déposé',
            'url' => route('documents.index'),
            'document_id' => $this->document->id,
        ];
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        $titre = \Illuminate\Support\Str::limit($this->document->titre ?: $this->document->nom_original, 25);

        return (new VonageMessage)
            ->content("GED: Doc « {$titre} » déposé par {$this->depositor->name}. Dossier important.");
    }
}
