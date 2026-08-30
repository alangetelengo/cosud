<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentValidationResultNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public User $validateur,
        public bool $approuve,
        public ?string $commentaire = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $titre = $this->document->titre ?: $this->document->nom_original;
        $msg = $this->approuve
            ? 'Votre document a été validé.'
            : 'Votre document a été rejeté.';

        $mail = (new MailMessage)
            ->subject($this->approuve ? 'COSUD : Document validé' : 'COSUD : Document rejeté')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($msg)
            ->line('**Document :** '.$titre)
            ->line('**Par :** '.$this->validateur->name);
        if ($this->commentaire) {
            $mail->line('**Commentaire :** '.$this->commentaire);
        }
        $mail->action('Voir les documents', route('documents.index'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $titre = $this->document->titre ?: $this->document->nom_original;
        $action = $this->approuve ? 'validé' : 'rejeté';

        return [
            'message' => sprintf('Document « %s » %s par %s', $titre, $action, $this->validateur->name),
            'message_title' => $this->approuve ? 'Document validé' : 'Document rejeté',
            'url' => route('documents.index'),
            'document_id' => $this->document->id,
        ];
    }
}
