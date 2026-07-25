<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dépôt de la preuve de paiement avant clôture du dossier facture.
 */
class DeposerPreuvePaiementRequest extends FormRequest
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
            'preuve_paiement' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preuve_paiement.required' => 'Merci de joindre la preuve de paiement.',
            'preuve_paiement.mimes' => 'La preuve doit être un PDF ou une image (jpg, png).',
        ];
    }
}
