<?php

namespace App\Http\Requests;

use App\Models\MoratoireEcheance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMoratoireEcheanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $moratoire = $this->route('moratoire');

        return $this->user()->can('update', $moratoire);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '_echeance_id' => ['nullable', 'integer'],
            'mode_paiement' => ['nullable', Rule::in(MoratoireEcheance::MODES_PAIEMENT)],
            'numero_cheque' => ['nullable', 'string', 'max:150'],
            'banque' => ['nullable', 'string', 'max:100'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'date_paiement' => ['nullable', 'date'],
            'periode_mois' => ['nullable', Rule::in(array_keys(MoratoireEcheance::moisDisponibles()))],
            'periode_annee' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'fichiers' => ['nullable', 'array', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var MoratoireEcheance|null $echeance */
            $echeance = $this->route('echeance');
            if (! $echeance) {
                return;
            }

            $mode = $this->input('mode_paiement', MoratoireEcheance::MODE_CHEQUE);
            $numeroCheque = trim((string) $this->input('numero_cheque', ''));
            $datePaiement = $this->input('date_paiement');
            $periodeMois = $this->input('periode_mois');
            $periodeAnnee = $this->input('periode_annee');

            $saisiePaiement = $numeroCheque !== '' || filled($datePaiement) || filled($periodeMois);

            if (! $saisiePaiement && ! $echeance->estPayee()) {
                return;
            }

            if (blank($datePaiement)) {
                $validator->errors()->add('date_paiement', 'La date de paiement est obligatoire.');
            }

            if (blank($periodeMois)) {
                $validator->errors()->add('periode_mois', 'Indiquez le mois réglé par ce paiement.');
            }

            if (blank($periodeAnnee)) {
                $validator->errors()->add('periode_annee', 'Indiquez l’année de la période réglée.');
            }

            if ($mode === MoratoireEcheance::MODE_CHEQUE && $numeroCheque === '') {
                $validator->errors()->add('numero_cheque', 'Le N° chèque est obligatoire pour un paiement par chèque.');
            }

            $fichiers = array_values(array_filter((array) $this->file('fichiers', [])));
            if (! $echeance->estPayee() && $fichiers === []) {
                $validator->errors()->add('fichiers', 'Au moins un justificatif (PDF ou image) est obligatoire pour enregistrer un paiement.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichiers.*.mimes' => 'Chaque justificatif doit être un PDF ou une image (JPG, PNG).',
            'fichiers.*.max' => 'Chaque justificatif ne doit pas dépasser 10 Mo.',
        ];
    }
}
