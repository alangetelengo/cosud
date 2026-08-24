<?php

namespace App\Http\Requests;

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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $courrier = $this->route('courrier');
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
            'service_demandeur_structure_id.required' => 'Le service demandeur (direction) est obligatoire pour une facture ou une MAD.',
            'service_demandeur_structure_id.exists' => 'Choisissez une direction ou antenne départementale valide.',
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
