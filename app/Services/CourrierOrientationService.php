<?php

namespace App\Services;

use App\Http\Requests\OrienterCourrierRequest;
use App\Models\Courrier;
use App\Models\CourrierOrientation;
use App\Models\JournalAudit;
use App\Models\Parapheur;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CourrierOrientationService
{
    public function __construct(
        private readonly CourrierWorkflowService $workflowService,
        private readonly CourrierSecretariatService $secretariatService,
        private readonly CourrierNotificationService $courrierNotifications,
    ) {}

    /**
     * Met le courrier en parapheur s’il est encore « reçu », puis applique l’orientation.
     *
     * @param  array{
     *     orientation_mode: string,
     *     instructions_dg: string,
     *     est_confidentiel?: bool,
     *     destinataire_type?: string|null,
     *     direction_id?: int|null,
     *     notify_user_ids?: list<int>
     * }  $data
     */
    public function appliquerDepuisEnregistrement(Courrier $courrier, User $acteur, array $data): Courrier
    {
        return DB::transaction(function () use ($courrier, $acteur, $data) {
            $courrier = $courrier->fresh(['statutCourrier', 'sensCourrier', 'structure']);

            if ($courrier->statutCourrier?->code === 'recu') {
                $parapheur = Parapheur::query()
                    ->where('sens_courrier_id', $courrier->sens_courrier_id)
                    ->where('actif', true)
                    ->first();

                $this->workflowService->transitionner($courrier, 'en_parapheur', [
                    'parapheur_id' => $parapheur?->id,
                ]);
                $courrier = $courrier->fresh(['statutCourrier', 'structure']);
                $this->courrierNotifications->notifierMiseEnParapheur($courrier, $acteur);
            }

            return $this->appliquer($courrier->fresh(['statutCourrier']), $acteur, $data);
        });
    }

    /**
     * @param  array{
     *     orientation_mode: string,
     *     instructions_dg: string,
     *     est_confidentiel?: bool,
     *     destinataire_type?: string|null,
     *     direction_id?: int|null,
     *     notify_user_ids?: list<int>
     * }  $data
     */
    public function appliquer(Courrier $courrier, User $acteur, array $data): Courrier
    {
        $mode = $data['orientation_mode'];
        $confidentiel = (bool) ($data['est_confidentiel'] ?? false);
        $instructions = $data['instructions_dg'];

        $courrier->update([
            'est_confidentiel' => $confidentiel,
            'orientation_mode' => $mode,
        ]);

        if ($mode === OrienterCourrierRequest::MODE_VIA_PARTICULIERE) {
            $this->workflowService->transitionner($courrier->fresh(), 'attente_reponse_particuliere', [
                'instructions_dg' => $instructions,
                'date_orientation' => now(),
            ]);

            $particulieres = $this->secretariatService->particulieresDg();
            $this->courrierNotifications->notifierOrientation(
                $courrier->fresh(),
                $acteur,
                $particulieres,
                CourrierNotificationService::INSTRUCTION_PARTICULIERE,
                $instructions
            );
            $courrier->fresh()->orientationNotifies()->sync($particulieres->pluck('id')->all());

            $this->auditer($courrier, $acteur, [
                'mode' => $mode,
                'confidentiel' => $confidentiel,
            ]);

            return $courrier->fresh(['statutCourrier']);
        }

        $destType = $data['destinataire_type'] ?? null;
        $direction = ! empty($data['direction_id'])
            ? Structure::find((int) $data['direction_id'])
            : null;

        $structureCible = null;
        $destinataireUser = null;

        if ($destType === OrienterCourrierRequest::DEST_PARTICULIERE) {
            $destinataireUser = $this->secretariatService->particulieresDg()->first();
        } elseif ($destType === OrienterCourrierRequest::DEST_DIRECTEUR && $direction) {
            $destinataireUser = $this->secretariatService->directeurPourSecretariat($direction);
            $structureCible = $direction;
        } elseif ($destType === OrienterCourrierRequest::DEST_SECRETARIAT && $direction) {
            $structureCible = $this->secretariatService->secretariatPourDirection($direction) ?? $direction;
        }

        CourrierOrientation::create([
            'courrier_id' => $courrier->id,
            'structure_id' => $structureCible?->id,
            'destinataire_type' => $destType,
            'destinataire_user_id' => $destinataireUser?->id,
            'instructions' => $instructions,
            'oriente_par_id' => $acteur->id,
        ]);

        $this->workflowService->transitionner($courrier->fresh(), 'oriente', [
            'instructions_dg' => $instructions,
            'date_orientation' => now(),
        ]);

        $aNotifier = collect();
        if ($confidentiel) {
            $ids = collect($data['notify_user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
            $aNotifier = User::query()->whereIn('id', $ids)->where('actif', true)->get();
        } else {
            $aNotifier = $aNotifier->merge($this->secretariatService->particulieresDg());
            if ($direction) {
                $directeur = $this->secretariatService->directeurPourSecretariat($direction);
                if ($directeur) {
                    $aNotifier->push($directeur);
                }
                $sec = $this->secretariatService->secretariatPourDirection($direction);
                if ($sec) {
                    $aNotifier = $aNotifier->merge($this->secretariatService->secretairesPourStructure($sec));
                }
            }
            if ($destinataireUser) {
                $aNotifier->push($destinataireUser);
            }
        }

        $aNotifier = $aNotifier->unique('id')->values();
        $this->courrierNotifications->notifierOrientation(
            $courrier->fresh(),
            $acteur,
            $aNotifier,
            CourrierNotificationService::ORIENTATION,
            $instructions
        );
        $courrier->fresh()->orientationNotifies()->sync($aNotifier->pluck('id')->all());

        $this->auditer($courrier, $acteur, [
            'mode' => $mode,
            'destinataire_type' => $destType,
            'direction_id' => $direction?->id,
            'confidentiel' => $confidentiel,
            'notifies' => $aNotifier->pluck('id')->all(),
        ]);

        return $courrier->fresh(['statutCourrier']);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function auditer(Courrier $courrier, User $acteur, array $meta): void
    {
        JournalAudit::log('courrier.orienter', 'courriers', [
            'commentaire' => json_encode(array_merge([
                'courrier_id' => $courrier->id,
                'acteur_id' => $acteur->id,
            ], $meta)),
        ]);
    }
}
