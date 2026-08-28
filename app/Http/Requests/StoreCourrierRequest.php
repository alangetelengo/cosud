<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use App\Models\Document;
use App\Models\FournisseurPrestataire;
use App\Models\TypeCourrier;
use App\Services\CourrierDoublonService;
use App\Services\ParapheurDepartService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Courrier::class);
    }

    public function rules(): array
    {
        $sens = $this->input('sens');
        $typesParapheur = config('cosud.parapheur_depart.types_document', []);

        return [
            'sens' => ['required', 'in:arrivee,depart'],
            'type_courrier_id' => ['nullable', 'exists:type_courriers,id'],
            'priorite_courrier_id' => ['nullable', 'exists:priorite_courriers,id'],
            'date_reception' => ['nullable', 'date'],
            'date_courrier' => ['nullable', 'date'],
            'numero_fulgurant' => [
                Rule::requiredIf(fn () => $this->input('sens') === 'arrivee'),
                'nullable',
                'string',
                'max:100',
            ],
            'reference' => ['nullable', 'string', 'max:100'],
            'expediteur_libelle' => ['nullable', 'string', 'max:255'],
            'expediteur_email' => ['nullable', 'email', 'max:255'],
            'expediteur_telephone' => [
                Rule::requiredIf(fn () => $this->typeCourrierNecessiteTelephoneExpediteur()),
                'nullable',
                'string',
                'max:40',
            ],
            'fournisseur_prestataire_id' => [
                Rule::requiredIf(fn () => $this->typeCourrierCodeDans(['facture'])),
                'nullable',
                'integer',
                Rule::exists('fournisseur_prestataires', 'id')->where('actif', true),
            ],
            'destinataire_libelle' => ['nullable', 'string', 'max:255'],
            'est_expediteur_externe' => ['nullable', 'boolean'],
            'structure_expediteur_id' => ['nullable', 'exists:structures,id'],
            'structure_destinataire_id' => [
                'nullable',
                'exists:structures,id',
            ],
            'service_demandeur_structure_id' => [
                Rule::requiredIf(fn () => $this->typeCourrierNecessiteServiceDemandeur()),
                'nullable',
                Rule::exists('structures', 'id')->where(function ($query) {
                    $query->whereIn('type', ['direction', 'antenne'])->where('actif', true);
                }),
            ],
            'objet' => ['required', 'string', 'max:500'],
            'montant_facture' => [
                Rule::requiredIf(fn () => $this->typeCourrierCodeDans(['facture'])),
                'nullable',
                'numeric',
                'min:1',
            ],
            'fichier' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
            'fichiers' => ['nullable', 'array', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
            'nouveau_type_document_id' => [
                Rule::requiredIf($sens === 'depart' && $this->hasFile('nouveaux_fichiers')),
                'nullable',
                'integer',
                Rule::exists('type_documents', 'id')->where(function ($q) use ($typesParapheur) {
                    $q->whereIn('code', $typesParapheur)->where('actif', true);
                }),
            ],
            'nouveaux_fichiers' => ['nullable', 'array'],
            'nouveaux_fichiers.*' => ['file', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('montant_facture')) {
            $this->merge([
                'montant_facture' => preg_replace('/\s+/', '', (string) $this->input('montant_facture')),
            ]);
        }

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
                $this->merge($merge);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('sens') === 'arrivee') {
                $aDesScans = $this->hasFile('fichier')
                    || collect($this->file('fichiers', []))->filter()->isNotEmpty();

                if (! $aDesScans) {
                    $validator->errors()->add(
                        'fichiers',
                        'Au moins un scan (PDF ou image) est obligatoire pour un courrier arrivée.'
                    );
                }

                $service = app(CourrierDoublonService::class);
                $doublon = $service->trouverDoublonArrivee([
                    'numero_fulgurant' => $this->input('numero_fulgurant'),
                    'reference' => $this->input('reference'),
                    'expediteur_libelle' => $this->input('expediteur_libelle'),
                    'date_courrier' => $this->input('date_courrier'),
                    'objet' => $this->input('objet'),
                ]);

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
            }

            if ($this->input('sens') !== 'depart') {
                return;
            }

            $service = app(ParapheurDepartService::class);

            foreach ($this->input('document_ids', []) as $documentId) {
                $document = Document::find($documentId);
                if (! $document || ! $service->estEligible($document, $this->user())) {
                    $validator->errors()->add('document_ids', 'Un document sélectionné n\'appartient pas au parapheur départ ou n\'est pas disponible.');
                }
            }

            if ($this->hasFile('nouveaux_fichiers') && ! $this->filled('nouveau_type_document_id')) {
                $validator->errors()->add('nouveau_type_document_id', 'Le type de document est obligatoire pour un dépôt dans le parapheur.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'fichier.mimes' => 'Chaque scan doit être un PDF ou une image (jpg, png).',
            'fichiers.*.mimes' => 'Chaque scan doit être un PDF ou une image (jpg, png).',
            'nouveau_type_document_id.required' => 'Choisissez le type de pièce à déposer dans le parapheur.',
            'service_demandeur_structure_id.required' => 'Le service demandeur (direction) est obligatoire pour une facture ou une MAD.',
            'service_demandeur_structure_id.exists' => 'Choisissez une direction ou antenne départementale valide.',
            'expediteur_telephone.required' => 'Le téléphone de l’expéditeur est obligatoire pour une facture ou une demande (SMS / notification).',
            'fournisseur_prestataire_id.required' => 'Choisissez le fournisseur ou prestataire dans le référentiel.',
            'fournisseur_prestataire_id.exists' => 'Ce fournisseur ou prestataire n’est pas valide (ou a été désactivé).',
            'numero_fulgurant.required' => 'Le n° de registre (saisi par le secrétariat) est obligatoire.',
            'montant_facture.required' => 'Le montant de la facture est obligatoire.',
            'montant_facture.numeric' => 'Le montant de la facture doit être un nombre.',
            'montant_facture.min' => 'Le montant de la facture doit être supérieur à zéro.',
        ];
    }

    private function typeCourrierNecessiteServiceDemandeur(): bool
    {
        return $this->typeCourrierCodeDans(['facture', 'mad']);
    }

    private function typeCourrierNecessiteTelephoneExpediteur(): bool
    {
        return $this->typeCourrierCodeDans(['facture', 'demande']);
    }

    /**
     * @param  list<string>  $codes
     */
    private function typeCourrierCodeDans(array $codes): bool
    {
        if ($this->input('sens') !== 'arrivee' || ! $this->filled('type_courrier_id')) {
            return false;
        }

        $type = TypeCourrier::query()->find($this->input('type_courrier_id'));

        return $type !== null && in_array($type->code, $codes, true);
    }
}
