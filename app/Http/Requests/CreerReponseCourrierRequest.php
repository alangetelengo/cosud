<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Services\ParapheurDepartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création du courrier départ réponse.
 *
 * - Sans circuit : chemin historique (parapheur départ).
 * - Avec circuit, override DG (`signer_immediatement`) : création signée immédiatement
 *   à l’étape d’instructions (chemin B).
 * - Le chemin A (préparation + signature) passe par `soumettre-reponse` / `valider-reponse`.
 */
class CreerReponseCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');

        if (! $this->user()->can('update', $courrier) || ! $courrier->estArrivee() || $courrier->statutCourrier?->code === 'cloture') {
            return false;
        }

        if (! $courrier->circuit_courrier_id) {
            return true;
        }

        $etape = $courrier->circuitEtapeActuelle;

        // Chemin B uniquement : DG / admin à l’étape d’instructions.
        return $this->boolean('signer_immediatement')
            && ($this->user()->aAccesTotal() || $this->user()->hasRole('admin'))
            && in_array($etape?->code, ['instruction_dg', 'instructions_dg'], true);
    }

    public function rules(): array
    {
        $courrier = $this->route('courrier');

        if (! $courrier->circuit_courrier_id) {
            return [
                'structure_destinataire_id' => [
                    $courrier->estOrigineInterne() ? 'nullable' : 'required',
                    'exists:structures,id',
                ],
                'objet' => ['nullable', 'string', 'max:500'],
                'document_ids' => ['nullable', 'array'],
                'document_ids.*' => ['integer', 'exists:documents,id'],
            ];
        }

        $signerImmediatement = $this->boolean('signer_immediatement');
        $confidentielle = $this->boolean('reponse_confidentielle', (bool) $courrier->reponse_confidentielle);

        return [
            'signer_immediatement' => ['nullable', 'boolean'],
            'document_reponse' => [$signerImmediatement ? 'nullable' : 'nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'objet' => ['nullable', 'string', 'max:500'],
            'reponse_confidentielle' => ['nullable', 'boolean'],
            'structure_destinataire_id' => [
                $confidentielle || $courrier->estOrigineInterne() ? 'nullable' : 'required',
                'exists:structures,id',
            ],
            'destinataire_agent_id' => [
                $confidentielle ? 'required' : 'nullable',
                'exists:users,id',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $courrier = $this->route('courrier');

        $validator->after(function ($validator) use ($courrier) {
            if (! $courrier->circuit_courrier_id) {
                if ($courrier->estOrigineInterne() && ! $courrier->structure_expediteur_id) {
                    $validator->errors()->add(
                        'structure_destinataire_id',
                        'Impossible de répondre : la direction émettrice n\'est pas renseignée sur ce courrier.'
                    );
                }

                $service = app(ParapheurDepartService::class);

                foreach ($this->input('document_ids', []) as $documentId) {
                    $document = Document::find($documentId);
                    if (! $document || ! $service->estEligible($document, $this->user())) {
                        $validator->errors()->add('document_ids', 'Un document sélectionné n\'appartient pas au parapheur départ.');
                    }
                }

                return;
            }

            $signerImmediatement = $this->boolean('signer_immediatement');

            if ($signerImmediatement && ! ($this->user()->aAccesTotal() || $this->user()->hasRole('admin'))) {
                $validator->errors()->add('signer_immediatement', 'Seuls le DG / l\'administrateur peuvent signer immédiatement.');
            }

            if ($signerImmediatement
                && ! in_array($courrier->circuitEtapeActuelle?->code, ['instruction_dg', 'instructions_dg'], true)) {
                $validator->errors()->add(
                    'signer_immediatement',
                    'La réponse directe n\'est possible qu\'à l\'étape des instructions DG.'
                );
            }

            if ($signerImmediatement) {
                if (! $this->hasFile('document_reponse') && ! $courrier->document_reponse_id) {
                    $validator->errors()->add('document_reponse', 'Merci de joindre le document de réponse.');
                }
            } elseif (! $courrier->document_reponse_id && ! $this->hasFile('document_reponse')) {
                $validator->errors()->add('document_reponse', 'Aucun projet de réponse validé n\'est disponible.');
            }

            if ($courrier->estOrigineInterne()
                && ! $this->boolean('reponse_confidentielle', (bool) $courrier->reponse_confidentielle)
                && ! $courrier->structure_expediteur_id) {
                $validator->errors()->add(
                    'structure_destinataire_id',
                    'Impossible de répondre : la direction émettrice n\'est pas renseignée sur ce courrier.'
                );
            }
        });
    }
}
