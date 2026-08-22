{{--
  Champs d’orientation DG.
  Variables : $directions, $agentsOrientation
  Optionnel : $field, $label, $compact
  Alpine parent attendu : mode, confidentiel, destType
--}}
@php
    $compact = $compact ?? false;
    $field = $field ?? 'w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500';
    $label = $label ?? 'block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5';
    $textSize = $compact ? 'text-xs' : 'text-sm';
    $hintSize = $compact ? 'text-[11px]' : 'text-xs';
    $spaceY = $compact ? 'space-y-2' : 'space-y-4';
@endphp

<div class="{{ $spaceY }}">
    <p class="{{ $hintSize }} text-slate-500 leading-snug">
        Choisissez d’orienter directement ou de charger la particulière de préparer une réponse.
    </p>

    <div class="space-y-1.5">
        <label class="flex items-start gap-2 {{ $textSize }} cursor-pointer">
            <input type="radio" name="orientation_mode" value="direct" class="mt-0.5" x-model="mode" @checked(old('orientation_mode', 'direct') === 'direct')>
            <span><strong>Orienter moi-même</strong> — instructions vers une direction / secrétariat / particulière</span>
        </label>
        <label class="flex items-start gap-2 {{ $textSize }} cursor-pointer">
            <input type="radio" name="orientation_mode" value="via_particuliere" class="mt-0.5" x-model="mode" @checked(old('orientation_mode') === 'via_particuliere')>
            <span><strong>Instruire la particulière</strong> — elle prépare l’élément de réponse à valider</span>
        </label>
        @error('orientation_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="{{ $label }}">Instructions</label>
        <textarea name="instructions_dg" rows="{{ $compact ? 3 : 4 }}" class="{{ $field }}" placeholder="Instructions…">{{ old('instructions_dg') }}</textarea>
        @error('instructions_dg')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-2 {{ $textSize }}">
        <input type="hidden" name="est_confidentiel" value="0">
        <input type="checkbox" name="est_confidentiel" value="1" x-model="confidentiel" @checked(old('est_confidentiel'))>
        Dossier confidentiel
    </label>

    <div x-show="mode === 'direct'" x-cloak class="{{ $spaceY }}">
        <div>
            <label class="{{ $label }}">Destinataire</label>
            <select name="destinataire_type" x-model="destType" class="{{ $field }}">
                <option value="secretariat">Secrétariat de direction (non confidentiel typique)</option>
                <option value="directeur">Directeur de direction (confidentiel typique)</option>
                <option value="particuliere">Particulière du DG</option>
            </select>
            @error('destinataire_type')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
        </div>
        <div x-show="destType === 'secretariat' || destType === 'directeur'" x-cloak>
            <label class="{{ $label }}">Direction</label>
            <select name="direction_id" class="{{ $field }}">
                <option value="">— Choisir —</option>
                @foreach($directions as $d)
                <option value="{{ $d->id }}" @selected(old('direction_id') == $d->id)>{{ $d->nom }}</option>
                @endforeach
            </select>
            @error('direction_id')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
        </div>
        <div x-show="confidentiel" x-cloak>
            <label class="{{ $label }}">Agents à notifier <span class="text-red-500 normal-case tracking-normal">*</span></label>
            <select name="notify_user_ids[]" multiple class="{{ $field }} min-h-[100px]">
                @foreach($agentsOrientation as $u)
                <option value="{{ $u->id }}" title="{{ $u->name }}" @selected(collect(old('notify_user_ids', []))->contains($u->id))>{{ $u->libelleDestinataireCourrier() }}</option>
                @endforeach
            </select>
            <p class="{{ $hintSize }} text-slate-500 mt-1.5">En confidentiel, vous choisissez librement qui notifier.</p>
            @error('notify_user_ids')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
        </div>
        <p x-show="!confidentiel" x-cloak class="{{ $hintSize }} text-slate-500 leading-snug">
            Non confidentiel : notification automatique au secrétariat, au directeur de la direction et à la particulière DG.
        </p>
    </div>
</div>
