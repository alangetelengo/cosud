<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCosudNotificationsRequest extends FormRequest
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
            'notif_facture_enregistree_dg' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'notif_facture_enregistree_dg.required' => 'Indiquez si la notification DG doit être activée ou non.',
        ];
    }
}
