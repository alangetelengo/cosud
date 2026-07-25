<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefuserReceptionInterneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recevoir', $this->route('courrier'));
    }

    public function rules(): array
    {
        return [
            'motif_rejet' => ['required', 'string', 'max:2000'],
        ];
    }
}
