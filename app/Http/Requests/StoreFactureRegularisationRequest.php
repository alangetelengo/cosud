<?php

namespace App\Http\Requests;

use App\Services\FactureRegularisationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactureRegularisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('factures-regularisation.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $payee = $this->input('paiement') === FactureRegularisationService::PAIEMENT_PAYEE;

        return [
            'fournisseur_libelle' => ['required', 'string', 'max:255'],
            'montant_facture' => ['required', 'numeric', 'min:1'],
            'objet' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'date_facture' => ['nullable', 'date'],
            'date_reception' => ['nullable', 'date'],
            'service_demandeur_structure_id' => [
                'nullable',
                Rule::exists('structures', 'id')->where(function ($query) {
                    $query->whereIn('type', ['direction', 'antenne'])->where('actif', true);
                }),
            ],
            'paiement' => ['required', Rule::in([
                FactureRegularisationService::PAIEMENT_IMPAYEE,
                FactureRegularisationService::PAIEMENT_PAYEE,
            ])],
            'numero_piece' => [Rule::requiredIf($payee), 'nullable', 'string', 'max:150'],
            'banque' => ['nullable', 'string', 'max:100'],
            'date_paiement' => [Rule::requiredIf($payee), 'nullable', 'date'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'fichiers' => [Rule::requiredIf($payee), 'nullable', 'array', 'min:1', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('montant_facture')) {
            $this->merge([
                'montant_facture' => preg_replace('/\s+/', '', (string) $this->input('montant_facture')),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fournisseur_libelle.required' => 'Le fournisseur / prestataire est obligatoire.',
            'montant_facture.required' => 'Le montant de la facture est obligatoire.',
            'montant_facture.min' => 'Le montant doit être supérieur à zéro.',
            'paiement.required' => 'Indiquez si la facture est déjà payée ou encore due.',
            'numero_piece.required' => 'Le N° de pièce / chèque est obligatoire pour une facture déjà payée.',
            'date_paiement.required' => 'La date de paiement est obligatoire pour une facture déjà payée.',
            'fichiers.required' => 'Au moins un scan (facture / preuve de paiement) est obligatoire pour une facture payée.',
            'fichiers.min' => 'Au moins un scan (facture / preuve de paiement) est obligatoire pour une facture payée.',
        ];
    }
}
