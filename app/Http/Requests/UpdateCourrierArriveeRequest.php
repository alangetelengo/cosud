<?php

namespace App\Http\Requests;

use App\Models\FournisseurPrestataire;
use App\Models\TypeCourrier;
use App\Services\CourrierDoublonService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourrierArriveeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');

        return $this->user()->can('corriger', $courrier)
            && $courrier->estArrivee();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'objet' => ['required', 'string', 'max:500'],
            'montant_facture' => [
                Rule::requiredIf(fn () => $this->typeCourrierCodeDans(['facture'])),
                'nullable',
                'numeric',
                'min:1',
            ],
            'date_reception' => ['nullable', 'date'],
            'date_courrier' => ['nullable', 'date'],
            'expediteur_libelle' => ['nullable', 'string', 'max:255'],
            'expediteur_email' => ['nullable', 'email', 'max:255'],
            'expediteur_telephone' => [
                Rule::requiredIf(fn () => $this->typeCourrierNecessiteTelephoneExpediteur()),
                'nullable',
                'string',
                'max:40',
            ],
            'expediteur_telephone_2' => ['nullable', 'string', 'max:40'],
            'expediteur_notifier_telephone' => ['nullable', 'boolean'],
            'expediteur_notifier_telephone_2' => ['nullable', 'boolean'],
            'fournisseur_prestataire_id' => [
                Rule::requiredIf(fn () => $this->typeCourrierCodeDans(['facture'])),
                'nullable',
                'integer',
                Rule::exists('fournisseur_prestataires', 'id')->where('actif', true),
            ],
            'numero_fulgurant' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'nombre_pieces' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'numero_archives' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'priorite_courrier_id' => ['nullable', 'exists:priorite_courriers,id'],
            'type_courrier_id' => ['nullable', 'exists:type_courriers,id'],
            'service_demandeur_structure_id' => [
                Rule::requiredIf(fn () => $this->typeCourrierNecessiteServiceDemandeur()),
                'nullable',
                Rule::exists('structures', 'id')->where(function ($query) {
                    $query->whereIn('type', ['direction', 'antenne'])->where('actif', true);
                }),
            ],
            'fichiers' => ['nullable', 'array', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'fichier' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'documents_a_retirer' => ['nullable', 'array'],
            'documents_a_retirer.*' => [
                'integer',
                Rule::exists('courrier_document', 'document_id')->where(function ($query) {
                    $query->where('courrier_id', $this->route('courrier')?->id);
                }),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('montant_facture')) {
            $this->merge([
                'montant_facture' => preg_replace('/\s+/', '', (string) $this->input('montant_facture')),
            ]);
        }

        $this->merge([
            'expediteur_notifier_telephone' => $this->has('expediteur_notifier_telephone')
                ? $this->boolean('expediteur_notifier_telephone')
                : true,
            'expediteur_notifier_telephone_2' => $this->has('expediteur_notifier_telephone_2')
                ? $this->boolean('expediteur_notifier_telephone_2')
                : true,
        ]);

        if ($this->filled('fournisseur_prestataire_id') && $this->typeCourrierCodeDans(['facture'])) {
            $fiche = FournisseurPrestataire::query()
                ->actifs()
                ->find($this->input('fournisseur_prestataire_id'));

            if ($fiche) {
                $merge = ['expediteur_libelle' => $fiche->nom];
                if (! $this->filled('expediteur_email') && filled($fiche->email)) {
                    $merge['expediteur_email'] = $fiche->email;
                }
                if (! $this->filled('expediteur_telephone') && filled($fiche->telephone)) {
                    $merge['expediteur_telephone'] = $fiche->telephone;
                }
                if (! $this->filled('expediteur_telephone_2') && filled($fiche->telephone_2)) {
                    $merge['expediteur_telephone_2'] = $fiche->telephone_2;
                }
                $this->merge($merge);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $courrier = $this->route('courrier');

            $aDesScansUpload = $this->hasFile('fichier')
                || collect($this->file('fichiers', []))->filter()->isNotEmpty();

            $idsARetirer = collect($this->input('documents_a_retirer', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique();

            $resteApresRetrait = $courrier
                ? $courrier->documents()->whereNotIn('documents.id', $idsARetirer)->exists()
                : false;

            if (! $aDesScansUpload && ! $resteApresRetrait) {
                $validator->errors()->add(
                    'fichiers',
                    'Au moins un scan (PDF ou image) est obligatoire : conservez une pièce existante ou importez un fichier.'
                );
            }

            $service = app(CourrierDoublonService::class);

            $doublon = $service->trouverDoublonArrivee([
                'numero_fulgurant' => $this->input('numero_fulgurant'),
                'reference' => $this->input('reference'),
                'expediteur_libelle' => $this->input('expediteur_libelle'),
                'date_courrier' => $this->input('date_courrier'),
                'objet' => $this->input('objet'),
            ], $courrier?->id);

            if ($doublon) {
                $champ = match ($doublon['critere']) {
                    'numero_fulgurant' => 'numero_fulgurant',
                    'reference' => 'reference',
                    default => 'objet',
                };
                $validator->errors()->add(
                    $champ,
                    $service->messagePour($doublon['courrier'], $doublon['critere'])
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'objet.required' => 'L’objet du courrier est obligatoire.',
            'numero_fulgurant.required' => 'Le n° de registre (saisi par le secrétariat) est obligatoire.',
            'expediteur_telephone.required' => 'Le téléphone de l’expéditeur est obligatoire pour une facture ou une demande (SMS / notification).',
            'fournisseur_prestataire_id.required' => 'Choisissez le fournisseur ou prestataire dans le référentiel.',
            'fournisseur_prestataire_id.exists' => 'Ce fournisseur ou prestataire n’est pas valide (ou a été désactivé).',
            'service_demandeur_structure_id.required' => 'Le service demandeur (direction) est obligatoire pour une facture ou une MAD.',
            'service_demandeur_structure_id.exists' => 'Choisissez une direction ou antenne départementale valide.',
            'montant_facture.required' => 'Le montant de la facture est obligatoire.',
            'montant_facture.numeric' => 'Le montant de la facture doit être un nombre.',
            'montant_facture.min' => 'Le montant de la facture doit être supérieur à zéro.',
            'fichiers.*.mimes' => 'Chaque scan doit être un PDF ou une image (jpg, png).',
            'fichiers.*.max' => 'Chaque scan ne doit pas dépasser 10 Mo.',
        ];
    }

    private function typeCourrierNecessiteTelephoneExpediteur(): bool
    {
        return $this->typeCourrierCodeDans(['facture', 'demande']);
    }

    private function typeCourrierNecessiteServiceDemandeur(): bool
    {
        return $this->typeCourrierCodeDans(['facture', 'mad']);
    }

    /**
     * @param  list<string>  $codes
     */
    private function typeCourrierCodeDans(array $codes): bool
    {
        $typeId = $this->input('type_courrier_id')
            ?? $this->route('courrier')?->type_courrier_id;

        if (! $typeId) {
            return false;
        }

        $type = TypeCourrier::query()->find($typeId);

        return $type !== null && in_array($type->code, $codes, true);
    }
}
