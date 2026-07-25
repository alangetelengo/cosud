<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le DG enregistre la signature du chèque (scan obligatoire) et peut notifier le fournisseur.
 */
class SignerChequeDgRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');
        $etape = $courrier->circuitEtapeActuelle;

        if (! $this->user()->can('view', $courrier)
            || ! $etape
            || $etape->code !== 'dg_signe_cheque') {
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
            'scan_cheque_signe' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'message' => ['nullable', 'string', 'max:2000'],
            'notifier_fournisseur' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scan_cheque_signe.required' => 'Merci de joindre le scan du chèque signé.',
            'scan_cheque_signe.mimes' => 'Le scan doit être un PDF ou une image (jpg, png).',
        ];
    }
}
