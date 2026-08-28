<?php

namespace App\Http\Requests;

use App\Models\Dossier;
use App\Models\FournisseurPrestataire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFournisseurPrestataireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FournisseurPrestataire::class);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(FournisseurPrestataire::TYPES)],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'type_contrat' => ['nullable', 'string', 'max:255'],
            'a_contrat' => ['nullable', 'boolean'],
            'a_dossier_fiscal' => ['nullable', 'boolean'],
            'observation' => ['nullable', 'string', 'max:5000'],
            'dossier_id' => ['nullable', 'integer', Rule::in($this->dossierIdsAutorises())],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du fournisseur ou prestataire est obligatoire.',
            'type.required' => 'Indiquez le type (fournisseur, prestataire ou partenaire).',
            'type.in' => 'Type invalide.',
            'email.email' => 'L’adresse e-mail n’est pas valide.',
            'dossier_id.in' => 'Choisissez un dossier fournisseur / prestataire sous « Mes dossiers ».',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'a_contrat' => $this->boolean('a_contrat'),
            'a_dossier_fiscal' => $this->boolean('a_dossier_fiscal'),
            'actif' => $this->has('actif') ? $this->boolean('actif') : true,
        ]);
    }

    /**
     * @return list<int>
     */
    private function dossierIdsAutorises(): array
    {
        $user = $this->user();
        if (! $user) {
            return [];
        }

        return Dossier::idsDossiersFournisseursPrestatairesPour((int) $user->id);
    }
}
