<?php

namespace App\Services;

use App\Models\CircuitCourrierHistorique;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Support\Collection;

class CourrierRetardService
{
    public function delaiHeures(): int
    {
        return max(1, (int) config('cosud.circuit_retard_heures', 48));
    }

    public function rappelHeures(): int
    {
        return max(1, (int) config('cosud.circuit_retard_rappel_heures', 24));
    }

    /**
     * @return Collection<int, Courrier>
     */
    public function courriersEnRetard(): Collection
    {
        $seuil = now()->subHours($this->delaiHeures());

        return Courrier::query()
            ->whereNotNull('circuit_etape_actuelle_id')
            ->whereNotNull('circuit_etape_depuis')
            ->where('circuit_etape_depuis', '<=', $seuil)
            ->with(['circuitEtapeActuelle', 'circuit', 'createur', 'statutCourrier', 'sensCourrier'])
            ->orderBy('circuit_etape_depuis')
            ->get();
    }

    /**
     * @return Collection<int, Courrier>
     */
    public function courriersAAlerter(): Collection
    {
        $rappelAvant = now()->subHours($this->rappelHeures());

        return $this->courriersEnRetard()
            ->filter(function (Courrier $c) use ($rappelAvant) {
                return $c->dernier_alerte_retard_at === null
                    || $c->dernier_alerte_retard_at->lte($rappelAvant);
            })
            ->values();
    }

    public function alerterRetards(?User $systeme = null): int
    {
        $acteur = $systeme ?? User::role('admin')->where('actif', true)->first()
            ?? User::role('dg')->where('actif', true)->first();

        if (! $acteur) {
            return 0;
        }

        $notifications = app(CourrierNotificationService::class);
        $count = 0;

        foreach ($this->courriersAAlerter() as $courrier) {
            $etape = $courrier->circuitEtapeActuelle;
            $heures = (int) $courrier->circuit_etape_depuis?->diffInHours(now());
            $detail = sprintf(
                'Étape « %s » en attente depuis %d h (seuil %d h). Responsable attendu : %s.',
                $etape?->nom ?? '—',
                $heures,
                $this->delaiHeures(),
                $etape?->libelleActeur() ?? '—'
            );

            $notifications->notifierRoles(
                ['dg'],
                $courrier,
                $acteur,
                CourrierNotificationService::RETARD_TRAITEMENT,
                $detail
            );

            CircuitCourrierHistorique::create([
                'courrier_id' => $courrier->id,
                'circuit_courrier_etape_id' => $etape?->id,
                'user_id' => $acteur->id,
                'evenement' => 'alerte_retard',
                'commentaire' => $detail,
            ]);

            $courrier->dernier_alerte_retard_at = now();
            $courrier->save();
            $count++;
        }

        return $count;
    }

    public function relancer(Courrier $courrier, User $dg, ?string $commentaire = null): void
    {
        $etape = $courrier->circuitEtapeActuelle;
        $roles = [];
        if ($etape?->acteur_type === 'role' && $etape->acteur_valeur) {
            $roles[] = $etape->acteur_valeur;
        }
        if ($etape?->acteur_type === 'dg') {
            $roles[] = 'dg';
        }
        if ($etape?->acteur_type === 'secretariat') {
            $roles = ['secretaire_direction', 'particulier_dg', 'responsable_dossiers_prestataires', 'responsable_suivi_depenses'];
        }
        $roles = array_merge($roles, $etape?->notifie_roles ?? []);

        $detail = $commentaire
            ?: ('Relance DG sur l’étape : '.($etape?->nom ?? '—'));

        app(CourrierNotificationService::class)->notifierRoles(
            $roles,
            $courrier,
            $dg,
            CourrierNotificationService::RELANCE,
            $detail
        );

        CircuitCourrierHistorique::create([
            'courrier_id' => $courrier->id,
            'circuit_courrier_etape_id' => $etape?->id,
            'user_id' => $dg->id,
            'evenement' => 'relance',
            'commentaire' => $detail,
        ]);
    }

    public function heuresAttente(Courrier $courrier): ?int
    {
        if (! $courrier->circuit_etape_depuis) {
            return null;
        }

        return (int) $courrier->circuit_etape_depuis->diffInHours(now());
    }
}
