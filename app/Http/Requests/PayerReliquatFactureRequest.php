<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\SuiviFacturesFournisseursService;
use App\Services\SuiviPaiementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * L’AC paie et clôture le reliquat d’une facture (hors circuit DG).
 */
class PayerReliquatFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');

        return app(SuiviPaiementService::class)->peutEnregistrerPaiementReliquat($this->user(), $courrier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $beneficiaireFourni = trim((string) ($this->route('courrier')?->expediteur_libelle ?? '')) !== '';

        return [
            'montant' => ['required', 'numeric', 'min:1'],
            'numero_piece' => ['required', 'string', 'max:150'],
            'banque' => ['required', 'string', 'max:100'],
            'beneficiaire_libelle' => $beneficiaireFourni
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'programmation' => ['nullable', 'string', 'max:255'],
            'date_decharge' => ['required', 'date'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'scan_cheque' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'scans_cheque' => ['nullable', 'array', 'max:20'],
            'scans_cheque.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'preuve_paiement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'preuves_paiement' => ['nullable', 'array', 'max:20'],
            'preuves_paiement.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Courrier $courrier */
            $courrier = $this->route('courrier');
            $montants = app(SuiviFacturesFournisseursService::class)->montantsSurFacture($courrier);
            $montant = (float) preg_replace('/\s+/', '', (string) $this->input('montant', '0'));

            if ($montant > 0 && $montant - $montants['reliquat'] > 0.009) {
                $validator->errors()->add(
                    'montant',
                    'Le montant ne peut pas dépasser le reliquat ('
                    .number_format($montants['reliquat'], 0, ',', ' ')
                    .' FCFA).'
                );
            }

            $aDesPieces = $this->hasFile('scan_cheque')
                || collect($this->file('scans_cheque', []))->filter()->isNotEmpty()
                || $this->hasFile('preuve_paiement')
                || collect($this->file('preuves_paiement', []))->filter()->isNotEmpty();

            if (! $aDesPieces) {
                $validator->errors()->add(
                    'preuves_paiement',
                    'Joignez au moins une pièce (scan du chèque ou justificatif de décharge).'
                );
            }
        });
    }

    public function beneficiaireChequeForce(): string
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');
        $depuisCourrier = trim((string) ($courrier->expediteur_libelle ?? ''));

        if ($depuisCourrier !== '') {
            return $depuisCourrier;
        }

        return trim((string) $this->validated('beneficiaire_libelle'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('montant')) {
            $this->merge([
                'montant' => preg_replace('/\s+/', '', (string) $this->input('montant')),
            ]);
        }

        /** @var Courrier|null $courrier */
        $courrier = $this->route('courrier');
        $depuisCourrier = trim((string) ($courrier?->expediteur_libelle ?? ''));
        if ($depuisCourrier !== '') {
            $this->merge(['beneficiaire_libelle' => $depuisCourrier]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'montant.required' => 'Le montant du paiement est obligatoire.',
            'montant.numeric' => 'Le montant doit être un nombre.',
            'montant.min' => 'Le montant doit être supérieur à zéro.',
            'numero_piece.required' => 'Le N° de pièce (chèque) est obligatoire.',
            'banque.required' => 'La banque est obligatoire.',
            'beneficiaire_libelle.required' => 'Le bénéficiaire est obligatoire.',
            'date_decharge.required' => 'La date de décharge est obligatoire.',
            'scan_cheque.mimes' => 'Chaque scan doit être un PDF ou une image (jpg, png).',
            'scans_cheque.*.mimes' => 'Chaque scan doit être un PDF ou une image (jpg, png).',
            'preuve_paiement.mimes' => 'Chaque pièce doit être un PDF ou une image (jpg, png).',
            'preuves_paiement.*.mimes' => 'Chaque pièce doit être un PDF ou une image (jpg, png).',
        ];
    }
}
