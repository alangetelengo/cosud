<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupprimerCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('courrier'));
    }

    public function rules(): array
    {
        return [];
    }
}
