<?php

namespace Tests\Feature;

use App\Mail\TwoFactorBulkMailable;
use App\Models\Courrier;
use App\Models\User;
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CourrierNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailSmtpConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_mail_from_pointe_vers_ged_acsi(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'sifec.etatcivil@gmail.com',
            'mail.from.name' => 'GED ACSI',
        ]);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('sifec.etatcivil@gmail.com', config('mail.from.address'));
        $this->assertSame('GED ACSI', config('mail.from.name'));
    }

    public function test_mailable_2fa_utilise_lexpediteur_configure(): void
    {
        Mail::fake();

        config([
            'mail.from.address' => 'sifec.etatcivil@gmail.com',
            'mail.from.name' => 'GED ACSI',
        ]);

        $user = User::factory()->create([
            'email' => 'destinataire@example.com',
        ]);

        Mail::to($user->email)->send(new TwoFactorBulkMailable(
            $user,
            'SECRETKEYTEST123',
            ['AAAA-BBBB', 'CCCC-DDDD'],
            'https://example.com/qr.png',
        ));

        Mail::assertSent(TwoFactorBulkMailable::class, function (TwoFactorBulkMailable $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->envelope()->from->address === 'sifec.etatcivil@gmail.com'
                && $mail->envelope()->from->name === 'GED ACSI'
                && str_contains((string) $mail->envelope()->subject, '2FA');
        });
    }

    public function test_notification_courrier_mail_desactivee_par_defaut(): void
    {
        config(['ged.courrier_notifications_mail' => false]);

        $user = User::factory()->create();
        $acteur = User::factory()->create();

        $courrier = new Courrier;
        $courrier->id = 1;
        $courrier->objet = 'Test';
        $courrier->numero_registre = 1;
        $courrier->numero_registre_annee = 2026;

        $notification = new CourrierWorkflowNotification(
            $courrier,
            $acteur,
            CourrierNotificationService::MISE_EN_PARAPHEUR,
        );

        $this->assertSame(['database'], $notification->via($user));
    }

    public function test_notification_courrier_ajoute_le_canal_mail_si_active(): void
    {
        config(['ged.courrier_notifications_mail' => true]);

        Notification::fake();

        $user = User::factory()->create();
        $acteur = User::factory()->create();
        $courrier = new Courrier;
        $courrier->id = 1;
        $courrier->objet = 'Test';
        $courrier->numero_registre = 1;
        $courrier->numero_registre_annee = 2026;

        $notification = new CourrierWorkflowNotification(
            $courrier,
            $acteur,
            CourrierNotificationService::MISE_EN_PARAPHEUR,
        );

        $this->assertContains('mail', $notification->via($user));
        $this->assertContains('database', $notification->via($user));

        $mail = $notification->toMail($user);
        $this->assertStringStartsWith('GED : ', $mail->subject);
    }
}
