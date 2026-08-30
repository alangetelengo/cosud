@php
    $fiche = $fiche ?? null;
    $isEdit = (bool) $fiche;
    $aContratInitial = (bool) old('a_contrat', $fiche?->a_contrat);
    $aFiscalInitial = (bool) old('a_dossier_fiscal', $fiche?->a_dossier_fiscal);
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

<div class="space-y-3" x-data="{
    aContrat: {{ $aContratInitial ? 'true' : 'false' }},
    aFiscal: {{ $aFiscalInitial ? 'true' : 'false' }},
    viderInput(ref) { if (this.$refs[ref]) { this.$refs[ref].value = ''; } }
}">
    <div class="flex flex-wrap gap-6">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
            <input type="checkbox" name="a_contrat" value="1"
                   x-model="aContrat"
                   @change="if (! aContrat) viderInput('scanContrat')"
                   class="rounded border-slate-300 text-emerald-600">
            Contrat formalisé (Oui)
        </label>
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
            <input type="checkbox" name="a_dossier_fiscal" value="1"
                   x-model="aFiscal"
                   @change="if (! aFiscal) viderInput('scanFiscal')"
                   class="rounded border-slate-300 text-emerald-600">
            Dossier fiscal à jour (Oui)
        </label>
    </div>

    <div x-show="aContrat" x-cloak class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 p-3 space-y-2">
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">
            Scan du contrat (PDF / images)
            @if(! $isEdit || ! $fiche?->aScanContrat())
                <span class="text-red-500">*</span>
            @endif
        </label>
        @if($isEdit && $fiche?->aScanContrat())
            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1">
                @foreach($fiche->piecesContrat() as $i => $piece)
                    <li>
                        <a href="{{ route('fournisseurs-prestataires.pieces.show', [$fiche, 'contrat', $i]) }}" target="_blank" class="text-emerald-700 dark:text-emerald-300 font-semibold no-underline hover:underline">
                            {{ $piece['nom'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="text-[11px] text-slate-500">Joindre un fichier ajoute des pièces (les scans existants sont conservés).</p>
        @endif
        <input type="file" name="scan_contrat[]" x-ref="scanContrat" accept=".pdf,.jpg,.jpeg,.png" multiple
               class="block w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:text-white file:font-semibold file:text-xs hover:file:bg-emerald-700">
        @error('scan_contrat')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @error('scan_contrat.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <div x-show="aFiscal" x-cloak class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20 p-3 space-y-2">
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">
            Scan du dossier fiscal (PDF / images)
            @if(! $isEdit || ! $fiche?->aScanFiscal())
                <span class="text-red-500">*</span>
            @endif
        </label>
        @if($isEdit && $fiche?->aScanFiscal())
            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1">
                @foreach($fiche->piecesFiscal() as $i => $piece)
                    <li>
                        <a href="{{ route('fournisseurs-prestataires.pieces.show', [$fiche, 'fiscal', $i]) }}" target="_blank" class="text-sky-700 dark:text-sky-300 font-semibold no-underline hover:underline">
                            {{ $piece['nom'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="text-[11px] text-slate-500">Joindre un fichier ajoute des pièces (les scans existants sont conservés).</p>
        @endif
        <input type="file" name="scan_fiscal[]" x-ref="scanFiscal" accept=".pdf,.jpg,.jpeg,.png" multiple
               class="block w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-sky-600 file:text-white file:font-semibold file:text-xs hover:file:bg-sky-700">
        @error('scan_fiscal')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @error('scan_fiscal.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
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
