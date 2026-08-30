<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\Document;
use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CourrierEnregistrementService
{
    public function __construct(
        private readonly CourrierWorkflowService $workflowService,
    ) {}

    public function annuler(Courrier $courrier, User $acteur, ?string $motif): Courrier
    {
        if ($courrier->estArrivee()) {
            if (! $courrier->peutTransitionnerVers('annule')) {
                throw new InvalidArgumentException('Ce courrier ne peut pas être annulé à ce stade.');
            }
        } elseif (! $courrier->estDepart()) {
            throw new InvalidArgumentException('Type de courrier non pris en charge.');
        }

        $etaitChezDirecteur = $courrier->statutCourrier?->code === 'transmis_directeur';

        $courrier = $this->workflowService->transitionner($courrier, 'annule', [
            'motif_rejet' => $motif,
            'rejete_par_id' => $acteur->id,
            'date_rejet' => now(),
        ]);

        JournalAudit::log('courrier.annule', 'courriers', [
            'commentaire' => json_encode([
                'courrier_id' => $courrier->id,
                'numero' => $courrier->numeroRegistreComplet(),
                'sens' => $courrier->sensCourrier?->code,
                'motif' => $motif,
                'acteur_id' => $acteur->id,
                'etait_chez_directeur' => $etaitChezDirecteur,
            ]),
        ]);

        return $courrier;
    }

    public function supprimer(Courrier $courrier, User $acteur): void
    {
        DB::transaction(function () use ($courrier, $acteur): void {
            $courrierVerrouille = Courrier::query()
                ->whereKey($courrier->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $courrierVerrouille->peutSupprimerEnregistrement()) {
                throw new InvalidArgumentException('Ce courrier ne peut plus être supprimé.');
            }

            $numero = $courrierVerrouille->numeroRegistreComplet();
            $sens = $courrierVerrouille->sensCourrier?->code;

            $documents = $courrierVerrouille->documents()->get();
            $courrierVerrouille->documents()->detach();

            foreach ($documents as $document) {
                $this->supprimerSiOrphelin($document);
            }

            JournalAudit::log('courrier.supprimer', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrierVerrouille->id,
                    'numero' => $numero,
                    'sens' => $sens,
                    'objet' => $courrierVerrouille->objet,
                    'acteur_id' => $acteur->id,
                ]),
            ]);

            $courrierVerrouille->delete();
        });
    }

    /**
     * Détache des pièces du courrier et supprime le fichier s’il n’est plus rattaché ailleurs.
     *
     * @param  list<int>  $documentIds
     */
    public function retirerDocuments(Courrier $courrier, array $documentIds): int
    {
        $ids = collect($documentIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        $documents = $courrier->documents()->whereIn('documents.id', $ids)->get();
        $retirés = 0;

        foreach ($documents as $document) {
            $courrier->documents()->detach($document->id);
            $this->supprimerSiOrphelin($document);
            $retirés++;
        }

        return $retirés;
    }

    public function supprimerSiOrphelin(Document $document): void
    {
        if ($document->courriers()->exists()) {
            return;
        }

        if ($document->chemin) {
            Storage::disk('public')->delete($document->chemin);
        }

        $document->delete();
    }
}
