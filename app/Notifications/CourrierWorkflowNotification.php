<?php

namespace App\Notifications;

use App\Models\Courrier;
use App\Models\User;
use App\Services\CourrierNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourrierWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Courrier $courrier,
        public User $acteur,
        public string $type,
        public ?string $detail = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('ged.courrier_notifications_mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = $this->libelles();

        return (new MailMessage)
            ->subject('GED : '.$labels['title'])
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($labels['body'])
            ->line('**Courrier :** n° '.$this->courrier->numeroRegistreComplet().' — '.$this->courrier->objet)
            ->line('**Par :** '.$this->acteur->name)
            ->when($this->detail, fn (MailMessage $mail) => $mail->line('**Détail :** '.$this->detail))
            ->action('Voir le courrier', $this->urlAction())
            ->line('Merci d\'utiliser GED.');
    }

    /**
     * @return array{message: string, message_title: string, url: string, courrier_id: int, type: string}
     */
    public function toArray(object $notifiable): array
    {
        $labels = $this->libelles();

        return [
            'message' => $labels['body'].' — n° '.$this->courrier->numeroRegistreComplet(),
            'message_title' => $labels['title'],
            'url' => $this->urlAction(),
            'courrier_id' => $this->courrier->id,
            'type' => $this->type,
        ];
    }

    /**
     * Expédition interne : le destinataire doit réceptionner (pas consulter le départ émetteur).
     */
    private function urlAction(): string
    {
        if ($this->type === CourrierNotificationService::EXPEDITION) {
            return route('courriers.a-recevoir', ['highlight' => $this->courrier->id]);
        }

        return route('courriers.show', $this->courrier);
    }

    /**
     * @return array{title: string, body: string}
     */
    private function libelles(): array
    {
        return match ($this->type) {
            CourrierNotificationService::TRANSMISSION_DIRECTEUR => [
                'title' => 'Courrier départ à valider',
                'body' => 'Un courrier départ vous a été transmis pour validation.',
            ],
            CourrierNotificationService::VALIDE_POUR_ENVOI => [
                'title' => 'Courrier validé pour envoi',
                'body' => 'Votre courrier départ a été validé : vous pouvez l\'expédier vers le secrétariat destinataire.',
            ],
            CourrierNotificationService::RENVOI_CORRECTION => [
                'title' => 'Courrier à corriger',
                'body' => 'Le directeur a renvoyé le courrier pour correction.',
            ],
            CourrierNotificationService::ANNULATION => [
                'title' => 'Courrier annulé',
                'body' => 'Le courrier départ a été annulé.',
            ],
            CourrierNotificationService::EXPEDITION => [
                'title' => 'Courrier départ à réceptionner',
                'body' => 'Un courrier départ interne a été expédié vers votre secrétariat.',
            ],
            CourrierNotificationService::RECEPTION_REFUSEE => [
                'title' => 'Réception refusée',
                'body' => 'Le secrétariat destinataire a refusé la réception du courrier.',
            ],
            CourrierNotificationService::ENREGISTREMENT_ARRIVEE => [
                'title' => 'Nouveau courrier arrivée',
                'body' => 'Un courrier arrivée a été enregistré au secrétariat DG.',
            ],
            CourrierNotificationService::MISE_EN_PARAPHEUR => [
                'title' => 'Courrier en parapheur',
                'body' => 'Un courrier arrivée a été placé en parapheur : vos instructions sont attendues.',
            ],
            CourrierNotificationService::ORIENTATION => [
                'title' => 'Courrier orienté',
                'body' => 'Un courrier arrivée vous a été orienté avec des instructions de la direction.',
            ],
            CourrierNotificationService::DOSSIER_CONFIE => [
                'title' => 'Dossier confié — à traiter',
                'body' => 'Le DG vous a confié ce dossier avec des instructions à respecter.',
            ],
            CourrierNotificationService::INSTRUCTION_PARTICULIERE => [
                'title' => 'Préparer un élément de réponse',
                'body' => 'Le DG vous demande de préparer un élément de réponse pour validation.',
            ],
            CourrierNotificationService::ETAPE_CIRCUIT => [
                'title' => 'Courrier — étape à traiter',
                'body' => 'Une étape du circuit courrier vous concerne.',
            ],
            CourrierNotificationService::REPONSE_A_VALIDER => [
                'title' => 'Projet de réponse à valider',
                'body' => 'La particulière a soumis un projet de réponse : validez-le ou rejetez-le avec un motif.',
            ],
            CourrierNotificationService::REPONSE_REJETEE => [
                'title' => 'Projet de réponse rejeté',
                'body' => 'Le DG a rejeté le projet de réponse soumis : corrigez-le et resoumettez-le.',
            ],
            CourrierNotificationService::REPONSE_VALIDEE_A_CREER => [
                'title' => 'Projet de réponse validé — créer le départ',
                'body' => 'Le DG a validé le projet de réponse : créez le courrier départ en brouillon (destinataire selon ses indications).',
            ],
            CourrierNotificationService::RETARD_TRAITEMENT => [
                'title' => 'Courrier en retard de traitement',
                'body' => 'Un courrier n\'a pas été traité dans les délais : vous pouvez interpeller le responsable.',
            ],
            CourrierNotificationService::RELANCE => [
                'title' => 'Relance DG — courrier en attente',
                'body' => 'Le Directeur Général vous relance pour le traitement d\'un courrier en attente.',
            ],
            default => [
                'title' => 'Courrier — mise à jour',
                'body' => 'Le courrier a été mis à jour.',
            ],
        };
    }
}
