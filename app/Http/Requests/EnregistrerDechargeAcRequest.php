<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * L’AC enregistre la décharge bénéficiaire : date + pièces (+ observation).
 * Les références du chèque (déjà saisies à l’envoi DG) restent figées.
 */
class EnregistrerDechargeAcRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');
        $etape = $courrier->circuitEtapeActuelle;

        if (! $this->user()->can('view', $courrier)
            || ! $etape
            || $etape->code !== 'preuve_paiement') {
            return false;
        }

        if ($this->user()->aAccesTotal() || $this->user()->hasRole('admin')) {
            return true;
        }

        return app(CircuitCourrierMoteurService::class)->peutAgir($courrier, $this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_decharge' => ['required', 'date'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'preuve_paiement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'preuves_paiement' => ['nullable', 'array', 'max:20'],
            'preuves_paiement.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $aDesPieces = $this->hasFile('preuve_paiement')
                || collect($this->file('preuves_paiement', []))->filter()->isNotEmpty();

            if (! $aDesPieces) {
                $validator->errors()->add(
                    'preuves_paiement',
                    'Joignez au moins une pièce (chèque déchargé, pièce d’identité du bénéficiaire…).'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_decharge.required' => 'La date de décharge est obligatoire.',
        ];
    }
}
