<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_wirepick_envoie_un_sms_quand_configure(): void
    {
        config([
            'ged.sms.provider' => 'wirepick',
            'ged.sms.sender_id' => 'ACSI-GED',
            'ged.sms.wirepick.client' => 'test-client',
            'ged.sms.wirepick.password' => 'secret-password',
            'ged.sms.wirepick.endpoint' => 'https://api.wirepick.com/httpsms/send',
            'ged.sms.wirepick.http_method' => 'get',
        ]);

        Http::fake([
            'api.wirepick.com/*' => Http::response(
                '<?xml version="1.0"?><smses><sms><status>ACT</status><msgid>abc</msgid><phone>242066835332</phone><num_sms>1</num_sms></sms></smses>',
                200
            ),
        ]);

        $ok = app(SmsService::class)->send('+242066835332', 'GED n°1/2026 : dossier VALIDÉ.');

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.wirepick.com/httpsms/send')
                && $request['phone'] === '242066835332'
                && $request['client'] === 'test-client'
                && $request['from'] === 'ACSI-GED'
                && str_contains((string) $request['text'], 'VALIDE');
        });
    }

    public function test_refuse_si_non_configure(): void
    {
        config([
            'ged.sms.provider' => 'wirepick',
            'ged.sms.wirepick.client' => null,
            'ged.sms.wirepick.password' => null,
        ]);

        Http::fake();

        $this->assertFalse(app(SmsService::class)->isConfigured());
        $this->assertFalse(app(SmsService::class)->send('066835332', 'test'));
        Http::assertNothingSent();
    }

    public function test_est_configure_des_que_client_et_mot_de_passe_sont_renseignes(): void
    {
        config([
            'ged.sms.provider' => 'wirepick',
            'ged.sms.wirepick.client' => 'mukinayiseth',
            'ged.sms.wirepick.password' => '123456789@123456789',
        ]);

        $this->assertTrue(app(SmsService::class)->isConfigured());
    }
}
