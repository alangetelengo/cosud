<?php

namespace App\Http\Requests;

use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le DG (ou le directeur destinataire) rejette le projet de réponse soumis par la
 * particulière, depuis l'étape « validation_reponse_dg » du circuit « courrier_general ».
 */
class RejeterReponseCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');
        $etape = $courrier->circuitEtapeActuelle;

        if (! $this->user()->can('view', $courrier) || ! $etape || $etape->code !== 'validation_reponse_dg') {
            return false;
        }

        if ($this->user()->aAccesTotal() || $this->user()->hasRole('admin')) {
            return true;
        }

        return app(CircuitCourrierMoteurService::class)->userCorrespondActeur($this->user(), $etape, $courrier);
    }

    public function rules(): array
    {
        return [
            'motif_rejet' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motif_rejet.required' => 'Le motif du rejet est obligatoire.',
        ];
    }
}
