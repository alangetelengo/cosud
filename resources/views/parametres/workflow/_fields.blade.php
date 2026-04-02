@php $e = $etape ?? null; @endphp
<div class="space-y-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom *</label>
            <input type="text" name="nom" value="{{ old('nom', $e?->nom) }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
            @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code *</label>
            <input type="text" name="code" value="{{ old('code', $e?->code) }}" required maxlength="50" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white font-mono @error('code') border-red-500 @enderror">
            @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Ordre</label>
            <input type="number" name="ordre" value="{{ old('ordre', $e?->ordre ?? 1) }}" min="0" max="255" class="w-full max-w-[8rem] px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de document</label>
            <select name="type_document_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                <option value="">— Global (tous types) —</option>
                @foreach($types as $t)
                <option value="{{ $t->id }}" {{ (string) old('type_document_id', $e?->type_document_id) === (string) $t->id ? 'selected' : '' }}>{{ $t->libelle }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Service (optionnel, prioritaire sur le type)</label>
        <select name="structure_scope_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('structure_scope_id') border-red-500 @enderror">
            <option value="">— Aucun service spécifique —</option>
            @foreach(($services ?? collect()) as $s)
            <option value="{{ $s->id }}" {{ (string) old('structure_scope_id', $e?->structure_scope_id) === (string) $s->id ? 'selected' : '' }}>{{ $s->nom }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Renseignez soit un type, soit un service, soit aucun (global).</p>
        @error('structure_scope_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
    </div>
    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600">
        <label class="flex items-center gap-2.5 cursor-pointer mb-2">
            <input type="hidden" name="validation_hierarchique" value="0">
            <input type="checkbox" name="validation_hierarchique" value="1" {{ old('validation_hierarchique', $e?->validation_hierarchique ?? false) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Validation hiérarchique</span>
        </label>
        <p class="text-xs text-slate-500 dark:text-slate-400 ml-6">La chaîne de validation est construite automatiquement à partir de l’organigramme (responsable structure du créateur → parent → … → DG).</p>
    </div>
    <div class="p-4 rounded-xl bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800">
        <label class="flex items-center gap-2.5 cursor-pointer mb-1">
            <input type="hidden" name="destinataire_libre" value="0">
            <input type="checkbox" name="destinataire_libre" value="1" {{ old('destinataire_libre', $e?->destinataire_libre ?? false) ? 'checked' : '' }} class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-4 h-4">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Destinataire libre</span>
        </label>
        <p class="text-xs text-slate-600 dark:text-slate-400 ml-6">Le déposant choisit librement le validateur pour cette étape. Ce mode neutralise « rôle requis » et « validateur spécifique ».</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Rôle requis</label>
            <input type="text" name="role_requis" value="{{ old('role_requis', $e?->role_requis) }}" placeholder="admin, dg, etc." maxlength="50" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Si non hiérarchique : nom du rôle Spatie.</p>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Fonction requise</label>
            <select name="fonction_requise_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                <option value="">— Aucune —</option>
                @foreach(($fonctions ?? collect()) as $f)
                <option value="{{ $f->id }}" {{ (string) old('fonction_requise_id', $e?->fonction_requise_id) === (string) $f->id ? 'selected' : '' }}>{{ $f->libelle }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Validation par les agents ayant cette fonction active.</p>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Validateur spécifique</label>
            <select name="validateur_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                <option value="">— Aucun —</option>
                @foreach($utilisateurs as $u)
                <option value="{{ $u->id }}" {{ (string) old('validateur_id', $e?->validateur_id) === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Étape suivante</label>
        <select name="workflow_etape_suivante_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            <option value="">— Aucune (dernière étape) —</option>
            @foreach($etapes as $opt)
            <option value="{{ $opt->id }}" {{ (string) old('workflow_etape_suivante_id', $e?->workflow_etape_suivante_id) === (string) $opt->id ? 'selected' : '' }}>{{ $opt->nom }} (ordre {{ $opt->ordre }})</option>
            @endforeach
        </select>
    </div>
    <div class="flex flex-wrap gap-6">
        <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="hidden" name="est_derniere_etape" value="0">
            <input type="checkbox" name="est_derniere_etape" value="1" {{ old('est_derniere_etape', $e?->est_derniere_etape ?? false) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
            <span class="text-sm text-slate-700 dark:text-slate-300">Dernière étape (validation finale)</span>
        </label>
        <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="hidden" name="actif" value="0">
            <input type="checkbox" name="actif" value="1" {{ old('actif', $e?->actif ?? true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
            <span class="text-sm text-slate-700 dark:text-slate-300">Actif</span>
        </label>
    </div>
</div>
