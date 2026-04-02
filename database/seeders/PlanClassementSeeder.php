<?php

namespace Database\Seeders;

use App\Models\Dossier;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanClassementSeeder extends Seeder
{
    /**
     * none : aucun dossier (CI ou base vide volontaire).
     * dg_only : une arborescence exemple rattachée à la structure DG uniquement.
     * full (défaut) : plan multi-directions (Administration, Finance, Projets, etc.).
     */
    protected function planMode(): string
    {
        return env('SEED_PLAN_CLASSEMENT', 'full');
    }

    /**
     * Racine unique pour la démo DG : tout le sous-arbre hérite de la structure Direction Générale.
     */
    protected array $planDemoDirectionGenerale = [
        'name' => 'Direction Générale',
        'type' => 'administratif',
        'children' => [
            [
                'name' => 'Réunions et instances',
                'children' => [
                    ['name' => 'Comité de direction'],
                    ['name' => 'Instances consultatives'],
                ],
            ],
            [
                'name' => 'Décisions et instructions',
                'children' => [
                    ['name' => 'Notes de service'],
                    ['name' => 'Circulaires'],
                ],
            ],
            ['name' => 'Correspondance'],
            [
                'name' => 'Stratégie et reporting',
                'children' => [
                    ['name' => 'Rapports de pilotage'],
                    ['name' => 'Suivi des orientations'],
                ],
            ],
        ],
    ];

    /** Mapping type dossier → code structure (direction responsable). */
    protected array $typeToStructure = [
        // Aligné avec les codes générés par ACSIOrganigrammeSeeder (DIR-... / SVC-...).
        'administratif' => 'DIR-2000',
        'finance' => 'DIR-3000',
        'projet' => 'DIR-P000',
        'ingenierie_si' => 'DIR-4000',
        'support' => 'DIR-L000',
        'client' => 'DIR-K000',
        'operation' => 'DIR-N000',
        'archive' => 'DIR-3000',
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
            'name' => 'Développement et intégration des applications',
            'type' => 'projet',
            'structure_code' => 'SVC-DDI-DEVINT',
            'children' => [
                [
                    'name' => 'Développement',
                    'children' => [
                        ['name' => 'Spécifications fonctionnelles'],
                        ['name' => 'Conception applicative'],
                        ['name' => 'Implémentation'],
                    ],
                ],
                [
                    'name' => 'Intégration',
                    'children' => [
                        ['name' => 'Interfaces & APIs'],
                        ['name' => 'Tests d’intégration'],
                    ],
                ],
                [
                    'name' => 'Déploiement',
                    'children' => [
                        ['name' => 'CI/CD'],
                        ['name' => 'Guides de déploiement'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Conception et administration des bases de données',
            'type' => 'projet',
            'structure_code' => 'SVC-DDI-BDD',
            'children' => [
                [
                    'name' => 'Conception',
                    'children' => [
                        ['name' => 'Modèle conceptuel'],
                        ['name' => 'Modèle logique'],
                        ['name' => 'Schémas techniques'],
                    ],
                ],
                [
                    'name' => 'Administration',
                    'children' => [
                        ['name' => 'Sécurité des données'],
                        ['name' => 'Sauvegardes & restauration'],
                    ],
                ],
                [
                    'name' => 'Performances',
                    'children' => [
                        ['name' => 'Optimisation'],
                        ['name' => 'Requêtes & tuning'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Maintenance des systèmes applicatifs',
            'type' => 'projet',
            'structure_code' => 'SVC-DDI-MAINT',
            'children' => [
                [
                    'name' => 'Maintenance corrective',
                    'children' => [
                        ['name' => 'Correctifs'],
                        ['name' => 'Incidents'],
                    ],
                ],
                [
                    'name' => 'Maintenance évolutive',
                    'children' => [
                        ['name' => 'Améliorations'],
                        ['name' => 'Nouvelles fonctionnalités'],
                    ],
                ],
                [
                    'name' => 'Supervision',
                    'children' => [
                        ['name' => 'Monitoring'],
                        ['name' => 'Rapports de performance'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Veille et innovation technologique',
            'type' => 'projet',
            'structure_code' => 'SVC-DDI-VEILLE',
            'children' => [
                [
                    'name' => 'Veille technologique',
                    'children' => [
                        ['name' => 'Analyse tendances'],
                        ['name' => 'Veille concurrentielle'],
                    ],
                ],
                [
                    'name' => 'Prototypes & POC',
                    'children' => [
                        ['name' => 'Expérimentations'],
                        ['name' => 'Résultats & recommandations'],
                    ],
                ],
                [
                    'name' => 'Documentation',
                    'children' => [
                        ['name' => 'Rapports'],
                        ['name' => 'Référentiels'],
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

    /** @return list<array<string, mixed>> */
    protected function racinesPlan(): array
    {
        return match ($this->planMode()) {
            'none' => [],
            'dg_only' => [$this->planDemoDirectionGenerale],
            default => $this->plan,
        };
    }

    public function run(): void
    {
        if ($this->planMode() === 'none') {
            return;
        }

        // Suppression des anciens dossiers "projet" (on les recrée selon le nouveau modèle DDSAIT).
        Dossier::where('type', 'projet')->delete();

        $racines = $this->racinesPlan();
        if ($racines === []) {
            return;
        }

        $createurId = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->value('id');
        $dgId = User::whereHas('roles', fn ($q) => $q->where('name', 'dg'))->value('id');
        $fallbackProprietaireId = $dgId ?? $createurId;
        $structureIdDg = Structure::where('code', 'DG')->value('id');
        $structureIdDdsait = Structure::where('code', 'DDSAIT')->value('id');

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
            // Créer les racines manquantes selon le mode de plan
            $ordre = Dossier::whereNull('parent_id')->max('ordre') ?? -1;
            foreach ($racines as $rootData) {
                if (! Dossier::whereNull('parent_id')->where('nom', $rootData['name'])->exists()) {
                    $ordre++;
                    $structureId = $this->structureIdPourRacine($rootData, $structureIdDg);
                    $this->createDossier($rootData, null, $ordre, $createurId, $structureId, $fallbackProprietaireId, $structureIdDdsait);
                }
            }

            return;
        }

        $ordre = 0;
        foreach ($racines as $root) {
            $structureId = $this->structureIdPourRacine($root, $structureIdDg);
            $this->createDossier($root, null, $ordre++, $createurId, $structureId, $fallbackProprietaireId, $structureIdDdsait);
        }
    }

    /**
     * En mode dg_only, toute l'arborescence démo est rattachée à la structure DG (pas au mapping SEC-DIR du type administratif).
     */
    protected function structureIdPourRacine(array $rootData, ?int $structureIdDg): ?int
    {
        if ($this->planMode() === 'dg_only' && $structureIdDg) {
            return $structureIdDg;
        }

        if (! isset($rootData['type'], $this->typeToStructure[$rootData['type']])) {
            return null;
        }

        return Structure::where('code', $this->typeToStructure[$rootData['type']])->value('id');
    }

    protected function createDossier(
        array $data,
        ?int $parentId,
        int $ordre,
        ?int $createurId = null,
        ?int $structureId = null,
        ?int $fallbackProprietaireId = null,
        ?int $ddsaitDirectionId = null
    ): Dossier
    {
        $typeCode = $data['type'] ?? null;
        $confidentiel = $typeCode === 'confidentiel';
        $notifySms = $confidentiel || ($data['notify_sms'] ?? false);
        $typeDossierId = $typeCode ? DB::table('type_dossiers')->where('code', $typeCode)->value('id') : null;

        $nodeStructureId = $structureId;
        // Si le plan précise explicitement une structure, on s’y rattache (utile pour DDSAIT).
        $structureCode = $data['structure_code'] ?? null;
        if ($structureCode) {
            $resolved = Structure::where('code', $structureCode)->value('id');
            if ($resolved) {
                $nodeStructureId = (int) $resolved;
            }
        }

        $proprietaireId = $this->proprietaireIdPourStructure($nodeStructureId, $fallbackProprietaireId);

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
            'structure_id' => $nodeStructureId,
        ]);

        $children = $data['children'] ?? [];
        $childOrdre = 0;
        foreach ($children as $child) {
            $this->createDossier($child, $dossier->id, $childOrdre++, $createurId, $nodeStructureId, $fallbackProprietaireId, $ddsaitDirectionId);
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

        return $structure?->titulaireValidationActuel()?->id ?? $structure?->responsable_id ?? $fallbackProprietaireId;
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
            $slug .= '-'.substr(uniqid(), -4);
        }

        return $slug;
    }
}
