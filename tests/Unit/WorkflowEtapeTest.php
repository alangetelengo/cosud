<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\Fonction;
use App\Models\Structure;
use App\Models\TypeDocument;
use App\Models\User;
use App\Models\WorkflowEtape;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEtapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_priority_is_service_then_type_then_global(): void
    {
        $type = TypeDocument::create([
            'code' => 'contrat',
            'libelle' => 'Contrat',
            'extension_defaut' => 'pdf',
            'taille_max_ko' => 10240,
            'actif' => true,
        ]);

        $service = Structure::create([
            'nom' => 'Service Développement',
            'code' => 'SVC-DDI-DEVINT',
            'type' => 'service',
            'actif' => true,
        ]);
        $projet = Dossier::create([
            'nom' => 'Projet SIG',
            'type' => 'projet',
            'structure_id' => $service->id,
            'actif' => true,
        ]);
        $sousDossier = Dossier::create([
            'parent_id' => $projet->id,
            'nom' => 'Développement',
            'type' => 'operation',
            'structure_id' => $service->id,
            'actif' => true,
        ]);

        $global = WorkflowEtape::create([
            'nom' => 'Global',
            'code' => 'global_etape_1',
            'ordre' => 1,
            'est_derniere_etape' => false,
            'actif' => true,
        ]);
        $typeStep = WorkflowEtape::create([
            'nom' => 'Type',
            'code' => 'type_etape_1',
            'ordre' => 1,
            'type_document_id' => $type->id,
            'est_derniere_etape' => false,
            'actif' => true,
        ]);
        $serviceStep = WorkflowEtape::create([
            'nom' => 'Service',
            'code' => 'service_etape_1',
            'ordre' => 1,
            'structure_scope_id' => $service->id,
            'est_derniere_etape' => false,
            'actif' => true,
        ]);

        $resolvedForProject = WorkflowEtape::premiereEtapePour($type->id, $sousDossier->id);
        $resolvedForType = WorkflowEtape::premiereEtapePour($type->id, null);
        $resolvedGlobal = WorkflowEtape::premiereEtapePour(null, null);
        $ctx = WorkflowEtape::contexteEnvoiPourType($type->id, $type->libelle, $sousDossier->id);

        $this->assertSame($serviceStep->id, $resolvedForProject?->id);
        $this->assertSame($typeStep->id, $resolvedForType?->id);
        $this->assertSame($global->id, $resolvedGlobal?->id);
        $this->assertSame('service', $ctx['source']);
        $this->assertSame('Service Développement', $ctx['service_nom']);
    }

    public function test_destinataire_libre_uses_document_assigned_validator(): void
    {
        $type = TypeDocument::create([
            'code' => 'note',
            'libelle' => 'Note',
            'extension_defaut' => 'pdf',
            'taille_max_ko' => 10240,
            'actif' => true,
        ]);
        $owner = User::factory()->create();
        $target = User::factory()->create();

        $etape = WorkflowEtape::create([
            'nom' => 'Visa libre',
            'code' => 'visa_libre',
            'ordre' => 1,
            'destinataire_libre' => true,
            'est_derniere_etape' => false,
            'actif' => true,
        ]);

        $document = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $owner->id,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'nom_original' => 'note.pdf',
            'chemin' => 'documents/test/note.pdf',
            'extension' => 'pdf',
            'workflow_validateur_id' => $target->id,
            'workflow_etape_actuelle_id' => $etape->id,
        ]);

        $this->assertTrue($etape->peutValider($target, $document));
        $this->assertFalse($etape->peutValider($owner, $document));
    }

    public function test_fonction_mode_accepts_only_agents_with_active_required_function(): void
    {
        $fonctionChefProjet = Fonction::create([
            'code' => 'chef_projet',
            'libelle' => 'Chef de projet',
            'actif' => true,
        ]);
        $structure = Structure::create([
            'nom' => 'DDSAIT',
            'code' => 'DDSAIT',
            'type' => 'direction',
            'actif' => true,
        ]);
        $agentChefProjet = User::factory()->create();
        $agentSimple = User::factory()->create();

        $agentChefProjet->structures()->attach($structure->id, [
            'fonction_id' => $fonctionChefProjet->id,
            'date_affectation' => now(),
            'date_fin' => null,
        ]);

        $etape = WorkflowEtape::create([
            'nom' => 'Validation fonction chef de projet',
            'code' => 'wf_fonction_cp',
            'ordre' => 1,
            'fonction_requise_id' => $fonctionChefProjet->id,
            'est_derniere_etape' => false,
            'actif' => true,
        ]);

        $this->assertTrue($etape->peutValider($agentChefProjet));
        $this->assertFalse($etape->peutValider($agentSimple));
    }
}

