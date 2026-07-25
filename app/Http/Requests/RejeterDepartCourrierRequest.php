<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejeterDepartCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rejeter', $this->route('courrier'));
    }

    public function rules(): array
    {
        return [
            'motif_rejet' => ['required', 'string', 'max:2000'],
        ];
    }
}
