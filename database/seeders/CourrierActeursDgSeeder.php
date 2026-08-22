<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Affectation initiale des acteurs courriers (DG, secrétariats, directions, DAC).
 */
class CourrierActeursDgSeeder extends Seeder
{
    public function run(): void
    {
        $structureCodes = [
            'DG', 'SEC-DIR', 'SEC-DDSAIT', 'SEC-DAF', 'SEC-DAC',
            'DAF', 'DDSAIT', 'DAC',
            'DING-SI', 'DINFRA', 'DSUPPORT', 'DCOM',
        ];

        $structures = Structure::query()
            ->whereIn('code', $structureCodes)
            ->get()
            ->keyBy('code');

        foreach ($structureCodes as $code) {
            if (! $structures->has($code)) {
                $this->command?->warn("CourrierActeursDgSeeder : structure {$code} introuvable.");

                return;
            }
        }

        foreach ([
            'dg',
            'particulier_dg',
            'particulier_ac',
            'responsable_dossiers_prestataires',
            'responsable_suivi_depenses',
            'secretaire_direction',
            'directeur',
            'agent_comptable',
        ] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $fonctionDirecteurGeneral = Fonction::query()->where('code', 'directeur_general')->value('id');
        $fonctionDirecteurDirection = Fonction::query()->where('code', 'directeur_direction')->value('id');
        $fonctionAgentComptable = Fonction::query()->where('code', 'agent_comptable')->value('id');
        $password = Hash::make('password');

        $acteurs = [
            [
                'email' => '003057w@acsi.cg',
                'name' => 'LORD MARHYNO GANDOU',
                'role' => 'dg',
                'structure' => $structures['DG'],
                'pivot_role' => 'Directeur Général',
                'fonction_id' => $fonctionDirecteurGeneral,
            ],
            [
                'email' => '003144s@acsi.cg',
                'name' => 'LUCIENNE NGOUMBI NEE KELENGUE',
                'role' => 'particulier_dg',
                'structure' => $structures['SEC-DIR'],
                'pivot_role' => 'Particulière du DG',
                'fonction_id' => null,
            ],
            [
                'email' => '001958d@acsi.cg',
                'name' => 'ANNE LETHICIA TATY-TCHICAYA NÉE ND',
                'role' => 'responsable_dossiers_prestataires',
                'structure' => $structures['SEC-DIR'],
                'pivot_role' => 'Responsable dossiers prestataires / fournisseurs',
                'fonction_id' => null,
            ],
            [
                // Responsable suivi des dépenses — secrétariat / DG.
                'email' => '003091k@acsi.cg',
                'name' => 'ASTRIDE ELENI OSSEBI',
                'role' => 'responsable_suivi_depenses',
                'structure' => $structures['SEC-DIR'],
                'pivot_role' => 'Responsable suivi des dépenses',
                'fonction_id' => null,
            ],
            [
                'email' => '003064f@acsi.cg',
                'name' => 'BRIGITTE ESSONGA',
                'role' => 'secretaire_direction',
                'structure' => $structures['SEC-DDSAIT'],
                'pivot_role' => 'Secrétaire de direction',
                'fonction_id' => null,
            ],
            [
                'email' => '003063e@acsi.cg',
                'name' => 'RUTH JEMIMAH MAYELA',
                'role' => 'secretaire_direction',
                'structure' => $structures['SEC-DAF'],
                'pivot_role' => 'Secrétaire de direction',
                'fonction_id' => null,
            ],
            [
                'email' => '003329t@acsi.cg',
                'name' => 'NGONO MAMPASSI',
                'role' => 'directeur',
                'structure' => $structures['DAF'],
                'pivot_role' => 'Directeur administratif et financier',
                'fonction_id' => $fonctionDirecteurDirection,
            ],
            [
                'email' => '003152b@acsi.cg',
                'name' => 'BRICE GANGOUE',
                'role' => 'directeur',
                'structure' => $structures['DDSAIT'],
                'pivot_role' => 'Directeur DDSAIT',
                'fonction_id' => $fonctionDirecteurDirection,
            ],
            [
                'email' => '003012y@acsi.cg',
                'name' => 'DIRECTEUR INGENIERIE SI',
                'role' => 'directeur',
                'structure' => $structures['DING-SI'],
                'pivot_role' => 'Directeur DING-SI',
                'fonction_id' => $fonctionDirecteurDirection,
                'preserve_existing_name' => true,
            ],
            [
                'email' => '001966m@acsi.cg',
                'name' => 'DIRECTEUR INFRASTRUCTURES SI',
                'role' => 'directeur',
                'structure' => $structures['DINFRA'],
                'pivot_role' => 'Directeur DINFRA',
                'fonction_id' => $fonctionDirecteurDirection,
                'preserve_existing_name' => true,
            ],
            [
                'email' => '001957c@acsi.cg',
                'name' => 'DIRECTEUR SUPPORT ET FORMATION',
                'role' => 'directeur',
                'structure' => $structures['DSUPPORT'],
                'pivot_role' => 'Directeur DSUPPORT',
                'fonction_id' => $fonctionDirecteurDirection,
                'preserve_existing_name' => true,
            ],
            [
                'email' => '003330u@acsi.cg',
                'name' => 'DIRECTEUR COMMUNICATION',
                'role' => 'directeur',
                'structure' => $structures['DCOM'],
                'pivot_role' => 'Directeur DCOM',
                'fonction_id' => $fonctionDirecteurDirection,
                'preserve_existing_name' => true,
            ],
            [
                'email' => '003232b@acsi.cg',
                'name' => 'RAÏSSA LEBANITOU',
                'role' => 'agent_comptable',
                'structure' => $structures['DAC'],
                'pivot_role' => 'Agent Comptable',
                'fonction_id' => $fonctionAgentComptable,
            ],
            [
                'email' => '002871v@acsi.cg',
                'name' => 'NICOLE BIENVENUE OBA',
                'role' => 'particulier_ac',
                'structure' => $structures['SEC-DAC'],
                'pivot_role' => 'Particulière de l\'agent comptable',
                'fonction_id' => null,
            ],
        ];

        foreach ($acteurs as $acteur) {
            $existing = User::query()->where('email', $acteur['email'])->first();
            $name = $acteur['name'];
            if (
                ($acteur['preserve_existing_name'] ?? false)
                && $existing
                && filled($existing->name)
                && $existing->name !== $acteur['email']
                && ! str_starts_with($existing->name, 'DIRECTEUR ')
            ) {
                $name = $existing->name;
            }

            $user = User::updateOrCreate(
                ['email' => $acteur['email']],
                [
                    'name' => $name,
                    'password' => $password,
                    'structure_id' => $acteur['structure']->id,
                    'actif' => true,
                ]
            );

            $user->syncRoles([$acteur['role']]);

            $user->structures()->syncWithoutDetaching([
                $acteur['structure']->id => [
                    'role' => $acteur['pivot_role'],
                    'fonction_id' => $acteur['fonction_id'],
                    'date_affectation' => now(),
                    'date_fin' => null,
                ],
            ]);
        }

        // Ancien titulaire du rôle (re-seed) : retirer responsable_suivi_depenses.
        $ancienSuivi = User::where('email', '003269d@acsi.cg')->first();
        if ($ancienSuivi && $ancienSuivi->hasRole('responsable_suivi_depenses')) {
            $ancienSuivi->removeRole('responsable_suivi_depenses');
        }

        $this->command?->info('Acteurs courriers affectés : DG, secrétariats, DAF, DDSAIT, DING-SI, DINFRA, DSUPPORT, DCOM, DAC.');
    }
}
