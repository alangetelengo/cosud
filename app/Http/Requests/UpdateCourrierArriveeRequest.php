<?php

namespace App\Http\Requests;

use App\Services\CourrierDoublonService;
use Illuminate\Foundation\Http\FormRequest;

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
            'expediteur_telephone' => ['nullable', 'string', 'max:40'],
            'numero_fulgurant' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'nombre_pieces' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'numero_archives' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'priorite_courrier_id' => ['nullable', 'exists:priorite_courriers,id'],
            'type_courrier_id' => ['nullable', 'exists:type_courriers,id'],
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
                $champ = in_array($doublon['critere'], ['numero_fulgurant', 'reference'], true)
                    ? $doublon['critere']
                    : 'objet';
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
        ];
    }
}
