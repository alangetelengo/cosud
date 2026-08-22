<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mme Eleni contrôle les éléments saisis par l’AC et confirme la clôture.
 */
class ConfirmerControleDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');
        $courrier->loadMissing('circuitEtapeActuelle');
        $etape = $courrier->circuitEtapeActuelle;

        if (! $etape || $etape->code !== 'cloture_depenses') {
            return false;
        }

        $user = $this->user();

        if ($user->aAccesTotal() || $user->hasRole('admin')) {
            return true;
        }

        return app(CircuitCourrierMoteurService::class)
            ->userCorrespondActeurParRole($user, $etape, $courrier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000'],
            'pieces_complementaires' => ['nullable', 'array', 'max:20'],
            'pieces_complementaires.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pieces_complementaires.*.mimes' => 'Chaque pièce doit être un PDF ou une image (jpg, png).',
        ];
    }
}
