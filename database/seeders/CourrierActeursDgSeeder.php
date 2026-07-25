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
        $structures = Structure::query()
            ->whereIn('code', ['DG', 'SEC-DIR', 'SEC-DDSAIT', 'SEC-DAF', 'SEC-DAC', 'DAF', 'DDSAIT', 'DAC'])
            ->get()
            ->keyBy('code');

        foreach (['DG', 'SEC-DIR', 'SEC-DDSAIT', 'SEC-DAF', 'SEC-DAC', 'DAF', 'DAC'] as $code) {
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
            $user = User::updateOrCreate(
                ['email' => $acteur['email']],
                [
                    'name' => $acteur['name'],
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

        $this->command?->info('Acteurs courriers affectés : DG, secrétariats, DAF, DAC (agent comptable + particulière).');
    }
}
