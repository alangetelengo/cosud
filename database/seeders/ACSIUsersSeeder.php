<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Importe la liste des agents ACSI (identité + compte) sans affectation.
 * Direction, rôle et fonction sont configurés ensuite par l’administrateur.
 */
class ACSIUsersSeeder extends Seeder
{
    public function run(): void
    {
        $rows = $this->loadRows();
        if ($rows === []) {
            return;
        }

        $password = Hash::make('password');

        foreach ($rows as $r) {
            $matricule = trim((string) ($r['matricule'] ?? ''));
            if ($matricule === '') {
                continue;
            }

            $nom = trim((string) ($r['nom'] ?? ''));
            $prenom = trim((string) ($r['prenom'] ?? ''));
            $name = trim(($prenom !== '' ? $prenom.' ' : '').$nom);
            $email = strtolower($matricule).'@acsi.cg';

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name !== '' ? $name : $email,
                    'password' => $password,
                    'structure_id' => null,
                    'actif' => true,
                ]
            );
        }
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
}
