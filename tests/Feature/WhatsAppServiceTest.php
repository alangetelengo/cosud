<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    public function test_driver_log_simule_un_envoi_sans_masquer_sms(): void
    {
        config(['cosud.whatsapp.driver' => 'log']);
        Http::fake();

        $whatsapp = app(WhatsAppService::class);

        $this->assertFalse($whatsapp->isConfigured());
        $this->assertTrue($whatsapp->canSend());
        $this->assertTrue($whatsapp->send('+242044164337', 'Test COSUD'));
        Http::assertNothingSent();
    }

    public function test_envoie_texte_meta_quand_configure(): void
    {
        config([
            'cosud.whatsapp.driver' => 'meta',
            'cosud.whatsapp.meta.token' => 'meta-token-test',
            'cosud.whatsapp.meta.phone_number_id' => '123456789',
            'cosud.whatsapp.meta.api_version' => 'v21.0',
            'cosud.whatsapp.meta.template_name' => null,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.xxx']]], 200),
        ]);

        $ok = app(WhatsAppService::class)->send('+242044164337', 'COSUD : test Meta');

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), 'graph.facebook.com/v21.0/123456789/messages')
                && ($data['messaging_product'] ?? null) === 'whatsapp'
                && ($data['to'] ?? null) === '242044164337'
                && ($data['type'] ?? null) === 'text'
                && str_contains((string) ($data['text']['body'] ?? ''), 'test Meta');
        });
    }

    public function test_envoie_texte_infobip_quand_configure(): void
    {
        config([
            'cosud.whatsapp.driver' => 'infobip',
            'cosud.whatsapp.infobip.api_key' => 'App test-key',
            'cosud.whatsapp.infobip.base_url' => 'https://mpn66j.api.infobip.com',
            'cosud.whatsapp.infobip.from' => '242066000000',
            'cosud.whatsapp.infobip.template_name' => null,
            'cosud.sms.infobip.api_key' => null,
        ]);

        Http::fake([
            'mpn66j.api.infobip.com/whatsapp/1/message/text' => Http::response(['messages' => [['status' => ['groupId' => 1]]]], 200),
        ]);

        $ok = app(WhatsAppService::class)->send('+242066835332', 'COSUD n°1/2026 : dossier VALIDÉ.');

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/whatsapp/1/message/text')
                && $request->hasHeader('Authorization', 'App test-key')
                && ($data['from'] ?? null) === '242066000000'
                && ($data['to'] ?? null) === '242066835332'
                && str_contains((string) ($data['content']['text'] ?? ''), 'VALIDÉ');
        });
    }

    public function test_envoie_template_infobip_quand_template_name_renseigne(): void
    {
        config([
            'cosud.whatsapp.driver' => 'infobip',
            'cosud.whatsapp.infobip.api_key' => 'App test-key',
            'cosud.whatsapp.infobip.base_url' => 'https://mpn66j.api.infobip.com',
            'cosud.whatsapp.infobip.from' => '242066000000',
            'cosud.whatsapp.infobip.template_name' => 'cosud_alerte',
            'cosud.whatsapp.infobip.template_language' => 'fr',
        ]);

        Http::fake([
            'mpn66j.api.infobip.com/whatsapp/1/message/template' => Http::response(['messages' => []], 200),
        ]);

        $ok = app(WhatsAppService::class)->send('066835332', 'Dossier validé');

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            $data = $request->data();
            $message = $data['messages'][0] ?? [];

            return str_contains($request->url(), '/whatsapp/1/message/template')
                && ($message['content']['templateName'] ?? null) === 'cosud_alerte'
                && ($message['content']['language'] ?? null) === 'fr'
                && ($message['content']['templateData']['body']['placeholders'][0] ?? null) === 'Dossier validé';
        });
    }

    public function test_refuse_si_infobip_non_configure(): void
    {
        config([
            'cosud.whatsapp.driver' => 'infobip',
            'cosud.whatsapp.infobip.api_key' => null,
            'cosud.whatsapp.infobip.from' => null,
            'cosud.sms.infobip.api_key' => null,
        ]);

        Http::fake();

        $this->assertFalse(app(WhatsAppService::class)->isConfigured());
        $this->assertFalse(app(WhatsAppService::class)->send('066835332', 'test'));
        Http::assertNothingSent();
    }

    public function test_reutilise_la_cle_api_sms_infobip_en_repli(): void
    {
        config([
            'cosud.whatsapp.driver' => 'infobip',
            'cosud.whatsapp.infobip.api_key' => null,
            'cosud.whatsapp.infobip.base_url' => 'https://mpn66j.api.infobip.com',
            'cosud.whatsapp.infobip.from' => '242066000000',
            'cosud.sms.infobip.api_key' => 'App sms-key',
        ]);

        $this->assertTrue(app(WhatsAppService::class)->isConfigured());
    }

    public function test_commande_whatsapp_test_en_mode_log(): void
    {
        config(['cosud.whatsapp.driver' => 'log']);

        $this->artisan('whatsapp:test', [
            'telephone' => '242044164337',
            '--message' => 'Ping COSUD',
        ])->assertSuccessful();
    }
}
