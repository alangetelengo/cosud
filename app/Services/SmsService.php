<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi SMS via Wirepick ou Infobip (même logique que SIFEC).
 */
class SmsService
{
    public function isConfigured(): bool
    {
        $provider = $this->provider();

        if ($provider === 'infobip') {
            return filled(config('cosud.sms.infobip.api_key'))
                && filled(config('cosud.sms.infobip.send_url'));
        }

        if ($provider === 'wirepick') {
            $client = config('cosud.sms.wirepick.client');
            $password = config('cosud.sms.wirepick.password');

            return filled($client) && filled($password);
        }

        return false;
    }

    /**
     * @return bool true si l’envoi a été accepté / tenté avec succès HTTP
     */
    public function send(string $to, string $content): bool
    {
        $provider = $this->provider();
        $content = $this->sanitizeSmsText($content);
        $phoneNorm = $this->normalizeSmsPhone($to);

        Log::channel('cosud')->info('SMS send (entrée)', [
            'provider' => $provider,
            'to_brut' => $to,
            'phone_normalise' => $phoneNorm,
            'phone_masque' => $this->maskMsisdnForLog($phoneNorm),
            'from_config' => config('cosud.sms.sender_id'),
            'texte_apercu' => mb_substr($content, 0, 240),
            'texte_longueur' => mb_strlen($content),
        ]);

        if ($phoneNorm === '') {
            Log::channel('cosud')->warning('SMS: numéro vide ou invalide après normalisation Congo', [
                'to_brut' => $to,
            ]);

            return false;
        }

        if (! $this->isConfigured()) {
            Log::channel('cosud')->warning('SMS: fournisseur non configuré — renseigner COSUD_SMS_* dans .env', [
                'provider' => $provider,
            ]);

            return false;
        }

        if ($provider === 'wirepick'
            && config('cosud.sms.wirepick.password') === '123456789@123456789') {
            Log::channel('cosud')->warning('Wirepick: mot de passe encore par défaut — vérifier COSUD_SMS_WIREPICK_PASSWORD');
        }

        return $provider === 'infobip'
            ? $this->sendInfobip($phoneNorm, $content)
            : $this->sendWirepick($phoneNorm, $content);
    }

    public function sanitizeSmsText(string $text): string
    {
        $replacements = [
            '—' => '-', '–' => '-', '−' => '-', '‐' => '-',
            '‘' => "'", '’' => "'", '‚' => "'", '‛' => "'",
            '“' => '"', '”' => '"', '„' => '"',
            '…' => '...', '«' => '"', '»' => '"',
            '°' => ' ', '€' => 'EUR',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ç' => 'c', 'ñ' => 'n',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Ç' => 'C', 'Ñ' => 'N',
        ];
        $text = strtr($text, $replacements);

        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($trans) && $trans !== '') {
            $text = $trans;
        }

        $text = preg_replace('/[^\x20-\x7E\n]/', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ ?- ?/', '-', $text) ?? $text;

        return trim($text);
    }

    /**
     * MSISDN international Congo : chiffres uniquement (ex. 242066835332).
     */
    public function normalizeSmsPhone(string $to): string
    {
        $digits = preg_replace('/\D/', '', ltrim(trim($to), '+'));
        if (! is_string($digits) || $digits === '') {
            return '';
        }

        $cc = '242';

        if (str_starts_with($digits, $cc)) {
            $national = substr($digits, strlen($cc));
            if (strlen($national) === 8 && ! str_starts_with($national, '0')) {
                return $cc.'0'.$national;
            }

            return $digits;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '0')) {
            return $cc.$digits;
        }

        if (strlen($digits) === 8) {
            return $cc.'0'.$digits;
        }

        if (strlen($digits) <= 10) {
            if (! str_starts_with($digits, '0')) {
                $digits = '0'.$digits;
            }

            return $cc.$digits;
        }

        return $digits;
    }

    public function maskMsisdnForLog(string $digits): string
    {
        $len = strlen($digits);
        if ($len === 0) {
            return '(vide)';
        }
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($digits, 0, 3).'…'.substr($digits, -4).' ('.$len.' chiffres)';
    }

    /**
     * @return array{status: ?string, msgid: ?string, phone: ?string, num_sms: ?int}
     */
    public function parseWirepickResponseDetails(?string $xmlBody): array
    {
        $empty = ['status' => null, 'msgid' => null, 'phone' => null, 'num_sms' => null];
        if ($xmlBody === null || $xmlBody === '') {
            return $empty;
        }

        try {
            $sx = @simplexml_load_string($xmlBody);
            if ($sx === false || ! isset($sx->sms)) {
                return $empty;
            }

            $numSms = isset($sx->sms->num_sms) ? (int) (string) $sx->sms->num_sms : null;

            return [
                'status' => isset($sx->sms->status) ? trim((string) $sx->sms->status) : null,
                'msgid' => isset($sx->sms->msgid) ? trim((string) $sx->sms->msgid) : null,
                'phone' => isset($sx->sms->phone) ? trim((string) $sx->sms->phone) : null,
                'num_sms' => $numSms > 0 ? $numSms : null,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    protected function provider(): string
    {
        return strtolower((string) config('cosud.sms.provider', 'wirepick'));
    }

    protected function sendWirepick(string $phone, string $content): bool
    {
        $from = (string) config('cosud.sms.sender_id', 'ACSI-COSUD');
        $data = [
            'client' => config('cosud.sms.wirepick.client'),
            'password' => config('cosud.sms.wirepick.password'),
            'phone' => $phone,
            'from' => $from,
            'text' => $content,
        ];
        $endpoint = (string) config('cosud.sms.wirepick.endpoint', 'https://api.wirepick.com/httpsms/send');
        $method = strtolower((string) config('cosud.sms.wirepick.http_method', 'get'));

        $http = Http::withOptions([
            'curl' => [
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_FRESH_CONNECT => true,
            ],
        ])->timeout(30);

        $response = $method === 'post'
            ? $http->asForm()->post($endpoint, $data)
            : $http->get($endpoint, $data);

        $body = $response->body();
        $wirepick = $this->parseWirepickResponseDetails($body);
        $status = $wirepick['status'];

        Log::channel('cosud')->info('Wirepick SMS (réponse)', [
            'http_method' => $method,
            'from_envoye' => $from,
            'phone_masque' => $this->maskMsisdnForLog($phone),
            'texte_longueur' => mb_strlen($content),
            'http_status' => $response->status(),
            'wirepick_status' => $status,
            'wirepick_msgid' => $wirepick['msgid'],
            'corps_reponse' => mb_substr($body, 0, 500),
        ]);

        if ($status !== null && strtoupper($status) !== 'ACT') {
            Log::channel('cosud')->warning('Wirepick: statut autre que ACT', [
                'wirepick_status' => $status,
                'phone_masque' => $this->maskMsisdnForLog($phone),
            ]);

            return false;
        }

        return $response->successful() || $status === 'ACT' || strtoupper((string) $status) === 'ACT';
    }

    protected function sendInfobip(string $phone, string $content): bool
    {
        $endpoint = (string) config('cosud.sms.infobip.send_url');
        $token = (string) config('cosud.sms.infobip.api_key');
        $from = (string) config('cosud.sms.sender_id', 'ACSI-COSUD');

        $payload = [
            'messages' => [
                [
                    'from' => $from,
                    'destinations' => [
                        ['to' => $phone],
                    ],
                    'text' => $content,
                ],
            ],
        ];

        $response = Http::asJson()
            ->withHeaders([
                'Authorization' => $token,
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->post($endpoint, $payload);

        Log::channel('cosud')->info('Infobip SMS (réponse)', [
            'from_envoye' => $from,
            'phone_masque' => $this->maskMsisdnForLog($phone),
            'texte_longueur' => mb_strlen($content),
            'http_status' => $response->status(),
            'corps_reponse' => mb_substr($response->body(), 0, 500),
        ]);

        return $response->successful();
    }
}
