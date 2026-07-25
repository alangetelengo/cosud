<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransmettreCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transmettre', $this->route('courrier'));
    }

    public function rules(): array
    {
        return [
            'vers_structure_id' => ['nullable', 'exists:structures,id'],
            'vers_user_id' => ['nullable', 'exists:users,id'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
            'accuse_reception' => ['nullable', 'boolean'],
            'accuse_fichier' => ['nullable', 'file', 'max:5120'],
        ];
    }
}
