<?php

namespace Tests\Unit;

use App\Models\Courrier;
use App\Models\User;
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CourrierNotificationService;
use Tests\TestCase;

class CourrierWorkflowNotificationMessageTest extends TestCase
{
    public function test_etape_circuit_affiche_nom_etape_numero_et_objet(): void
    {
        $courrier = new Courrier([
            'objet' => 'Facture N°2026-0087 — matériel',
            'numero_registre' => 1,
            'numero_registre_annee' => 2026,
        ]);
        $courrier->id = 1;

        $acteur = new User(['name' => 'DG Test']);
        $acteur->id = 2;

        $notification = new CourrierWorkflowNotification(
            $courrier,
            $acteur,
            CourrierNotificationService::ETAPE_CIRCUIT,
            'Étape en cours : Notification / instructions DG — Merci de traiter | Instructions : Établir le chèque'
        );

        $data = $notification->toArray($acteur);

        $this->assertSame('À traiter : Notification / instructions DG', $data['message_title']);
        $this->assertStringContainsString('À traiter : Notification / instructions DG', $data['message']);
        $this->assertStringContainsString('n° 1/2026', $data['message']);
        $this->assertStringContainsString('Facture N°2026-0087 — matériel', $data['message']);
        $this->assertStringContainsString('Notification / instructions DG', $data['message_body']);
        $this->assertStringNotContainsString('Une étape du circuit courrier vous concerne', $data['message']);
    }

    public function test_etape_circuit_sans_detail_reste_actionnable(): void
    {
        $courrier = new Courrier([
            'objet' => 'Demande organigramme',
            'numero_registre' => 12,
            'numero_registre_annee' => 2026,
        ]);
        $courrier->id = 3;

        $acteur = new User(['name' => 'Agent']);
        $acteur->id = 4;

        $notification = new CourrierWorkflowNotification(
            $courrier,
            $acteur,
            CourrierNotificationService::ETAPE_CIRCUIT,
            null
        );

        $data = $notification->toArray($acteur);

        $this->assertSame('Action requise sur un courrier', $data['message_title']);
        $this->assertStringContainsString('n° 12/2026', $data['message']);
        $this->assertStringContainsString('Demande organigramme', $data['message']);
    }
}
