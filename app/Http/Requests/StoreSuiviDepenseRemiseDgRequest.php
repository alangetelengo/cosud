<?php

namespace App\Http\Requests;

use App\Models\CategorieDepense;
use App\Models\SuiviPaiement;
use App\Support\MontantFcfa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuiviDepenseRemiseDgRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SuiviPaiement::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $idsSaisie = CategorieDepense::activesPourSaisie()->pluck('id')->all();

        return [
            'categorie_depense_id' => ['required', 'integer', Rule::in($idsSaisie)],
            'date_suivi' => ['required', 'date'],
            'intitule' => ['required', 'string', 'max:500'],
            'montant' => ['required', 'numeric', 'min:1'],
            'beneficiaire_libelle' => ['nullable', 'string', 'max:255'],
            'numero_piece' => ['nullable', 'string', 'max:255'],
            'instruction_dg' => ['nullable', 'string', 'max:2000'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'justificatifs' => ['nullable', 'array', 'max:20'],
            'justificatifs.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('montant')) {
            $this->merge([
                'montant' => MontantFcfa::normaliser($this->input('montant')),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categorie_depense_id.required' => 'Choisissez la catégorie de dépense.',
            'categorie_depense_id.in' => 'Catégorie de dépense invalide.',
            'date_suivi.required' => 'La date de suivi est obligatoire.',
            'intitule.required' => 'L’intitulé de la dépense est obligatoire.',
            'montant.required' => 'Le montant est obligatoire.',
            'montant.min' => 'Le montant doit être strictement positif.',
            'justificatifs.*.mimes' => 'Chaque justificatif doit être un PDF ou une image (JPG, PNG).',
            'justificatifs.*.max' => 'Chaque justificatif ne doit pas dépasser 10 Mo.',
        ];
    }
}
