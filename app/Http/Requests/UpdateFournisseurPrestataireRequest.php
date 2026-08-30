<?php

namespace App\Http\Requests;

use App\Models\Dossier;
use App\Models\FournisseurPrestataire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFournisseurPrestataireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fournisseur_prestataire'));
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
            'scan_contrat' => ['nullable', 'array', 'max:20'],
            'scan_contrat.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'scan_fiscal' => ['nullable', 'array', 'max:20'],
            'scan_fiscal.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'observation' => ['nullable', 'string', 'max:5000'],
            'dossier_id' => ['nullable', 'integer', Rule::in($this->dossierIdsAutorises())],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var FournisseurPrestataire $fiche */
            $fiche = $this->route('fournisseur_prestataire');

            if ($this->boolean('a_contrat') && ! $fiche->aScanContrat() && ! $this->hasFile('scan_contrat')) {
                $validator->errors()->add('scan_contrat', 'Joignez le scan du contrat (PDF ou image).');
            }

            if ($this->boolean('a_dossier_fiscal') && ! $fiche->aScanFiscal() && ! $this->hasFile('scan_fiscal')) {
                $validator->errors()->add('scan_fiscal', 'Joignez le scan du dossier fiscal (PDF ou image).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du fournisseur ou prestataire est obligatoire.',
            'type.required' => 'Indiquez le type (fournisseur, prestataire ou partenaire).',
            'type.in' => 'Type invalide.',
            'email.email' => 'L’adresse e-mail n’est pas valide.',
            'dossier_id.in' => 'Choisissez un dossier fournisseur / prestataire sous « Mes dossiers ».',
            'scan_contrat.*.mimes' => 'Chaque scan de contrat doit être un PDF ou une image (jpg, png).',
            'scan_fiscal.*.mimes' => 'Chaque scan fiscal doit être un PDF ou une image (jpg, png).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'a_contrat' => $this->boolean('a_contrat'),
            'a_dossier_fiscal' => $this->boolean('a_dossier_fiscal'),
            'actif' => $this->boolean('actif'),
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

        $ids = Dossier::idsDossiersFournisseursPrestatairesPour((int) $user->id);

        /** @var FournisseurPrestataire|null $fiche */
        $fiche = $this->route('fournisseur_prestataire');
        if ($fiche?->dossier_id && ! in_array((int) $fiche->dossier_id, $ids, true)) {
            $ids[] = (int) $fiche->dossier_id;
        }

        return $ids;
    }
}
