<?php

namespace App\Http\Requests;

use App\Models\Structure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OrienterCourrierRequest extends FormRequest
{
    public const MODE_DIRECT = 'direct';

    public const MODE_VIA_PARTICULIERE = 'via_particuliere';

    public const DEST_SECRETARIAT = 'secretariat';

    public const DEST_DIRECTEUR = 'directeur';

    public const DEST_PARTICULIERE = 'particuliere';

    public function authorize(): bool
    {
        return $this->user()->can('orienter', $this->route('courrier'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modeDirect = $this->input('orientation_mode') === self::MODE_DIRECT;
        $confidentiel = $this->boolean('est_confidentiel');
        $destType = $this->input('destinataire_type');

        return [
            'orientation_mode' => ['required', Rule::in([self::MODE_DIRECT, self::MODE_VIA_PARTICULIERE])],
            'instructions_dg' => ['required', 'string', 'max:2000'],
            'est_confidentiel' => ['sometimes', 'boolean'],
            'direction_id' => [
                Rule::requiredIf($modeDirect && in_array($destType, [self::DEST_SECRETARIAT, self::DEST_DIRECTEUR], true)),
                'nullable',
                'integer',
                Rule::exists('structures', 'id')->where(fn ($q) => $q->where('type', 'direction')->where('actif', true)),
            ],
            'destinataire_type' => [
                Rule::requiredIf($modeDirect),
                'nullable',
                Rule::in([self::DEST_SECRETARIAT, self::DEST_DIRECTEUR, self::DEST_PARTICULIERE]),
            ],
            'notify_user_ids' => [
                Rule::requiredIf($modeDirect && $confidentiel),
                'nullable',
                'array',
                'min:1',
            ],
            'notify_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orientation_mode.required' => 'Choisissez le mode d’orientation.',
            'instructions_dg.required' => 'Les instructions sont obligatoires.',
            'direction_id.required' => 'Choisissez la direction destinataire.',
            'destinataire_type.required' => 'Choisissez le type de destinataire.',
            'notify_user_ids.required' => 'En mode confidentiel, sélectionnez au moins un agent à notifier.',
            'notify_user_ids.min' => 'En mode confidentiel, sélectionnez au moins un agent à notifier.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('orientation_mode') !== self::MODE_DIRECT) {
                return;
            }

            $directionId = (int) $this->input('direction_id');
            $type = $this->input('destinataire_type');

            if ($type === self::DEST_SECRETARIAT && $directionId > 0) {
                $hasSec = Structure::secretariatsDirections()
                    ->where('parent_id', $directionId)
                    ->exists();
                if (! $hasSec) {
                    $validator->errors()->add('direction_id', 'Cette direction n’a pas de secrétariat de direction.');
                }
            }
        });
    }
}
