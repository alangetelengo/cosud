<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\FactureRegularisationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayerFactureRegularisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('factures-regularisation.payer');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');
        $mode = $courrier?->regularisation_mode_paiement;
        $refsRequises = in_array($mode, [
            FactureRegularisationService::MODE_CHEQUE,
            FactureRegularisationService::MODE_OV,
        ], true);

        return [
            'date_paiement' => ['required', 'date'],
            'numero_piece' => [Rule::requiredIf($refsRequises), 'nullable', 'string', 'max:150'],
            'banque' => ['nullable', 'string', 'max:100'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'fichiers' => ['nullable', 'array', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_paiement.required' => 'La date de paiement effectif est obligatoire.',
            'numero_piece.required' => 'La référence (N° chèque ou OV) est obligatoire pour ce mode.',
        ];
    }
}
