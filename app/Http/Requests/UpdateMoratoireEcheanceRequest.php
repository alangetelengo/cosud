<?php

namespace App\Http\Requests;

use App\Models\MoratoireEcheance;
use Illuminate\Foundation\Http\FormRequest;
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
            'numero_cheque' => ['nullable', 'string', 'max:150'],
            'banque' => ['nullable', 'string', 'max:100'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'date_paiement' => ['nullable', 'date'],
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

            $numeroCheque = trim((string) $this->input('numero_cheque', ''));
            $datePaiement = $this->input('date_paiement');

            if ($numeroCheque === '' && blank($datePaiement)) {
                $validator->errors()->add('numero_cheque', 'Indiquez le N° chèque ou la date de paiement.');
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
