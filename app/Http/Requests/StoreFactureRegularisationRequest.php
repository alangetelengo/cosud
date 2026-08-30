<?php

namespace App\Http\Requests;

use App\Services\FactureRegularisationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $paiement = $this->input('paiement');
        $programmee = $paiement === FactureRegularisationService::PAIEMENT_PROGRAMMEE;
        $contratMensuel = $paiement === FactureRegularisationService::PAIEMENT_CONTRAT_MENSUEL;
        $mode = $this->input('mode_paiement');
        $refsRequises = $programmee && in_array($mode, [
            FactureRegularisationService::MODE_CHEQUE,
            FactureRegularisationService::MODE_OV,
        ], true);

        return [
            'fournisseur_prestataire_id' => ['nullable', 'integer', 'exists:fournisseur_prestataires,id'],
            'fournisseur_libelle' => ['nullable', 'string', 'max:255'],
            'montant_facture' => [Rule::requiredIf(! $contratMensuel), 'nullable', 'numeric', 'min:1'],
            'montant_mensuel_contrat' => [Rule::requiredIf($contratMensuel), 'nullable', 'numeric', 'min:1'],
            'nb_mois_impayes' => [Rule::requiredIf($contratMensuel), 'nullable', 'integer', 'min:1', 'max:600'],
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
                FactureRegularisationService::PAIEMENT_PROGRAMMEE,
                FactureRegularisationService::PAIEMENT_CONTRAT_MENSUEL,
            ])],
            'mode_paiement' => [
                Rule::requiredIf($programmee),
                'nullable',
                Rule::in(FactureRegularisationService::MODES_PAIEMENT),
            ],
            'date_programmation' => [Rule::requiredIf($programmee), 'nullable', 'date'],
            'numero_piece' => [Rule::requiredIf($refsRequises), 'nullable', 'string', 'max:150'],
            'banque' => ['nullable', 'string', 'max:100'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'fichiers' => ['required', 'array', 'min:1', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $id = (int) $this->input('fournisseur_prestataire_id', 0);
            $libelle = trim((string) $this->input('fournisseur_libelle', ''));
            if ($id <= 0 && $libelle === '') {
                $validator->errors()->add(
                    'fournisseur_prestataire_id',
                    'Sélectionnez un fournisseur / prestataire du référentiel, ou saisissez un nouveau nom.'
                );
            }

            if ($this->input('paiement') !== FactureRegularisationService::PAIEMENT_CONTRAT_MENSUEL) {
                return;
            }

            $mensuel = (float) preg_replace('/\s+/', '', (string) $this->input('montant_mensuel_contrat', '0'));
            $mois = (int) $this->input('nb_mois_impayes', 0);
            if ($mensuel > 0 && $mois > 0) {
                $this->merge([
                    'montant_facture' => (string) ($mensuel * $mois),
                ]);
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('montant_facture')) {
            $this->merge([
                'montant_facture' => preg_replace('/\s+/', '', (string) $this->input('montant_facture')),
            ]);
        }

        if ($this->has('montant_mensuel_contrat')) {
            $this->merge([
                'montant_mensuel_contrat' => preg_replace('/\s+/', '', (string) $this->input('montant_mensuel_contrat')),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fournisseur_libelle.required' => 'Le prestataire est obligatoire.',
            'montant_facture.required' => 'Le montant de la facture est obligatoire.',
            'montant_facture.min' => 'Le montant doit être supérieur à zéro.',
            'montant_mensuel_contrat.required' => 'Le montant mensuel du contrat est obligatoire.',
            'montant_mensuel_contrat.min' => 'Le montant mensuel doit être supérieur à zéro.',
            'nb_mois_impayes.required' => 'Indiquez le nombre de mois impayés.',
            'nb_mois_impayes.min' => 'Le nombre de mois impayés doit être au moins 1.',
            'paiement.required' => 'Indiquez le type de régularisation.',
            'mode_paiement.required' => 'Choisissez le mode de paiement (chèque, espèces ou OV).',
            'date_programmation.required' => 'La date de programmation est obligatoire.',
            'numero_piece.required' => 'La référence (N° chèque ou OV) est obligatoire pour ce mode.',
            'fichiers.required' => 'Au moins un scan est obligatoire.',
            'fichiers.min' => 'Au moins un scan est obligatoire.',
        ];
    }
}
