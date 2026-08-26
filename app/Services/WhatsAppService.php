<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi WhatsApp multi-driver : log (local) | meta (Cloud API sandbox/prod) | infobip.
 */
class WhatsAppService
{
    public function __construct(private readonly SmsService $sms) {}

    public function driver(): string
    {
        return strtolower((string) config('cosud.whatsapp.driver', 'log'));
    }

    /**
     * True si un canal WhatsApp réel (Meta / Infobip) est prêt à délivrer.
     * Le driver « log » n’est jamais « configuré » : il ne doit pas masquer les SMS.
     */
    public function isConfigured(): bool
    {
        return match ($this->driver()) {
            'log' => false,
            'meta' => filled(config('cosud.whatsapp.meta.token'))
                && filled(config('cosud.whatsapp.meta.phone_number_id')),
            'infobip' => filled($this->infobipApiKey())
                && filled(config('cosud.whatsapp.infobip.from'))
                && filled(config('cosud.whatsapp.infobip.base_url')),
            default => false,
        };
    }

    /**
     * True si un envoi peut être tenté (simulation log ou canal réel configuré).
     */
    public function canSend(): bool
    {
        return $this->driver() === 'log' || $this->isConfigured();
    }

    /**
     * @return bool true si l’envoi a été accepté (ou simulé en driver log)
     */
    public function send(string $to, string $content): bool
    {
        $content = trim($content);
        $phoneNorm = $this->sms->normalizeSmsPhone($to);
        $driver = $this->driver();

        Log::channel('cosud')->info('WhatsApp send (entrée)', [
            'driver' => $driver,
            'to_brut' => $to,
            'phone_normalise' => $phoneNorm,
            'phone_masque' => $this->sms->maskMsisdnForLog($phoneNorm),
            'texte_apercu' => mb_substr($content, 0, 240),
            'texte_longueur' => mb_strlen($content),
        ]);

        if ($phoneNorm === '' || $content === '') {
            Log::channel('cosud')->warning('WhatsApp: numéro ou message vide', [
                'to_brut' => $to,
            ]);

            return false;
        }

        if (! $this->canSend()) {
            Log::channel('cosud')->warning('WhatsApp: driver non configuré', [
                'driver' => $driver,
            ]);

            return false;
        }

        return match ($driver) {
            'log' => $this->sendLog($phoneNorm, $content),
            'meta' => $this->sendViaMeta($phoneNorm, $content),
            'infobip' => $this->sendViaInfobip($phoneNorm, $content),
            default => false,
        };
    }

    /**
     * @param  list<string>  $placeholders
     */
    public function sendTemplate(string $to, string $templateName, array $placeholders = []): bool
    {
        $phoneNorm = $this->sms->normalizeSmsPhone($to);
        if ($phoneNorm === '' || ! $this->canSend()) {
            return false;
        }

        return match ($this->driver()) {
            'log' => $this->sendLog($phoneNorm, '[template:'.$templateName.'] '.implode(' | ', $placeholders)),
            'meta' => $this->sendMetaTemplate($phoneNorm, $templateName, $placeholders),
            'infobip' => $this->sendInfobipTemplate($phoneNorm, $templateName, $placeholders),
            default => false,
        };
    }

    public function sendText(string $to, string $content): bool
    {
        $phoneNorm = $this->sms->normalizeSmsPhone($to);
        if ($phoneNorm === '' || ! $this->canSend()) {
            return false;
        }

        return match ($this->driver()) {
            'log' => $this->sendLog($phoneNorm, $content),
            'meta' => $this->sendMetaText($phoneNorm, $content),
            'infobip' => $this->sendInfobipText($phoneNorm, $content),
            default => false,
        };
    }

    protected function sendLog(string $phoneNorm, string $content): bool
    {
        Log::channel('cosud')->info('WhatsApp LOG (simulation — aucun envoi réel)', [
            'phone_masque' => $this->sms->maskMsisdnForLog($phoneNorm),
            'phone_normalise' => $phoneNorm,
            'texte' => $content,
        ]);

        return true;
    }

    protected function sendViaMeta(string $phoneNorm, string $content): bool
    {
        $templateName = trim((string) config('cosud.whatsapp.meta.template_name', ''));

        return $templateName !== ''
            ? $this->sendMetaTemplate($phoneNorm, $templateName, [$content])
            : $this->sendMetaText($phoneNorm, $content);
    }

    protected function sendMetaText(string $phoneNorm, string $content): bool
    {
        $url = $this->metaMessagesUrl();

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phoneNorm,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $content,
            ],
        ];

        $response = Http::asJson()
            ->withToken((string) config('cosud.whatsapp.meta.token'))
            ->timeout(30)
            ->post($url, $payload);

        Log::channel('cosud')->info('Meta WhatsApp text (réponse)', [
            'phone_masque' => $this->sms->maskMsisdnForLog($phoneNorm),
            'http_status' => $response->status(),
            'corps_reponse' => mb_substr($response->body(), 0, 500),
        ]);

        return $response->successful();
    }

    /**
     * @param  list<string>  $placeholders
     */
    protected function sendMetaTemplate(string $phoneNorm, string $templateName, array $placeholders = []): bool
    {
        $language = (string) config('cosud.whatsapp.meta.template_language', 'fr');
        $url = $this->metaMessagesUrl();

        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if ($placeholders !== []) {
            $template['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        fn (string $text): array => ['type' => 'text', 'text' => $text],
                        array_values($placeholders)
                    ),
                ],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNorm,
            'type' => 'template',
            'template' => $template,
        ];

        $response = Http::asJson()
            ->withToken((string) config('cosud.whatsapp.meta.token'))
            ->timeout(30)
            ->post($url, $payload);

        Log::channel('cosud')->info('Meta WhatsApp template (réponse)', [
            'phone_masque' => $this->sms->maskMsisdnForLog($phoneNorm),
            'template' => $templateName,
            'http_status' => $response->status(),
            'corps_reponse' => mb_substr($response->body(), 0, 500),
        ]);

        return $response->successful();
    }

    protected function sendViaInfobip(string $phoneNorm, string $content): bool
    {
        $templateName = trim((string) config('cosud.whatsapp.infobip.template_name', ''));

        return $templateName !== ''
            ? $this->sendInfobipTemplate($phoneNorm, $templateName, [$content])
            : $this->sendInfobipText($phoneNorm, $content);
    }

    /**
     * @param  list<string>  $placeholders
     */
    protected function sendInfobipTemplate(string $phoneNorm, string $templateName, array $placeholders = []): bool
    {
        $from = (string) config('cosud.whatsapp.infobip.from');
        $language = (string) config('cosud.whatsapp.infobip.template_language', 'fr');
        $url = rtrim((string) config('cosud.whatsapp.infobip.base_url'), '/').'/whatsapp/1/message/template';

        $payload = [
            'messages' => [
                [
                    'from' => $from,
                    'to' => $phoneNorm,
                    'content' => [
                        'templateName' => $templateName,
                        'templateData' => [
                            'body' => [
                                'placeholders' => array_values($placeholders),
                            ],
                        ],
                        'language' => $language,
                    ],
                ],
            ],
        ];

        $response = Http::asJson()
            ->withHeaders($this->infobipAuthHeaders())
            ->timeout(30)
            ->post($url, $payload);

        Log::channel('cosud')->info('Infobip WhatsApp template (réponse)', [
            'from_envoye' => $from,
            'phone_masque' => $this->sms->maskMsisdnForLog($phoneNorm),
            'template' => $templateName,
            'http_status' => $response->status(),
            'corps_reponse' => mb_substr($response->body(), 0, 500),
        ]);

        return $response->successful();
    }

    protected function sendInfobipText(string $phoneNorm, string $content): bool
    {
        $from = (string) config('cosud.whatsapp.infobip.from');
        $url = rtrim((string) config('cosud.whatsapp.infobip.base_url'), '/').'/whatsapp/1/message/text';

        $payload = [
            'from' => $from,
            'to' => $phoneNorm,
            'content' => [
                'text' => $content,
            ],
        ];

        $response = Http::asJson()
            ->withHeaders($this->infobipAuthHeaders())
            ->timeout(30)
            ->post($url, $payload);

        Log::channel('cosud')->info('Infobip WhatsApp text (réponse)', [
            'from_envoye' => $from,
            'phone_masque' => $this->sms->maskMsisdnForLog($phoneNorm),
            'texte_longueur' => mb_strlen($content),
            'http_status' => $response->status(),
            'corps_reponse' => mb_substr($response->body(), 0, 500),
        ]);

        return $response->successful();
    }

    protected function metaMessagesUrl(): string
    {
        $version = trim((string) config('cosud.whatsapp.meta.api_version', 'v21.0'), '/');
        $phoneId = (string) config('cosud.whatsapp.meta.phone_number_id');

        return "https://graph.facebook.com/{$version}/{$phoneId}/messages";
    }

    /**
     * @return array<string, string>
     */
    protected function infobipAuthHeaders(): array
    {
        return [
            'Authorization' => $this->infobipApiKey(),
            'Accept' => 'application/json',
        ];
    }

    protected function infobipApiKey(): string
    {
        $key = (string) config('cosud.whatsapp.infobip.api_key', '');

        if ($key !== '') {
            return $key;
        }

        return (string) config('cosud.sms.infobip.api_key', '');
    }
}
