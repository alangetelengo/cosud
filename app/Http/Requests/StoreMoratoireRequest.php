<?php

namespace App\Http\Requests;

use App\Models\Moratoire;
use Illuminate\Foundation\Http\FormRequest;

class StoreMoratoireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Moratoire::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fournisseur_libelle' => ['required', 'string', 'max:255'],
            'montant_dette_initial' => ['required', 'numeric', 'min:1'],
            'montant_echeance_defaut' => ['required', 'numeric', 'min:1'],
            'lieu' => ['nullable', 'string', 'max:120'],
            'date_document' => ['nullable', 'date'],
            'signataire_libelle' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['montant_dette_initial', 'montant_echeance_defaut'] as $champ) {
            if ($this->has($champ)) {
                $this->merge([
                    $champ => preg_replace('/\s+/', '', (string) $this->input($champ)),
                ]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fournisseur_libelle.required' => 'Le fournisseur / établissement est obligatoire.',
            'montant_dette_initial.required' => 'Le montant de la dette est obligatoire.',
            'montant_dette_initial.min' => 'La dette doit être supérieure à zéro.',
            'montant_echeance_defaut.required' => 'Le montant d’échéance est obligatoire.',
            'montant_echeance_defaut.min' => 'L’échéance doit être supérieure à zéro.',
        ];
    }
}
