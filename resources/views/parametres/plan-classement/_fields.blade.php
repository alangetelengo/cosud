@php
    $d = $dossier ?? null;
@endphp
<div class="space-y-5">
    <div>
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom du dossier *</label>
        <input type="text" name="nom" value="{{ old('nom', $d?->nom) }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
        @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Dossier parent</label>
        <select name="parent_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            <option value="">— Racine (niveau plan de classement) —</option>
            @foreach($parents as $opt)
            <option value="{{ $opt->value }}" {{ (string) old('parent_id', $d?->parent_id ?? ($parent->id ?? '')) === (string) $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
            @endforeach
        </select>
        @error('parent_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code technique</label>
        <input type="text" name="code" value="{{ old('code', $d?->code) }}" maxlength="50" placeholder="Auto si vide (ex. COMPTABILITE)"
               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white font-mono text-sm @error('code') border-red-500 @enderror">
        @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Unique. Laisser vide pour génération automatique à partir du nom.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de dossier</label>
            <select name="type_dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                <option value="">— Non défini —</option>
                @include('dossiers.partials.type-dossier-select-options', [
                    'typesDossier' => $typesDossier,
                    'selectedId' => old('type_dossier_id', $d?->type_dossier_id),
                    'afficherCodeDansTitle' => true,
                ])
            </select>
            @error('type_dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Structure ACSI (rattachement)</label>
            <select name="structure_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                <option value="">— Aucune —</option>
                @foreach($structures as $st)
                <option value="{{ $st->id }}" {{ (string) old('structure_id', $d?->structure_id) === (string) $st->id ? 'selected' : '' }}>{{ $st->nom }} ({{ $st->code }})</option>
                @endforeach
            </select>
            @error('structure_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Utilisé pour la visibilité / dépôt par direction.</p>
        </div>
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
        <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description', $d?->description) }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Ordre d’affichage</label>
        <input type="number" name="ordre" value="{{ old('ordre', $d?->ordre ?? 0) }}" min="0" class="w-full max-w-xs px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
    </div>
    <div class="flex flex-wrap gap-6">
        <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="hidden" name="actif" value="0">
            <input type="checkbox" name="actif" value="1" {{ old('actif', $d?->actif ?? true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Actif</span>
        </label>
        <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="hidden" name="confidentiel" value="0">
            <input type="checkbox" name="confidentiel" value="1" {{ old('confidentiel', $d?->confidentiel) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
            <span class="text-sm text-slate-700 dark:text-slate-300">Confidentiel</span>
        </label>
        <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="hidden" name="notify_sms" value="0">
            <input type="checkbox" name="notify_sms" value="1" {{ old('notify_sms', $d?->notify_sms) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
            <span class="text-sm text-slate-700 dark:text-slate-300">Notification SMS (prioritaire)</span>
        </label>
    </div>
</div>
