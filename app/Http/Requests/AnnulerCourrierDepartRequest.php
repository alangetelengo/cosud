<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnulerCourrierDepartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('annuler', $this->route('courrier'));
    }

    public function rules(): array
    {
        $courrier = $this->route('courrier');
        $estDirecteur = $courrier?->statutCourrier?->code === 'transmis_directeur';

        return [
            'motif_annulation' => [$estDirecteur ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif_annulation.required' => 'Indiquez le motif d\'annulation.',
        ];
    }
}
