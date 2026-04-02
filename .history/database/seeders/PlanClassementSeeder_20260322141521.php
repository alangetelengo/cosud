<?php

namespace Database\Seeders;

use App\Models\Dossier;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanClassementSeeder extends Seeder
{
    /** Mapping type dossier → code structure (direction responsable). */
    protected array $typeToStructure = [
        'administratif' => 'SEC-DIR',
        'finance' => 'DAF',
        'projet' => 'DDSAIT',
        'ingenierie_si' => 'DING-SI',
        'support' => 'DSUPPORT',
        'client' => 'DCOM',
        'operation' => 'DINFRA',
        'archive' => 'DAF',
        'confidentiel' => 'DG',
    ];

    protected array $plan = [
        [
            'name' => 'Administration',
            'type' => 'administratif',
            'children' => [
                [
                    'name' => 'Ressources humaines',
                    'children' => [
                        ['name' => 'Contrats de travail'],
                        ['name' => 'CV'],
                        ['name' => 'Fiches de paie'],
                    ],
                ],
                [
                    'name' => 'Juridique',
                    'children' => [
                        ['name' => 'Statuts'],
                        ['name' => 'Contrats'],
                        ['name' => 'Procès-verbaux'],
                    ],
                ],
                [
                    'name' => 'Direction',
                    'children' => [
                        ['name' => 'Rapports'],
                        ['name' => 'Réunions'],
                        ['name' => 'Décisions'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Finance',
            'type' => 'finance',
            'children' => [
                [
                    'name' => 'Comptabilité',
                    'children' => [
                        ['name' => 'Factures clients'],
                        ['name' => 'Factures fournisseurs'],
                    ],
                ],
                [
                    'name' => 'Trésorerie',
                    'children' => [
                        ['name' => 'Relevés bancaires'],
                    ],
                ],
                [
                    'name' => 'Budgets',
                    'children' => [
                        ['name' => 'Prévisions'],
                        ['name' => 'Rapports financiers'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Ingénierie SI',
            'type' => 'ingenierie_si',
            'children' => [
                ['name' => 'Architecture'],
                ['name' => 'Études techniques'],
                ['name' => 'Documentation'],
            ],
        ],
        [
            'name' => 'Projets',
            'type' => 'projet',
            'children' => [
                [
                    'name' => 'Projet A',
                    'children' => [
                        ['name' => 'Cahier des charges'],
                        ['name' => 'Planning'],
                        ['name' => 'Rapports'],
                    ],
                ],
                [
                    'name' => 'Projet B',
                    'children' => [
                        ['name' => 'Documents techniques'],
                        ['name' => 'Livrables'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Support et formation',
            'type' => 'support',
            'children' => [
                ['name' => 'Formations'],
                ['name' => 'Tickets support'],
                ['name' => 'Guides'],
            ],
        ],
        [
            'name' => 'Clients',
            'type' => 'client',
            'children' => [
                [
                    'name' => 'Client X',
                    'children' => [
                        ['name' => 'Contrats'],
                        ['name' => 'Factures'],
                        ['name' => 'Correspondances'],
                    ],
                ],
                [
                    'name' => 'Client Y',
                    'children' => [
                        ['name' => 'Dossiers actifs'],
                        ['name' => 'Historique'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Opérations',
            'type' => 'operation',
            'children' => [
                ['name' => 'Achats'],
                ['name' => 'Ventes'],
                ['name' => 'Logistique'],
                ['name' => 'Production'],
            ],
        ],
        [
            'name' => 'Archives',
            'type' => 'archive',
            'children' => [
                ['name' => 'Archives 2023'],
                ['name' => 'Archives 2024'],
                ['name' => 'Documents expirés'],
            ],
        ],
        [
            'name' => 'Confidentiel',
            'type' => 'confidentiel',
            'children' => [
                ['name' => 'Direction uniquement'],
                ['name' => 'Documents sensibles'],
            ],
        ],
    ];

    public function run(): void
    {
        $createurId = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->value('id');
        $dgId = User::whereHas('roles', fn ($q) => $q->where('name', 'dg'))->value('id');
        $fallbackProprietaireId = $dgId ?? $createurId;

        if (Dossier::count() > 0) {
            if ($createurId) {
                Dossier::whereNull('createur_id')->update(['createur_id' => $createurId]);
            }
            foreach (Dossier::whereNull('parent_id')->get() as $root) {
                $structureId = isset($this->typeToStructure[$root->type ?? ''])
                    ? Structure::where('code', $this->typeToStructure[$root->type])->value('id')
                    : null;
                if ($structureId) {
                    $this->propagateStructureId($root, $structureId);
                }
            }
            $this->propagerProprietairesParStructure($fallbackProprietaireId);
            // Créer les racines manquantes (ex. Ingénierie SI, Support) ajoutées au plan après un premier seed
            $ordre = Dossier::whereNull('parent_id')->max('ordre') ?? -1;
            foreach ($this->plan as $rootData) {
                if (! Dossier::whereNull('parent_id')->where('nom', $rootData['name'])->exists()) {
                    $ordre++;
                    $structureId = isset($rootData['type'], $this->typeToStructure[$rootData['type']])
                        ? Structure::where('code', $this->typeToStructure[$rootData['type']])->value('id')
                        : null;
                    $this->createDossier($rootData, null, $ordre, $createurId, $structureId, $fallbackProprietaireId);
                }
            }
            return;
        }

        $ordre = 0;
        foreach ($this->plan as $root) {
            $structureId = isset($root['type'], $this->typeToStructure[$root['type']])
                ? Structure::where('code', $this->typeToStructure[$root['type']])->value('id')
                : null;
            $this->createDossier($root, null, $ordre++, $createurId, $structureId, $fallbackProprietaireId);
        }
    }

    protected function createDossier(array $data, ?int $parentId, int $ordre, ?int $createurId = null, ?int $structureId = null, ?int $fallbackProprietaireId = null): Dossier
    {
        $typeCode = $data['type'] ?? null;
        $confidentiel = $typeCode === 'confidentiel';
        $notifySms = $confidentiel || ($data['notify_sms'] ?? false);
        $typeDossierId = $typeCode ? DB::table('type_dossiers')->where('code', $typeCode)->value('id') : null;

        $proprietaireId = $this->proprietaireIdPourStructure($structureId, $fallbackProprietaireId);

        $dossier = Dossier::create([
            'parent_id' => $parentId,
            'type_dossier_id' => $typeDossierId,
            'nom' => $data['name'],
            'code' => $this->generateCode($data['name'], $parentId),
            'type' => $typeCode,
            'description' => $data['description'] ?? null,
            'confidentiel' => $confidentiel,
            'notify_sms' => $notifySms,
            'actif' => true,
            'ordre' => $ordre,
            'createur_id' => $createurId,
            'proprietaire_id' => $proprietaireId,
            'structure_id' => $structureId,
        ]);

        $children = $data['children'] ?? [];
        $childOrdre = 0;
        foreach ($children as $child) {
            $this->createDossier($child, $dossier->id, $childOrdre++, $createurId, $structureId, $fallbackProprietaireId);
        }

        return $dossier;
    }

    /** Retourne le responsable de la structure ou le fallback (DG/admin) si aucun. */
    protected function proprietaireIdPourStructure(?int $structureId, ?int $fallbackProprietaireId): ?int
    {
        if (! $structureId) {
            return $fallbackProprietaireId;
        }
        $structure = Structure::find($structureId);

        return $structure?->responsable_id ?? $fallbackProprietaireId;
    }

    /** Met à jour proprietaire_id pour tous les dossiers selon leur structure (acteurs métier). */
    protected function propagerProprietairesParStructure(?int $fallbackProprietaireId): void
    {
        foreach (Dossier::all() as $dossier) {
            $proprietaireId = $this->proprietaireIdPourStructure($dossier->structure_id, $fallbackProprietaireId);
            if ($proprietaireId && $dossier->proprietaire_id !== $proprietaireId) {
                $dossier->update(['proprietaire_id' => $proprietaireId]);
            }
        }
    }

    protected function propagateStructureId(Dossier $dossier, int $structureId): void
    {
        $dossier->update(['structure_id' => $structureId]);
        foreach (Dossier::where('parent_id', $dossier->id)->get() as $child) {
            $this->propagateStructureId($child, $structureId);
        }
    }

    protected function generateCode(string $name, ?int $parentId): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        $slug = strtoupper(substr($slug, 0, 20));
        $slug = trim($slug, '-');

        if (Dossier::where('code', $slug)->exists()) {
            $slug .= '-' . substr(uniqid(), -4);
        }

        return $slug;
    }
}
