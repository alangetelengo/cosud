<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ACSIUsersFromExcelSeeder extends Seeder
{
    public function run(): void
    {
        // Le JSON est généré à partir du fichier Excel côté machine (voir script python).
        $dataPath = database_path('seeders/data/acsi_agents.json');
        if (! file_exists($dataPath)) {
            $this->command?->warn("Fichier introuvable : {$dataPath}");
            return;
        }

        $rows = json_decode(file_get_contents($dataPath) ?: '[]', true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($rows) || $rows === []) {
            return;
        }

        // Mapping fonctions métier -> codes (cf FonctionSeeder.php)
        $fonctionIds = Fonction::query()
            ->whereIn('code', ['directeur_general', 'directeur_direction', 'chef_service', 'chef_projet', 'chef_pool'])
            ->pluck('id', 'code');

        $structureIds = Structure::query()
            ->whereIn('code', ['DG', 'DAF', 'DDSAIT', 'DING-SI', 'DINFRA', 'DSUPPORT', 'DCOM', 'SEC-DIR', 'SJUR', 'CCG', 'SVC-DDI-DEVINT', 'SVC-DDI-BDD', 'SVC-DDI-MAINT', 'SVC-DDI-VEILLE', 'SVC-FIN', 'ANT'])
            ->pluck('id', 'code');

        $password = Hash::make('password');

        foreach ($rows as $r) {
            $matricule = trim((string) ($r['matricule'] ?? ''));
            $prenom = trim((string) ($r['prenom'] ?? ''));
            $nom = trim((string) ($r['nom'] ?? ''));
            $libDirection = (string) ($r['libDirection'] ?? '');
            $libService = (string) ($r['libService'] ?? '');
            $libFonction = $r['libFonction'] ?? null;
            $libEmploi = $r['libEmploi'] ?? null;

            if ($matricule === '') {
                continue;
            }

            // Email : garant de l'unicité via matricule.
            $email = $matricule . '@acsi.cg';

            $structureCode = $this->structureCodeForAgent($libDirection, $libService);
            $structureId = $structureIds->get($structureCode);
            if (! $structureId) {
                // Si aucun code n'est trouvé, on rattache au DG (par défaut).
                $structureCode = 'DG';
                $structureId = $structureIds->get('DG');
                if (! $structureId) {
                    continue;
                }
            }

            [$roleSpatie, $pivotFonctionCode] = $this->roleAndPivotFonctionForAgent($libFonction, $libEmploi);

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => trim(($prenom !== '' ? $prenom . ' ' : '') . $nom) ?: ($nom ?: $email),
                    'password' => $password,
                    'structure_id' => $structureId,
                    'actif' => true,
                ]
            );

            $user->syncRoles([$roleSpatie]);

            // Affectation : indispensable pour que structureIdsGerees()/titulaireValidationActuel() fonctionnent.
            $pivotFonctionId = $pivotFonctionCode ? ($fonctionIds->get($pivotFonctionCode) ?? null) : null;
            $user->structures()->syncWithoutDetaching([
                $structureId => [
                    'role' => $pivotFonctionCode ? $pivotFonctionCode : null,
                    'fonction_id' => $pivotFonctionId,
                    'date_affectation' => now(),
                    'date_fin' => null,
                ],
            ]);
        }
    }

    private function norm(?string $s): string
    {
        $s = $s ?? '';
        $s = trim($s);
        $s = mb_strtoupper($s, 'UTF-8');

        // Normalisation légère : espaces multiples -> 1.
        $s = preg_replace('/\\s+/', ' ', $s) ?: $s;

        return $s;
    }

    /**
     * Choisit le code `structures.code` à partir de libDirection/libService.
     * Objectif : garder notre structure hiérarchique existante (codes) tout en collant au document réel.
     */
    private function structureCodeForAgent(?string $libDirection, ?string $libService): string
    {
        $d = $this->norm($libDirection);
        $s = $this->norm($libService);

        // DIRECTION GENERALE (rattachement aux 3 entités centrales)
        if (str_contains($d, 'DIRECTION GENERALE')) {
            if (str_contains($s, 'SECRET') && str_contains($s, 'DG')) {
                return 'SEC-DIR';
            }
            if (str_contains($s, 'DTION') || str_contains($s, 'DTION ADM')) {
                return 'SJUR';
            }
            if (str_contains($s, 'CTLE') || str_contains($s, 'GESTION')) {
                return 'CCG';
            }
            return 'DG';
        }

        // FINANCE / ADMINFIN
        if (str_contains($d, 'FINANCI') || str_contains($d, 'COMPTABLE')) {
            if (str_contains($s, 'COMPTABIL')) {
                return 'SVC-FIN';
            }

            return 'DAF';
        }

        // TECHNIQUE / DDSAIT
        if (str_contains($d, 'TECHNIQUE')) {
            if (str_contains($s, 'SYSTEME D') || str_contains($s, 'SYSTEME') && str_contains($s, 'EXPL')) {
                return 'SVC-DDI-DEVINT';
            }
            if (str_contains($s, 'FORMATION') && str_contains($s, 'BASE DE DONNEE')) {
                return 'SVC-DDI-BDD';
            }
            if (str_contains($s, 'MAINT') || str_contains($s, 'RESEAU') || str_contains($s, 'SECTION MAINTENANCE')) {
                return 'SVC-DDI-MAINT';
            }
            if (str_contains($s, 'VEILLE') || str_contains($s, 'INNOVATION')) {
                return 'SVC-DDI-VEILLE';
            }
            if (str_contains($s, 'SERVICE ETUDES') || $s === 'SERVICE DES ETUDES' || str_contains($s, 'ETUDES')) {
                return 'SVC-DDI-DEVINT';
            }
            if (str_contains($s, 'CENTRE')) {
                // Centres (chef de centre) : on les mappe sur le périmètre DDSAIT technique pour préserver le workflow.
                return 'SVC-DDI-DEVINT';
            }

            return 'DDSAIT';
        }

        // COMMERCIAL / DCOM
        if (str_contains($d, 'COMMERCIALE') || str_contains($d, 'COMMERCIAL')) {
            return 'DCOM';
        }

        // DEP. (antenne)
        if (str_contains($d, 'DEPARTEMENTALE') || str_contains($d, 'KOUILOU')) {
            return 'ANT';
        }

        // SUPPORT TECH. -> DSUPPORT (fallback)
        if (str_contains($d, 'SUPPORT TECH')) {
            return 'DSUPPORT';
        }

        // ADMIN / RH (fallback sur une entité centrale)
        if (str_contains($d, 'ADMINISTRATIVE ET DU PERSONNEL') || str_contains($d, 'PERSONNEL')) {
            if (str_contains($s, 'DTION')) {
                return 'SJUR';
            }

            return 'SEC-DIR';
        }

        // Dernier fallback
        return 'DG';
    }

    /**
     * @return array{0:string,1:?string} [roleSpatie, pivotFonctionCode]
     */
    private function roleAndPivotFonctionForAgent(?string $libFonction, ?string $libEmploi): array
    {
        $lf = $this->norm($libFonction);
        $le = $this->norm($libEmploi);

        $probe = $lf !== '' && $lf !== 'NAN' ? $lf : $le;

        // Directeurs
        if (str_contains($probe, 'DIRECTEUR GENERAL')) {
            return ['dg', 'directeur_general'];
        }
        if (str_contains($probe, 'DIRECTEUR')) {
            return ['directeur', 'directeur_direction'];
        }

        // Workflow projet (preset DDSAIT)
        if (str_contains($probe, 'CHEF DE PROJET') || str_contains($probe, 'CHEF PROJET')) {
            return ['chef_projet', 'chef_projet'];
        }
        if (str_contains($probe, 'ADJOINT')) {
            // Chef adjoint = même permission que chef de projet (workflow).
            return ['chef_projet', 'chef_projet'];
        }

        // Chef de centre (étape 2 du workflow) : role Spatie = chef_centre,
        // mais fonction pivot = chef_service pour que la logique de visibilité par structure reste cohérente.
        if (str_contains($probe, 'CHEF DE CENTRE')) {
            return ['chef_centre', 'chef_service'];
        }

        // Chef d'application : pas de fonction dédiée dans le preset actuel,
        // on le rattache au rôle "chef_service" (étape finale).
        if (str_contains($probe, 'CHEF D\'APPLICATION') || str_contains($probe, 'CHEF D APPLICATION')) {
            return ['chef_service', 'chef_service'];
        }

        // Chef service/section : étape finale du workflow
        if (str_contains($probe, 'CHEF SERVICE') || str_contains($probe, 'CHEF DE SECTION') || str_contains($probe, 'CHEF SCE') || str_contains($probe, 'CONTROLEUR DE GESTION')) {
            return ['chef_service', 'chef_service'];
        }

        return ['utilisateur', null];
    }
}

