<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\JournalAudit;
use App\Models\StatutCourrier;
use Illuminate\Support\Facades\DB;

class CourrierWorkflowService
{
    public function __construct(
        private readonly CourrierNotificationService $courrierNotifications,
    ) {}

    public function transitionner(Courrier $courrier, string $codeStatut, array $donnees = []): Courrier
    {
        if (! $courrier->peutTransitionnerVers($codeStatut)) {
            throw new \InvalidArgumentException("Transition impossible vers le statut {$codeStatut}.");
        }

        $statut = StatutCourrier::query()
            ->where('sens_courrier_id', $courrier->sens_courrier_id)
            ->where('code', $codeStatut)
            ->where('actif', true)
            ->firstOrFail();

        $ancienCode = $courrier->statutCourrier?->code;

        return DB::transaction(function () use ($courrier, $statut, $donnees, $ancienCode, $codeStatut) {
            $courrier->statut_courrier_id = $statut->id;
            $courrier->fill($donnees);
            $courrier->save();

            JournalAudit::log('courrier.transition', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'de' => $ancienCode,
                    'vers' => $codeStatut,
                ]),
            ]);

            $fresh = $courrier->fresh([
                'sensCourrier', 'statutCourrier', 'typeCourrier', 'prioriteCourrier', 'parapheur', 'createur',
            ]);

            if ($codeStatut === 'cloture' && $fresh) {
                $this->courrierNotifications->notifierExpediteurExterneTraite($fresh);
            }

            return $fresh;
        });
    }

    /**
     * Clôture l’arrivée liée après expédition du départ réponse.
     * Contourne les transitions classiques (recu → …) : le traitement métier est terminé.
     */
    public function cloturerArriveeLieeApresExpedition(Courrier $depart): ?Courrier
    {
        $arrivee = $depart->courrierParent;
        if (! $arrivee || ! $arrivee->estArrivee()) {
            return null;
        }

        if ($arrivee->statutCourrier?->code === 'cloture') {
            return $arrivee;
        }

        $statut = StatutCourrier::query()
            ->where('sens_courrier_id', $arrivee->sens_courrier_id)
            ->where('code', 'cloture')
            ->where('actif', true)
            ->firstOrFail();

        return DB::transaction(function () use ($arrivee, $statut, $depart) {
            $ancienCode = $arrivee->statutCourrier?->code;
            $arrivee->statut_courrier_id = $statut->id;
            $arrivee->save();

            JournalAudit::log('courrier.transition', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $arrivee->id,
                    'de' => $ancienCode,
                    'vers' => 'cloture',
                    'motif' => 'cloture_auto_apres_expedition_reponse',
                    'depart_id' => $depart->id,
                ]),
            ]);

            $fresh = $arrivee->fresh([
                'sensCourrier', 'statutCourrier', 'typeCourrier', 'prioriteCourrier', 'parapheur', 'createur',
            ]);

            if ($fresh) {
                $this->courrierNotifications->notifierExpediteurExterneTraite($fresh);
            }

            return $fresh;
        });
    }

    public function statutInitialPourSens(int $sensCourrierId): StatutCourrier
    {
        return StatutCourrier::query()
            ->where('sens_courrier_id', $sensCourrierId)
            ->where('est_initial', true)
            ->where('actif', true)
            ->orderBy('ordre')
            ->firstOrFail();
    }
}
