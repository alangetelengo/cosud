<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiverCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courrier = $this->route('courrier');

        return $this->user()->can('archiver', $courrier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre_pieces' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'numero_archives' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'dossier_id' => ['nullable', 'exists:dossiers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_pieces.integer' => 'Le nombre de pièces doit être un entier.',
            'numero_archives.max' => 'Le n° archives ne peut pas dépasser 100 caractères.',
            'observations.max' => 'Les observations ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
