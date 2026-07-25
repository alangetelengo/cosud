<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourrierSecretariatService
{
    public function directeurPourSecretariat(?Structure $structure): ?User
    {
        if (! $structure) {
            return null;
        }

        $direction = $structure->directionGestionCourrier();
        if (! $direction) {
            return null;
        }

        $directeur = User::role('directeur')
            ->where('structure_id', $direction->id)
            ->orderBy('id')
            ->first();

        if ($directeur) {
            return $directeur;
        }

        // Direction Générale : le titulaire porte le rôle Spatie « dg », pas « directeur ».
        if ($direction->code === 'DG') {
            $directeurGeneral = User::role('dg')
                ->where('structure_id', $direction->id)
                ->orderBy('id')
                ->first();

            if ($directeurGeneral) {
                return $directeurGeneral;
            }
        }

        return $direction->titulaireValidationActuel();
    }

    /**
     * @return Collection<int, User>
     */
    public function secretairesPourStructure(?Structure $structure): Collection
    {
        if (! $structure) {
            return collect();
        }

        return User::query()
            ->where('actif', true)
            ->where('structure_id', $structure->id)
            ->get()
            ->filter(fn (User $user) => $user->gereCourrierSecretariat());
    }

    public function estDestinataireInterne(?int $structureId): bool
    {
        if (! $structureId) {
            return false;
        }

        return Structure::secretariatsDirections()->whereKey($structureId)->exists();
    }

    public function creerArriveeDepuisDepart(Courrier $depart, User $recepteur): Courrier
    {
        if (! $depart->estDepart()) {
            throw new \InvalidArgumentException('Seul un courrier départ peut générer une arrivée interne.');
        }

        $sensArrivee = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statutRecu = StatutCourrier::query()
            ->where('sens_courrier_id', $sensArrivee->id)
            ->where('code', 'recu')
            ->where('actif', true)
            ->firstOrFail();

        return DB::transaction(function () use ($depart, $recepteur, $sensArrivee, $statutRecu) {
            $nums = app(CourrierNumeroRegistreService::class)->prochainNumero((int) $sensArrivee->id);

            $arrivee = Courrier::create([
                'sens_courrier_id' => $sensArrivee->id,
                'type_courrier_id' => $depart->type_courrier_id,
                'statut_courrier_id' => $statutRecu->id,
                'priorite_courrier_id' => $depart->priorite_courrier_id,
                'numero_registre' => $nums['numero_registre'],
                'numero_registre_annee' => $nums['numero_registre_annee'],
                'origine' => 'interne',
                'date_reception' => now()->toDateString(),
                'date_courrier' => $depart->date_courrier,
                'expediteur_libelle' => $depart->structure?->nom ?? $depart->createur?->structure?->nom,
                'structure_expediteur_id' => $depart->structure_id,
                'objet' => $depart->objet,
                'est_expediteur_externe' => false,
                'courrier_depart_source_id' => $depart->id,
                'createur_id' => $recepteur->id,
                'structure_id' => $recepteur->structure_id,
            ]);

            $documentIds = $depart->documents()->pluck('documents.id');
            foreach ($documentIds as $documentId) {
                $arrivee->documents()->attach($documentId, ['est_principal' => false]);
            }

            $depart->update(['courrier_arrivee_lie_id' => $arrivee->id]);

            return $arrivee->fresh(['sensCourrier', 'statutCourrier', 'documents']);
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function particulieresDg(): Collection
    {
        return User::query()
            ->role('particulier_dg')
            ->where('actif', true)
            ->orderBy('name')
            ->get();
    }

    public function secretariatPourDirection(Structure $direction): ?Structure
    {
        if (! $direction->estDirection()) {
            return null;
        }

        return Structure::secretariatsDirections()
            ->where('parent_id', $direction->id)
            ->orderBy('id')
            ->first();
    }
}
