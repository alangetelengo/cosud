<?php

namespace Tests\Feature;

use Tests\TestCase;

class InfobipWhatsAppWebhookTest extends TestCase
{
    public function test_accepte_un_message_entrant(): void
    {
        config(['cosud.whatsapp.webhook_secret' => null]);

        $this->postJson(route('webhooks.infobip.whatsapp', absolute: false), [
            'results' => [
                [
                    'from' => '242066835332',
                    'to' => '242066000000',
                    'messageId' => 'abc-123',
                    'message' => [
                        'type' => 'TEXT',
                        'text' => 'Bonjour COSUD',
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_refuse_si_secret_invalide(): void
    {
        config(['cosud.whatsapp.webhook_secret' => 'secret-attendu']);

        $this->postJson(route('webhooks.infobip.whatsapp', absolute: false), [
            'results' => [],
        ])->assertUnauthorized();
    }

    public function test_accepte_avec_secret_valide(): void
    {
        config(['cosud.whatsapp.webhook_secret' => 'secret-attendu']);

        $this->postJson(route('webhooks.infobip.whatsapp', absolute: false), [
            'results' => [
                [
                    'from' => '242066835332',
                    'to' => '242066000000',
                    'message' => ['type' => 'TEXT', 'text' => 'ok'],
                ],
            ],
        ], [
            'Authorization' => 'secret-attendu',
        ])->assertOk();
    }
}
