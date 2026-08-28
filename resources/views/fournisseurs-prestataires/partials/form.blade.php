@php
    $fiche = $fiche ?? null;
    $isEdit = (bool) $fiche;
@endphp

<div>
    <label class="block text-xs font-semibold mb-1">Nom <span class="text-red-500">*</span></label>
    <input type="text" name="nom" value="{{ old('nom', $fiche?->nom) }}" required
           class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900" placeholder="Ex. : ACS - Approvisionnement Congo Services">
    @error('nom')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-xs font-semibold mb-1">Type <span class="text-red-500">*</span></label>
    <select name="type" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        <option value="fournisseur" @selected(old('type', $fiche?->type ?? 'fournisseur') === 'fournisseur')>Fournisseur</option>
        <option value="prestataire" @selected(old('type', $fiche?->type) === 'prestataire')>Prestataire</option>
        <option value="partenaire" @selected(old('type', $fiche?->type) === 'partenaire')>Partenaire</option>
    </select>
    @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>

<div class="grid sm:grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-semibold mb-1">E-mail</label>
        <input type="email" name="email" value="{{ old('email', $fiche?->email) }}"
               class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1">Téléphone</label>
        <input type="text" name="telephone" value="{{ old('telephone', $fiche?->telephone) }}"
               class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        @error('telephone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label class="block text-xs font-semibold mb-1">Type de contrats / nature de la prestation</label>
    <input type="text" name="type_contrat" value="{{ old('type_contrat', $fiche?->type_contrat) }}"
           class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900"
           placeholder="Ex. : Location véhicule, Entretien des groupes électrogènes…">
    @error('type_contrat')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>

<div class="flex flex-wrap gap-6">
    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
        <input type="checkbox" name="a_contrat" value="1" @checked(old('a_contrat', $fiche?->a_contrat)) class="rounded border-slate-300 text-emerald-600">
        Contrat formalisé (Oui)
    </label>
    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
        <input type="checkbox" name="a_dossier_fiscal" value="1" @checked(old('a_dossier_fiscal', $fiche?->a_dossier_fiscal)) class="rounded border-slate-300 text-emerald-600">
        Dossier fiscal à jour (Oui)
    </label>
</div>

<div>
    <label class="block text-xs font-semibold mb-1">Observation</label>
    <textarea name="observation" rows="3" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900"
              placeholder="Commentaire pour le rapport DG…">{{ old('observation', $fiche?->observation) }}</textarea>
    @error('observation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>

@if(($dossiers ?? collect())->isNotEmpty())
<div>
    <label class="block text-xs font-semibold mb-1">Dossier fournisseur / prestataire <span class="font-normal text-slate-400">(facultatif)</span></label>
    <select name="dossier_id" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        <option value="">— Aucun —</option>
        @foreach($dossiers as $d)
            <option value="{{ $d->id }}" @selected((int) old('dossier_id', $fiche?->dossier_id) === (int) $d->id)>{{ $d->nom }}</option>
        @endforeach
    </select>
    <p class="text-[11px] text-slate-500 mt-1">Uniquement les dossiers sous votre espace « Mes dossiers ».</p>
    @error('dossier_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
@endif

@if($isEdit)
<label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
    <input type="hidden" name="actif" value="0">
    <input type="checkbox" name="actif" value="1" @checked(old('actif', $fiche?->actif)) class="rounded border-slate-300 text-emerald-600">
    Fiche active
</label>
@endif
