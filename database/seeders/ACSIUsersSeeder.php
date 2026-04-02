<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ACSIUsersSeeder extends Seeder
{
    public function run(): void
    {
        $rows = $this->loadRows();
        if ($rows === []) {
            return;
        }

        $password = Hash::make('password');

        $fonctions = Fonction::pluck('id', 'code');

        foreach ($rows as $r) {
            $matricule = trim((string) ($r['matricule'] ?? ''));
            if ($matricule === '') {
                continue;
            }

            $nom = trim((string) ($r['nom'] ?? ''));
            $prenom = trim((string) ($r['prenom'] ?? ''));
            $name = trim(($prenom !== '' ? $prenom.' ' : '').$nom);
            $email = strtolower($matricule).'@acsi.cg';

            $libDirection = (string) ($r['libDirection'] ?? '');
            $libService = (string) ($r['libService'] ?? '');
            $directionCode = $this->codeDirection($r['codeDirection'] ?? null, $libDirection);
            $serviceCode = $this->codeService($r['codeService'] ?? null, $libService, $directionCode);
            [$roles, $pivotFonctionCode] = $this->rolesEtFonctionPivot($r['libFonction'] ?? null, $r['libEmploi'] ?? null);
            $forcedStructureCode = $this->forcedStructureCodeForUser($matricule, $email);
            if ($forcedStructureCode !== null) {
                $serviceCode = $forcedStructureCode;
            }

            if ($this->isCentre($libService)) {
                // CENTRE ignoré : on rattache au niveau direction (temporaire).
                $serviceCode = null;
            }
            // Certains enregistrements "directeur" portent un code service générique (ex: P000).
            // On force alors le rattachement à la direction, pour une chaîne hiérarchique correcte.
            if (in_array($pivotFonctionCode, ['directeur_direction', 'directeur_general'], true)) {
                $serviceCode = null;
            }

            $structure = null;
            if ($serviceCode !== null) {
                $structure = Structure::where('code', $serviceCode)->first();
            }
            if (! $structure) {
                $structure = Structure::where('code', $directionCode)->first();
            }
            if (! $structure) {
                $structure = Structure::where('code', 'DG')->first();
            }
            if (! $structure) {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name !== '' ? $name : $email,
                    'password' => $password,
                    'structure_id' => $structure->id,
                    'actif' => true,
                ]
            );

            $user->syncRoles($roles);

            $pivotFonctionId = $pivotFonctionCode ? ($fonctions[$pivotFonctionCode] ?? null) : null;

            // Affectation à la structure “courante” de l’agent (service si possible)
            $user->structures()->syncWithoutDetaching([
                $structure->id => [
                    'role' => $pivotFonctionCode,
                    'fonction_id' => $pivotFonctionId,
                    'date_affectation' => now(),
                    'date_fin' => null,
                ],
            ]);

            // Cas ciblé: garder une seule affectation active sur la structure imposée.
            if ($forcedStructureCode !== null) {
                DB::table('user_structure')
                    ->where('user_id', $user->id)
                    ->whereNull('date_fin')
                    ->where('structure_id', '!=', $structure->id)
                    ->update([
                        'date_fin' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }

        $this->enforceCriticalAssignments($fonctions->toArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRows(): array
    {
        $path = database_path('seeders/data/acsi_agents_full.json');
        if (! file_exists($path)) {
            $this->command?->warn("Fichier introuvable : {$path}");
            return [];
        }
        $rows = json_decode(file_get_contents($path) ?: '[]', true);
        return is_array($rows) ? $rows : [];
    }

    private function norm($s): string
    {
        $s = trim((string) ($s ?? ''));
        $s = mb_strtoupper($s, 'UTF-8');
        return preg_replace('/\\s+/', ' ', $s) ?: $s;
    }

    private function isCentre(string $libService): bool
    {
        $s = $this->norm($libService);
        return str_starts_with($s, 'CENTRE');
    }

    private function codeDirection($codeDirection, $libDirection = null): string
    {
        $lib = $this->norm((string) ($libDirection ?? ''));
        if (
            str_contains($lib, 'DIRECTION DEV')
            && str_contains($lib, 'SYS')
            && str_contains($lib, 'APPL')
            && str_contains($lib, 'INNOV')
        ) {
            return 'DDSAIT';
        }
        $raw = trim((string) ($codeDirection ?? ''));
        $raw = $raw !== '' ? $raw : '0000';
        return 'DIR-'.$raw;
    }

    private function codeService($codeService, string $libService = '', string $directionCode = ''): ?string
    {
        $canonicalCode = $this->canonicalDdsaitServiceCode($libService);
        if ($canonicalCode !== null) {
            return $canonicalCode;
        }

        $lib = $this->norm($libService);
        if ($directionCode === 'DDSAIT') {
            if (str_contains($lib, 'SYSTEME D') || str_contains($lib, 'EXPL PROD') || str_contains($lib, 'INTEGRATION')) {
                return 'SVC-DDI-DEVINT';
            }
            if (str_contains($lib, 'BASE DE DONNEE') || str_contains($lib, 'BDD') || str_contains($lib, 'FORMATION')) {
                return 'SVC-DDI-BDD';
            }
            if (str_contains($lib, 'MAINT') || str_contains($lib, 'RESEAU')) {
                return 'SVC-DDI-MAINT';
            }
            if (str_contains($lib, 'VEILLE') || str_contains($lib, 'INNOVATION')) {
                return 'SVC-DDI-VEILLE';
            }
        }
        $raw = trim((string) ($codeService ?? ''));
        if ($raw === '') {
            return null;
        }
        return 'SVC-'.$raw;
    }

    private function canonicalDdsaitServiceCode(string $libService): ?string
    {
        $lib = $this->norm($libService);
        if (str_contains($lib, 'DEVELOPPEMENT') && str_contains($lib, 'INTEGRATION')) {
            return 'SVC-DDI-DEVINT';
        }
        if (str_contains($lib, 'BASE DE DONNEE') || str_contains($lib, 'BASES DE DONNEES') || str_contains($lib, 'BDD')) {
            return 'SVC-DDI-BDD';
        }
        if (str_contains($lib, 'MAINTENANCE') || str_contains($lib, 'RESEAU')) {
            return 'SVC-DDI-MAINT';
        }
        if (str_contains($lib, 'VEILLE') || str_contains($lib, 'INNOVATION')) {
            return 'SVC-DDI-VEILLE';
        }

        return null;
    }

    /**
     * Règles ACSI (version initiale) :
     * - on ignore CHEF DE CENTRE
     * - on utilise libFonction quand présent, sinon libEmploi (faible poids).
     *
     * @return array{0:list<string>,1:?string} [rolesSpatie, pivotFonctionCode]
     */
    private function rolesEtFonctionPivot($libFonction, $libEmploi): array
    {
        $lf = $this->norm($libFonction);
        $le = $this->norm($libEmploi);
        $probe = ($lf !== '' && $lf !== 'NAN') ? $lf : $le;

        // DG
        if (str_contains($probe, 'DIRECTEUR GENERAL')) {
            return [['dg'], 'directeur_general'];
        }

        // Directions
        if (str_contains($probe, 'DIRECTEUR CENTRAL') || str_contains($probe, 'DIRECTEUR DEPARTEMENTAL')) {
            return [['directeur'], 'directeur_direction'];
        }

        // Services : responsables hiérarchiques
        if (str_contains($probe, 'CHEF SERVICE') || str_contains($probe, 'CHEF DE SECTION')) {
            return [['chef_service'], 'chef_service'];
        }

        // Projet : rôle métier (peut être utilisé dans le workflow si configuré)
        if (str_contains($probe, 'CHEF DE PROJET') || str_contains($probe, 'CHEF PROJET') || str_contains($probe, 'ADJOINT')) {
            return [['chef_projet'], 'chef_projet'];
        }

        if (str_contains($probe, "CHEF D'APPLICATION") || str_contains($probe, 'CHEF D APPLICATION')) {
            // Pour l’instant, on l'aligne sur chef_service (responsable d'un sous-ensemble)
            return [['chef_service'], 'chef_service'];
        }

        // On ignore CHEF DE CENTRE (non utilisé, en voie de suppression)
        if (str_contains($probe, 'CHEF DE CENTRE')) {
            return [['utilisateur'], null];
        }

        return [['utilisateur'], null];
    }

    private function forcedStructureCodeForUser(string $matricule, string $email): ?string
    {
        $m = strtolower(trim($matricule));
        $e = strtolower(trim($email));

        // DG principal -> structure DG (racine de la chaîne hiérarchique).
        if ($m === '003057w' || $e === '003057w@acsi.cg') {
            return 'DG';
        }

        // Otniel Gabrieli MBOUNGOU -> SERVICE DU DEVELOPPEMENT ET DE L'INTEGRATION DES APPLICATIONS.
        if ($m === '003202f' || $e === '003202f@acsi.cg') {
            return 'SVC-DDI-DEVINT';
        }
        // Agent de test de la chaîne DDSAIT (agent -> chef_service -> directeur -> DG).
        if ($m === '003248f' || $e === '003248f@acsi.cg') {
            return 'SVC-DDI-DEVINT';
        }
        // Brice GANGOUE -> Directeur de la DDSAIT.
        if ($m === '003152b' || $e === '003152b@acsi.cg') {
            return 'DDSAIT';
        }

        return null;
    }

    /** @param array<string,int> $fonctions */
    private function enforceCriticalAssignments(array $fonctions): void
    {
        // Otniel doit être chef_service de SVC-DDI-DEVINT.
        $this->forceUserStructure(
            '003202f@acsi.cg',
            'SVC-DDI-DEVINT',
            (int) ($fonctions['chef_service'] ?? 0),
            'chef_service'
        );

        // Agent de test de bout en bout rattaché au service DDSAIT.
        $this->forceUserStructureSansFonction(
            '003248f@acsi.cg',
            'SVC-DDI-DEVINT',
            'utilisateur'
        );

        // Brice GANGOUE doit être directeur de la DDSAIT.
        $this->forceUserStructure(
            '003152b@acsi.cg',
            'DDSAIT',
            (int) ($fonctions['directeur_direction'] ?? 0),
            'directeur_direction'
        );

        // DG principal doit être porté sur la structure DG (racine).
        $this->forceUserStructure(
            '003057w@acsi.cg',
            'DG',
            (int) ($fonctions['directeur_general'] ?? 0),
            'directeur_general'
        );
    }

    private function forceUserStructure(string $email, string $structureCode, int $fonctionId, string $role): void
    {
        if ($fonctionId < 1) {
            return;
        }

        $user = User::where('email', strtolower($email))->first();
        $structure = Structure::where('code', $structureCode)->first();
        if (! $user || ! $structure) {
            return;
        }

        DB::table('user_structure')
            ->where('user_id', $user->id)
            ->whereNull('date_fin')
            ->where('structure_id', '!=', $structure->id)
            ->update([
                'date_fin' => now(),
                'updated_at' => now(),
            ]);

        $user->structures()->syncWithoutDetaching([
            $structure->id => [
                'role' => $role,
                'fonction_id' => $fonctionId,
                'date_affectation' => now(),
                'date_fin' => null,
            ],
        ]);

        if ((int) ($user->structure_id ?? 0) !== (int) $structure->id) {
            $user->forceFill(['structure_id' => $structure->id])->save();
        }
    }

    private function forceUserStructureSansFonction(string $email, string $structureCode, string $role): void
    {
        $user = User::where('email', strtolower($email))->first();
        $structure = Structure::where('code', $structureCode)->first();
        if (! $user || ! $structure) {
            return;
        }

        DB::table('user_structure')
            ->where('user_id', $user->id)
            ->whereNull('date_fin')
            ->where('structure_id', '!=', $structure->id)
            ->update([
                'date_fin' => now(),
                'updated_at' => now(),
            ]);

        $user->structures()->syncWithoutDetaching([
            $structure->id => [
                'role' => $role,
                'fonction_id' => null,
                'date_affectation' => now(),
                'date_fin' => null,
            ],
        ]);

        if ((int) ($user->structure_id ?? 0) !== (int) $structure->id) {
            $user->forceFill(['structure_id' => $structure->id])->save();
        }
    }
}

