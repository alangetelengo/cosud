<?php

namespace App\Http\Requests;

use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le DG (ou le directeur destinataire) valide le projet de réponse soumis par la
 * particulière et le renvoie pour création du courrier départ en brouillon.
 */
class ValiderReponseCourrierRequest extends FormRequest
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
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
