<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le DG confirme que le chèque est signé (sans scan) et renvoie le dossier à l’AC.
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
            'message' => ['nullable', 'string', 'max:2000'],
            'notifier_fournisseur' => ['nullable', 'boolean'],
        ];
    }
}
