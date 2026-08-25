<?php

namespace App\Notifications;

use App\Models\Courrier;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Expéditeur externe : la Direction a validé / signé la réponse.
 * Le dossier n’est pas encore clôturé (expédition éventuellement en cours).
 */
class CourrierExpediteurValideNotification extends Notification
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
            ->subject('COSUD : dossier n° '.$numero.' — validé par la Direction')
            ->greeting('Bonjour,')
            ->line('**État de votre dossier :** la Direction Générale a validé votre demande (courrier n° '.$numero.').')
            ->line('**Objet :** '.$this->objet())
            ->line('**Ce que cela signifie :** votre dossier est accepté au niveau de la direction. Une réponse officielle est en cours de finalisation et d’expédition.')
            ->line('**Ce que vous devez faire :** aucune démarche de votre part n’est requise pour le moment. Attendez la suite du traitement ; vous serez informé(e) lorsque le dossier sera clôturé.')
            ->line('Merci de votre confiance.')
            ->salutation('L’équipe COSUD — '.config('app.name'));
    }

    public function toCosudSms(object $notifiable): string
    {
        return 'COSUD n°'.$this->numero().' : dossier VALIDÉ par la Direction. '
            .'Réponse en cours d’envoi. Aucune action de votre part pour l’instant.';
    }

    /**
     * @return array{message: string, message_title: string, courrier_id: int, type: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Dossier n° '.$this->numero().' validé par la Direction — réponse en cours d’envoi ; aucune action de votre part.',
            'message_title' => 'Dossier validé — en attente d’expédition',
            'courrier_id' => $this->courrier->id,
            'type' => 'expediteur_valide',
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
