<?php

namespace App\Http\Requests;

use App\Models\Courrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClasserCourrierDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');

        return $this->user()->can('classerDossier', $courrier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');

        if ($courrier->typeCourrier?->code === 'facture') {
            return [
                'mode' => ['required', Rule::in(['auto'])],
            ];
        }

        $mode = $this->input('mode', 'existant');

        return [
            'mode' => ['required', Rule::in(['existant', 'nouveau'])],
            'dossier_id' => [
                Rule::requiredIf($mode === 'existant'),
                'nullable',
                'integer',
                'exists:dossiers,id',
            ],
            'nom_dossier' => [
                Rule::requiredIf($mode === 'nouveau'),
                'nullable',
                'string',
                'max:255',
            ],
            'parent_id' => ['nullable', 'integer', 'exists:dossiers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.required' => 'Choisissez un mode de classement.',
            'dossier_id.required' => 'Sélectionnez le dossier de classement.',
            'dossier_id.exists' => 'Le dossier sélectionné est invalide.',
            'nom_dossier.required' => 'Indiquez le nom du nouveau dossier.',
            'nom_dossier.max' => 'Le nom du dossier ne peut pas dépasser 255 caractères.',
        ];
    }
}
