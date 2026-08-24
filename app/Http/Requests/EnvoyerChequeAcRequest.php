<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * L’Agent comptable envoie le chèque au DG : message, montant et références bordereau obligatoires.
 */
class EnvoyerChequeAcRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');
        $etape = $courrier->circuitEtapeActuelle;

        if (! $this->user()->can('view', $courrier)
            || ! $etape
            || $etape->code !== 'ac_etablit_cheque') {
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
            'message' => ['required', 'string', 'max:2000'],
            'montant' => ['required', 'numeric', 'min:1'],
            'numero_piece' => ['required', 'string', 'max:150'],
            'banque' => ['required', 'string', 'max:100'],
            'beneficiaire_libelle' => ['required', 'string', 'max:255'],
            'programmation' => ['nullable', 'string', 'max:255'],
            'scan_cheque' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'scans_cheque' => ['nullable', 'array', 'max:20'],
            'scans_cheque.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Le message au DG est obligatoire.',
            'montant.required' => 'Le montant du chèque est obligatoire.',
            'montant.numeric' => 'Le montant doit être un nombre.',
            'montant.min' => 'Le montant doit être supérieur à zéro.',
            'numero_piece.required' => 'Le N° de pièce (chèque) est obligatoire.',
            'banque.required' => 'La banque est obligatoire.',
            'beneficiaire_libelle.required' => 'Le bénéficiaire est obligatoire.',
            'scan_cheque.mimes' => 'Chaque scan du chèque doit être un PDF ou une image (jpg, png).',
            'scans_cheque.*.mimes' => 'Chaque scan du chèque doit être un PDF ou une image (jpg, png).',
        ];
    }
}
