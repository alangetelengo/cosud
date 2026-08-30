<?php

namespace App\Notifications;

use App\Models\Courrier;
use App\Models\User;
use App\Services\CourrierNotificationService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
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

        if (config('cosud.courrier_notifications_mail')) {
            $channels[] = 'mail';
        }

        if ($this->doitEnvoyerSms()) {
            $whatsappOk = $notifiable->routeNotificationFor('cosud_whatsapp')
                && app(WhatsAppService::class)->isConfigured();
            if ($whatsappOk) {
                $channels[] = 'cosud_whatsapp';
            }

            $smsOk = $notifiable->routeNotificationFor('cosud_sms')
                && app(SmsService::class)->isConfigured()
                && (! $whatsappOk || (bool) config('cosud.whatsapp.also_sms'));
            if ($smsOk) {
                $channels[] = 'cosud_sms';
            }
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = $this->libelles();

        return (new MailMessage)
            ->subject('COSUD : '.$labels['title'])
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($labels['body'])
            ->line('**Courrier :** n° '.$this->courrier->numeroRegistreComplet().' — '.$this->courrier->objet)
            ->line('**Par :** '.$this->acteur->name)
            ->when($this->detail, fn (MailMessage $mail) => $mail->line('**Détail :** '.$this->detail))
            ->action('Voir le courrier', $this->urlAction())
            ->line('Merci d\'utiliser COSUD.');
    }

    /**
     * @return array{message: string, message_title: string, message_body: string, url: string, courrier_id: int, type: string, detail: ?string}
     */
    public function toArray(object $notifiable): array
    {
        $labels = $this->libelles();
        $numero = $this->courrier->numeroRegistreComplet();
        $objet = trim((string) ($this->courrier->objet ?? ''));

        // Texte affiché dans la cloche / liste : titre actionnable + n° + objet.
        $message = $labels['title'].' — n° '.$numero;
        if ($objet !== '') {
            $message .= ' — '.$objet;
        }

        return [
            'message' => $message,
            'message_title' => $labels['title'],
            'message_body' => $labels['body'],
            'url' => $this->urlAction(),
            'courrier_id' => $this->courrier->id,
            'type' => $this->type,
            'detail' => $this->detail,
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
                'title' => $this->titreEtapeCircuit(),
                'body' => $this->corpsEtapeCircuit(),
            ],
            CourrierNotificationService::REPONSE_A_VALIDER => [
                'title' => 'Réponse à signer',
                'body' => 'La particulière a transmis un courrier de réponse : signez-le ou rejetez-le avec un motif.',
            ],
            CourrierNotificationService::REPONSE_REJETEE => [
                'title' => 'Réponse rejetée',
                'body' => 'Le DG a rejeté le courrier de réponse : corrigez-le et retransmettez-le pour signature.',
            ],
            CourrierNotificationService::REPONSE_VALIDEE_A_CREER => [
                'title' => 'Réponse signée — à expédier',
                'body' => 'Le DG a signé le courrier de réponse : expédiez-le vers le secrétariat destinataire.',
            ],
            CourrierNotificationService::RETARD_TRAITEMENT => [
                'title' => 'Courrier en retard de traitement',
                'body' => 'Un courrier n\'a pas été traité dans les délais : vous pouvez interpeller le responsable.',
            ],
            CourrierNotificationService::RELANCE => [
                'title' => 'Relance DG — courrier en attente',
                'body' => 'Le Directeur Général vous relance pour le traitement d\'un courrier en attente.',
            ],
            CourrierNotificationService::ENTREE_CHEQUE_SUIVI_DEPENSE => [
                'title' => $this->courrier->estModePaiementOv()
                    ? 'Entrée OV — suivi des dépenses'
                    : 'Entrée chèque — suivi des dépenses',
                'body' => $this->courrier->estModePaiementOv()
                    ? 'L’Agent comptable a établi un ordre de virement : inscrivez-le sur la fiche de suivi des paiements.'
                    : 'L’Agent comptable a établi un chèque : inscrivez-le sur la fiche de suivi des paiements.',
            ],
            CourrierNotificationService::FACTURE_ENREGISTREE_DG => [
                'title' => 'Facture prestataire à traiter',
                'body' => 'Une facture / MAD prestataire vient d’être enregistrée : donnez votre Bon pour accord.',
            ],
            CourrierNotificationService::BON_POUR_ACCORD_AC => [
                'title' => $this->courrier->estModePaiementOv()
                    ? 'Bon pour accord — établir l’OV'
                    : 'Bon pour accord — établir le chèque',
                'body' => $this->courrier->estModePaiementOv()
                    ? 'Le DG a donné son Bon pour accord : établissez l’ordre de virement selon ses instructions.'
                    : 'Le DG a donné son Bon pour accord : établissez le chèque selon ses instructions.',
            ],
            default => [
                'title' => 'Courrier — mise à jour',
                'body' => 'Le courrier a été mis à jour.',
            ],
        };
    }

    public function toCosudSms(object $notifiable): string
    {
        $numero = $this->courrier->numeroRegistreComplet();
        $fournisseur = trim((string) ($this->courrier->expediteur_libelle ?? ''));
        $fournisseurCourt = $fournisseur !== '' ? mb_substr($fournisseur, 0, 40) : 'fournisseur';

        $texte = match ($this->type) {
            CourrierNotificationService::FACTURE_ENREGISTREE_DG => 'ACSI – COSUD : Facture prestataire ('.$numero.')'
                .' enregistrée et soumise à votre validation (Bon pour accord). Fournisseur : '.$fournisseurCourt.'.',
            CourrierNotificationService::BON_POUR_ACCORD_AC => $this->texteSmsBonPourAccordAc($numero, $fournisseurCourt),
            default => 'COSUD n°'.$numero.' : action requise sur un courrier.',
        };

        return app(SmsService::class)->sanitizeSmsText($texte);
    }

    public function toCosudWhatsapp(object $notifiable): string
    {
        return $this->toCosudSms($notifiable);
    }

    private function texteSmsBonPourAccordAc(string $numero, string $fournisseurCourt): string
    {
        $instructions = trim((string) ($this->courrier->instructions_dg ?? ''));
        $extrait = $instructions !== '' ? mb_substr($instructions, 0, 80) : 'voir COSUD';
        $action = $this->courrier->estModePaiementOv()
            ? 'etablir un OV'
            : 'editer un cheque';

        return 'COSUD n°'.$numero
            .' : Bon pour accord DG — '.$action.'. Fournisseur : '.$fournisseurCourt
            .'. Instructions : '.$extrait;
    }

    private function doitEnvoyerSms(): bool
    {
        return in_array($this->type, [
            CourrierNotificationService::FACTURE_ENREGISTREE_DG,
            CourrierNotificationService::BON_POUR_ACCORD_AC,
        ], true);
    }

    private function titreEtapeCircuit(): string
    {
        $etape = $this->nomEtapeDepuisDetail();

        return $etape !== null
            ? 'À traiter : '.$etape
            : 'Action requise sur un courrier';
    }

    private function corpsEtapeCircuit(): string
    {
        $etape = $this->nomEtapeDepuisDetail();
        $objet = trim((string) ($this->courrier->objet ?? ''));

        if ($etape !== null && $objet !== '') {
            return 'Le circuit attend votre action à l\'étape « '.$etape.' » pour le courrier « '.$objet.' ».';
        }

        if ($etape !== null) {
            return 'Le circuit attend votre action à l\'étape « '.$etape.' ». Ouvrez le courrier pour traiter.';
        }

        return 'Une étape du circuit courrier attend votre action. Ouvrez le courrier pour traiter.';
    }

    /**
     * Extrait le nom d'étape depuis le détail produit par le moteur
     * (« Étape en cours : Nom — aide | Instructions : … »).
     */
    private function nomEtapeDepuisDetail(): ?string
    {
        if (! is_string($this->detail) || trim($this->detail) === '') {
            return null;
        }

        if (! preg_match('/^Étape en cours\s*:\s*([^|]+)/u', $this->detail, $matches)) {
            return null;
        }

        $partie = trim($matches[1]);
        $segments = preg_split('/\s+[—\-]\s+/u', $partie, 2) ?: [$partie];
        $nom = trim((string) ($segments[0] ?? ''));

        return $nom !== '' ? $nom : null;
    }
}
