<?php

namespace App\Notifications;

use App\Models\Courrier;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fournisseur / prestataire : chèque signé — passage au recouvrement.
 */
class CourrierFournisseurRecouvrementNotification extends Notification
{
    use Queueable;

    public function __construct(public Courrier $courrier) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }

        if ($notifiable->routeNotificationFor('cosud_sms') && app(SmsService::class)->isConfigured()) {
            $channels[] = 'cosud_sms';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $numero = $this->numero();

        return (new MailMessage)
            ->subject('COSUD : dossier n° '.$numero.' — chèque signé, recouvrement possible')
            ->greeting('Bonjour,')
            ->line('**État de votre dossier :** le chèque relatif à votre facture (courrier n° '.$numero.') a été **signé** par la Direction.')
            ->line('**Objet :** '.$this->objet())
            ->line('**Ce que cela signifie :** le paiement est autorisé ; votre dossier est prêt pour le recouvrement.')
            ->line('**Ce que vous devez faire :** présentez-vous auprès de l’ACSI (ou contactez le service comptable) pour procéder au **recouvrement** du chèque, en rappelant le n° '.$numero.'.')
            ->line('Merci de votre confiance.')
            ->salutation('L’équipe COSUD — '.config('app.name'));
    }

    public function toCosudSms(object $notifiable): string
    {
        return 'COSUD n°'.$this->numero().' : chèque SIGNÉ. '
            .'Présentez-vous à l’ACSI pour le RECOUVREMENT (rappeler ce n°).';
    }

    /**
     * @return array{message: string, message_title: string, courrier_id: int, type: string}
     */
    public function toArray(object $notifiable): array
    {
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
}
