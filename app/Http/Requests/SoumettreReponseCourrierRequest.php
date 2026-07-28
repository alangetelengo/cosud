<?php

namespace App\Http\Requests;

use App\Models\CircuitCourrierEtape;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La particulière prépare le courrier de réponse (document) et le transmet
 * au DG pour signature — étape « traitement_particuliere ».
 */
class SoumettreReponseCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');
        $etape = $courrier->circuitEtapeActuelle;

        if (! $this->user()->can('view', $courrier)
            || ! $etape
            || $etape->code !== 'traitement_particuliere'
            || $etape->action !== CircuitCourrierEtape::ACTION_TRAITER) {
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
            'document_reponse' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'objet' => ['nullable', 'string', 'max:500'],
            'structure_destinataire_id' => ['prohibited'],
            'reponse_confidentielle' => ['prohibited'],
            'destinataire_agent_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_reponse.required' => 'Merci de joindre le document de réponse.',
        ];
    }
}
