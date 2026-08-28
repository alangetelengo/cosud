<?php

namespace App\Http\Requests;

use App\Models\Moratoire;
use App\Services\FournisseurDetteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'montant_dette_initial' => ['nullable', 'numeric', 'min:1'],
            'montant_echeance_defaut' => ['required', 'numeric', 'min:1'],
            'lieu' => ['nullable', 'string', 'max:120'],
            'date_document' => ['nullable', 'date'],
            'signataire_libelle' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'fichiers' => ['required', 'array', 'min:1', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $libelle = trim((string) $this->input('fournisseur_libelle', ''));
            if ($libelle === '') {
                return;
            }

            $eligibles = app(FournisseurDetteService::class)->fournisseursEligiblesMoratoire();
            $trouve = $eligibles->contains(
                fn (array $row) => $row['fournisseur_libelle'] === $libelle
                    || $row['fournisseur_normalise'] === app(FournisseurDetteService::class)->normaliserLibelle($libelle)
            );

            if (! $trouve) {
                $validator->errors()->add(
                    'fournisseur_libelle',
                    'Choisissez un fournisseur parmi les dettes enregistrées (saisies par le responsable Factures / prestataires).'
                );
            }
        });
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
            'fournisseur_libelle.required' => 'Choisissez un fournisseur parmi les dettes enregistrées.',
            'montant_echeance_defaut.required' => 'Le montant d’échéance est obligatoire.',
            'montant_echeance_defaut.min' => 'L’échéance doit être supérieure à zéro.',
            'fichiers.required' => 'Au moins une pièce d’instruction du DG est obligatoire.',
            'fichiers.min' => 'Au moins une pièce d’instruction du DG est obligatoire.',
            'fichiers.*.mimes' => 'Chaque pièce doit être un PDF ou une image (JPG, PNG).',
            'fichiers.*.max' => 'Chaque pièce ne doit pas dépasser 10 Mo.',
        ];
    }
}
