<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnulerCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('annuler', $this->route('courrier'));
    }

    public function rules(): array
    {
        $courrier = $this->route('courrier');
        $motifRequis = $courrier?->motifAnnulationRequis() ?? false;

        return [
            'motif_annulation' => [$motifRequis ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif_annulation.required' => 'Indiquez le motif d\'annulation.',
        ];
    }
}
