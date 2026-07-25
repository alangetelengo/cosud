<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VentilerCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ventiler', $this->route('courrier'));
    }

    public function rules(): array
    {
        return [
            'ventilations' => ['required', 'array', 'min:1'],
            'ventilations.*.user_id' => ['required', 'exists:users,id'],
            'ventilations.*.document_id' => ['required', 'exists:documents,id'],
            'ventilations.*.structure_id' => ['nullable', 'exists:structures,id'],
        ];
    }
}
