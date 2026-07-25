<?php

namespace App\Http\Requests;

use App\Models\Structure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpedierCourrierDepartRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');

        return $this->user()->can('expedierVersSecretariat', $courrier);
    }

    public function rules(): array
    {
        $courrier = $this->route('courrier');

        // Réponse confidentielle adressée directement à un agent (pas à une structure) :
        // le destinataire est déjà figé à la création, aucune ressaisie n'est nécessaire.
        $destinataireAgentDejaFixe = (bool) $courrier->destinataire_agent_id;

        return [
            'structure_destinataire_id' => [
                $destinataireAgentDejaFixe ? 'nullable' : 'required',
                Rule::exists('structures', 'id')->where(fn ($q) => $q->where('actif', true)),
            ],
            'numero_archives' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'structure_destinataire_id.required' => 'Choisissez le secrétariat destinataire avant l’expédition.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((bool) $this->route('courrier')->destinataire_agent_id) {
                return;
            }

            $structureId = (int) $this->input('structure_destinataire_id');
            if ($structureId <= 0) {
                return;
            }

            if (! Structure::secretariatsDirections()->whereKey($structureId)->exists()) {
                $validator->errors()->add(
                    'structure_destinataire_id',
                    'Le destinataire doit être un secrétariat de direction.'
                );

                return;
            }

            $courrier = $this->route('courrier');
            $emetteurId = (int) ($courrier->structure_id
                ?? $this->user()->structurePourValidationHierarchique()?->id
                ?? 0);

            if ($emetteurId > 0 && $structureId === $emetteurId) {
                $validator->errors()->add(
                    'structure_destinataire_id',
                    'Le secrétariat destinataire ne peut pas être le secrétariat émetteur.'
                );
            }
        });
    }
}
