<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Réception des messages WhatsApp entrants (Infobip inbound webhook).
 */
class InfobipWhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expectedSecret = trim((string) config('cosud.whatsapp.webhook_secret', ''));
        if ($expectedSecret !== '') {
            $provided = (string) $request->header('Authorization', $request->query('secret', ''));
            if (! hash_equals($expectedSecret, $provided)
                && ! hash_equals('App '.$expectedSecret, $provided)
                && ! hash_equals($expectedSecret, (string) $request->query('secret', ''))) {
                Log::channel('cosud')->warning('WhatsApp webhook: secret invalide');

                return response()->json(['ok' => false], 401);
            }
        }

        $payload = $request->all();
        $results = $payload['results'] ?? null;

        if (! is_array($results)) {
            Log::channel('cosud')->info('WhatsApp webhook: payload reçu (hors results)', [
                'cles' => array_keys($payload),
            ]);

            return response()->json(['ok' => true]);
        }

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $from = (string) ($result['from'] ?? '');
            $to = (string) ($result['to'] ?? '');
            $messageId = (string) ($result['messageId'] ?? '');
            $message = is_array($result['message'] ?? null) ? $result['message'] : [];
            $type = (string) ($message['type'] ?? '');
            $text = (string) ($message['text'] ?? '');

            Log::channel('cosud')->info('WhatsApp entrant', [
                'from' => $from,
                'to' => $to,
                'message_id' => $messageId,
                'type' => $type,
                'texte_apercu' => mb_substr($text, 0, 240),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
