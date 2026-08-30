<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowEtape;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentValidationDemandeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public User $demandeur,
        public WorkflowEtape $etape
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $titre = $this->document->titre ?: $this->document->nom_original;

        return (new MailMessage)
            ->subject('COSUD : Document en attente de validation')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Un document attend votre validation.')
            ->line('**Document :** '.$titre)
            ->line('**Demandé par :** '.$this->demandeur->name)
            ->line('**Étape :** '.$this->etape->nom)
            ->action('Voir les documents', route('documents.index'))
            ->line('Merci d\'utiliser COSUD.');
    }

    public function toArray(object $notifiable): array
    {
        $titre = $this->document->titre ?: $this->document->nom_original;

        return [
            'message' => sprintf(
                'Document « %s » en attente de validation (%s) par %s',
                $titre,
                $this->etape->nom,
                $this->demandeur->name
            ),
            'message_title' => 'Validation demandée',
            'url' => route('documents.index'),
            'document_id' => $this->document->id,
        ];
    }
}
