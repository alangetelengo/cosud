<?php

namespace App\Notifications;

use App\Models\Courrier;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fournisseur / prestataire : pièce de paiement signée (chèque ou OV).
 */
class CourrierFournisseurRecouvrementNotification extends Notification
{
    use Queueable;

    public function __construct(public Courrier $courrier)
    {
        $this->courrier->loadMissing(['suiviPaiement']);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }

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

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->courrier->estModePaiementOv()) {
            return (new MailMessage)
                ->subject('COSUD : dossier n° '.$this->numero().' — ordre de virement transmis à la banque')
                ->greeting('Bonjour,')
                ->line('**État de votre dossier :** l’ordre de virement relatif à votre facture (courrier n° '.$this->numero().') a été **signé** et transmis à la banque.')
                ->line('**Objet :** '.$this->objet())
                ->line('**Référence OV :** '.$this->numeroPiece())
                ->line('**Banque :** '.$this->banque())
                ->line('Merci de votre confiance.')
                ->salutation('L’équipe COSUD — '.config('app.name'));
        }

        return (new MailMessage)
            ->subject('COSUD : dossier n° '.$this->numero().' — chèque signé, recouvrement possible')
            ->greeting('Bonjour,')
            ->line('**État de votre dossier :** le chèque relatif à votre facture (courrier n° '.$this->numero().') a été **signé** par la Direction.')
            ->line('**Objet :** '.$this->objet())
            ->line('**Ce que cela signifie :** le paiement est autorisé ; votre dossier est prêt pour le recouvrement.')
            ->line('**Ce que vous devez faire :** présentez-vous auprès de l’ACSI (ou contactez le service comptable) pour procéder au **recouvrement** du chèque, en rappelant le n° '.$this->numero().'.')
            ->line('Merci de votre confiance.')
            ->salutation('L’équipe COSUD — '.config('app.name'));
    }

    public function toCosudSms(object $notifiable): string
    {
        if ($this->courrier->estModePaiementOv()) {
            $ets = $this->libelleFournisseurCourt();
            $ref = $this->numeroPiece();
            $banque = $this->banque();

            return app(SmsService::class)->sanitizeSmsText(
                $ets.' votre ordre de virement '.$ref.' a ete envoye a '.$banque.' banque.'
            );
        }

        return 'COSUD n°'.$this->numero().' : chèque SIGNÉ. '
            .'Présentez-vous à l’ACSI pour le RECOUVREMENT (rappeler ce n°).';
    }

    public function toCosudWhatsapp(object $notifiable): string
    {
        return $this->toCosudSms($notifiable);
    }

    /**
     * @return array{message: string, message_title: string, courrier_id: int, type: string}
     */
    public function toArray(object $notifiable): array
    {
        if ($this->courrier->estModePaiementOv()) {
            return [
                'message' => 'Dossier n° '.$this->numero().' : OV '.$this->numeroPiece().' envoyé à '.$this->banque().'.',
                'message_title' => 'OV signé — transmis à la banque',
                'courrier_id' => $this->courrier->id,
                'type' => 'fournisseur_recouvrement',
            ];
        }

        return [
            'message' => 'Dossier n° '.$this->numero().' : chèque signé — présentez-vous à l’ACSI pour le recouvrement.',
            'message_title' => 'Chèque signé — à recouvrer',
            'courrier_id' => $this->courrier->id,
            'type' => 'fournisseur_recouvrement',
        ];
    }

    protected function numero(): string
    {
        return $this->courrier->numeroRegistreComplet();
    }

    protected function objet(): string
    {
        $objet = trim((string) ($this->courrier->objet ?? ''));

        return $objet !== '' ? $objet : '—';
    }

    protected function numeroPiece(): string
    {
        $ref = trim((string) ($this->courrier->suiviPaiement?->numero_piece ?? ''));

        return $ref !== '' ? $ref : '—';
    }

    protected function banque(): string
    {
        $banque = trim((string) ($this->courrier->suiviPaiement?->banque ?? ''));

        return $banque !== '' ? $banque : '—';
    }

    protected function libelleFournisseurCourt(): string
    {
        $nom = trim((string) ($this->courrier->expediteur_libelle ?? ''));
        if ($nom === '') {
            return 'Ets';
        }

        if (! str_starts_with(mb_strtolower($nom), 'ets')) {
            $nom = 'Ets '.$nom;
        }

        return mb_substr($nom, 0, 40);
    }
}
