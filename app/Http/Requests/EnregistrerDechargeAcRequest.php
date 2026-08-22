<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * L’AC enregistre le bordereau de transmission + pièces à la décharge du bénéficiaire.
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
            'numero_piece' => ['required', 'string', 'max:150'],
            'montant' => ['required', 'numeric', 'min:1'],
            'banque' => ['required', 'string', 'max:100'],
            'beneficiaire_libelle' => ['required', 'string', 'max:255'],
            'programmation' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'preuve_paiement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'preuves_paiement' => ['nullable', 'array', 'max:20'],
            'preuves_paiement.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('montant')) {
            $this->merge([
                'montant' => preg_replace('/\s+/', '', (string) $this->input('montant')),
            ]);
        }
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
            'numero_piece.required' => 'Le N° de pièce (chèque / MAD) est obligatoire.',
            'montant.required' => 'Le montant est obligatoire.',
            'banque.required' => 'La banque est obligatoire.',
            'beneficiaire_libelle.required' => 'Le bénéficiaire est obligatoire.',
        ];
    }
}
