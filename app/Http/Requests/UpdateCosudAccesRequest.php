<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCosudAccesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('admin');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lecture_dossier_lors_partage_document' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'lecture_dossier_lors_partage_document.required' => 'Indiquez si l’option doit être activée ou non.',
        ];
    }
}
