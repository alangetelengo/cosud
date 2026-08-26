<?php

namespace App\Notifications;

use App\Models\Courrier;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Expéditeur externe : le courrier arrivée a été traité et clôturé.
 */
class CourrierExpediteurTraiteNotification extends Notification
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
        $numero = $this->numero();

        return (new MailMessage)
            ->subject('COSUD : dossier n° '.$numero.' — traité et clôturé')
            ->greeting('Bonjour,')
            ->line('**État de votre dossier :** votre courrier n° '.$numero.' a été traité. Le dossier est désormais **clôturé**.')
            ->line('**Objet :** '.$this->objet())
            ->line('**Ce que cela signifie :** le traitement administratif de votre demande est terminé côté ACSI.')
            ->line('**Ce que vous devez faire :** aucune action complémentaire n’est attendue de votre part. Pour toute question, contactez le secrétariat de l’ACSI en rappelant le n° '.$numero.'.')
            ->line('Merci de votre confiance.')
            ->salutation('L’équipe COSUD — '.config('app.name'));
    }

    public function toCosudSms(object $notifiable): string
    {
        return 'COSUD n°'.$this->numero().' : dossier TRAITÉ et CLÔTURÉ. '
            .'Aucune action de votre part. Contactez le secrétariat ACSI si besoin.';
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
        return [
            'message' => 'Dossier n° '.$this->numero().' traité et clôturé — aucune action de votre part.',
            'message_title' => 'Dossier traité et clôturé',
            'courrier_id' => $this->courrier->id,
            'type' => 'expediteur_traite',
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
