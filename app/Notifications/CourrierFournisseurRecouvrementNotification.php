<?php

namespace App\Notifications;

use App\Models\Courrier;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

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
                ->subject('ACSI – COSUD : ordre de virement transmis à '.$this->banque())
                ->greeting('Bonjour,')
                ->line(
                    'L’ordre de virement relatif à votre facture N° '.$this->referenceFacture()
                    .' du mois de '.$this->moisFacture()
                    .' a été transmis à '.$this->banque().' pour traitement.'
                )
                ->line('**Objet :** '.$this->objet())
                ->line('**Référence OV :** '.$this->numeroPiece())
                ->line('Merci.')
                ->salutation('L’équipe COSUD — '.config('app.name'));
        }

        return (new MailMessage)
            ->subject('ACSI – COSUD : chèque disponible pour recouvrement')
            ->greeting('Bonjour,')
            ->line(
                'Le chèque relatif à votre facture N° '.$this->referenceFacture()
                .' du mois de '.$this->moisFacture()
                .' est disponible pour recouvrement.'
            )
            ->line('Présentez-vous à l’ACSI muni(e) de votre pièce d’identité et de la référence de la facture.')
            ->line('**Objet :** '.$this->objet())
            ->line('Merci.')
            ->salutation('L’équipe COSUD — '.config('app.name'));
    }

    public function toCosudSms(object $notifiable): string
    {
        if ($this->courrier->estModePaiementOv()) {
            $texte = 'ACSI - COSUD : L\'ordre de virement relatif a votre facture '
                .$this->referenceFacture()
                .' du mois de '.$this->moisFacture()
                .' a ete transmis a '.$this->banque()
                .' pour traitement. Merci.';
        } else {
            $texte = 'ACSI - COSUD : Le cheque relatif a votre facture '
                .$this->referenceFacture()
                .' du mois de '.$this->moisFacture()
                .' est disponible pour recouvrement. Presentez-vous a l\'ACSI muni(e) de votre piece d\'identite et de la reference de la facture. Merci.';
        }

        return app(SmsService::class)->sanitizeSmsText($texte);
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
                'message' => 'OV facture '.$this->referenceFacture().' transmis à '.$this->banque().'.',
                'message_title' => 'OV signé — transmis à la banque',
                'courrier_id' => $this->courrier->id,
                'type' => 'fournisseur_recouvrement',
            ];
        }

        return [
            'message' => 'Chèque facture '.$this->referenceFacture().' disponible pour recouvrement à l’ACSI.',
            'message_title' => 'Chèque signé — à recouvrer',
            'courrier_id' => $this->courrier->id,
            'type' => 'fournisseur_recouvrement',
        ];
    }

    protected function referenceFacture(): string
    {
        $reference = trim((string) ($this->courrier->reference ?? ''));

        return $reference !== '' ? $reference : $this->courrier->numeroRegistreComplet();
    }

    protected function moisFacture(): string
    {
        $date = $this->courrier->date_reception
            ?? $this->courrier->date_orientation
            ?? Carbon::now();

        return $date->copy()->locale('fr')->translatedFormat('F Y');
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

        return $banque !== '' ? $banque : 'la banque';
    }
}
