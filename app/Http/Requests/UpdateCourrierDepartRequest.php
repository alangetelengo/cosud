<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourrierDepartRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');

        return $this->user()->can('corriger', $courrier)
            && $courrier->estDepart()
            && in_array($courrier->statutCourrier?->code, ['brouillon', 'rejete_directeur'], true);
    }

    public function rules(): array
    {
        return [
            'objet' => ['required', 'string', 'max:500'],
            'date_courrier' => ['nullable', 'date'],
            'priorite_courrier_id' => ['nullable', 'exists:priorite_courriers,id'],
            'type_courrier_id' => ['nullable', 'exists:type_courriers,id'],
        ];
    }
}
