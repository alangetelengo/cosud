<?php

namespace App\Http\Requests;

use App\Models\CircuitCourrierEtape;
use App\Models\Courrier;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InstruireCircuitCourrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Courrier $courrier */
        $courrier = $this->route('courrier');
        $etape = $courrier->circuitEtapeActuelle;

        if (! $this->user()->can('view', $courrier) || ! $etape || $etape->action !== CircuitCourrierEtape::ACTION_INSTRUIRE) {
            return false;
        }

        if ($this->user()->aAccesTotal() || $this->user()->hasRole('admin')) {
            return true;
        }

        return app(CircuitCourrierMoteurService::class)->userCorrespondActeur($this->user(), $etape, $courrier);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'instructions' => ['required', 'string', 'max:2000'],
            'agent_confie_id' => ['nullable', 'integer', 'exists:users,id'],
            'agent_confie_ids' => ['nullable', 'array'],
            'agent_confie_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instructions.required' => 'Les instructions sont obligatoires.',
            'agent_confie_id.exists' => 'Le destinataire choisi est introuvable.',
            'agent_confie_ids.*.exists' => 'Un des destinataires choisis est introuvable.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = collect($this->input('agent_confie_ids', []))
                ->when($this->filled('agent_confie_id'), fn ($c) => $c->push($this->input('agent_confie_id')))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return;
            }

            $users = User::query()->whereIn('id', $ids->all())->get();
            $invalides = $users->filter(
                fn (User $u) => ! $u->hasRole('directeur')
            );

            if ($users->count() !== $ids->count() || $invalides->isNotEmpty()) {
                $validator->errors()->add(
                    'agent_confie_ids',
                    'Seuls les directeurs peuvent être choisis comme destinataires.'
                );
            }
        });
    }
}
