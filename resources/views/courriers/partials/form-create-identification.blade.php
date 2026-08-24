<section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Identification</h2>
        <p class="text-xs text-slate-500 mt-0.5">Objet, type et circuit de traitement</p>
    </div>
    <div class="p-5 space-y-4">
        <div>
            <label class="{{ $label }}">Objet <span class="text-red-500 normal-case tracking-normal">*</span></label>
            <input type="text" name="objet" value="{{ old('objet') }}" required class="{{ $field }}" placeholder="Objet du courrier">
            @error('objet')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Type</label>
                <select name="type_courrier_id" id="type_courrier_id" class="{{ $field }}">
                    <option value="">— Choisir —</option>
                    @foreach($types as $t)
                    @php
                        $circuitDuType = ($t->circuit && $t->circuit->sens_initial === $sensCode) ? $t->circuit : null;
                        $necessiteServiceDemandeur = in_array($t->code, ['facture', 'mad'], true);
                        // MAD : pas de contacts externes. Facture / demande : téléphone obligatoire (SMS / mail).
                        $afficheContacts = ! in_array($t->code, ['mad'], true);
                        $telephoneObligatoire = in_array($t->code, ['facture', 'demande'], true);
                    @endphp
                    <option
                        value="{{ $t->id }}"
                        data-code="{{ $t->code }}"
                        data-circuit-libelle="{{ $circuitDuType?->libelle }}"
                        data-service-demandeur="{{ $necessiteServiceDemandeur ? '1' : '0' }}"
                        data-contacts="{{ $afficheContacts ? '1' : '0' }}"
                        data-telephone-requis="{{ $telephoneObligatoire ? '1' : '0' }}"
                        @selected(old('type_courrier_id') == $t->id)
                    >{{ $t->libelle }}</option>
                    @endforeach
                </select>
                <p id="circuit-du-type" class="text-xs text-slate-500 mt-1.5"></p>
            </div>
            <div>
                <label class="{{ $label }}">Priorité</label>
                <select name="priorite_courrier_id" class="{{ $field }}">
                    <option value="">Normale</option>
                    @foreach($priorites as $p)<option value="{{ $p->id }}" @selected(old('priorite_courrier_id') == $p->id)>{{ $p->libelle }}</option>@endforeach
                </select>
            </div>
        </div>

        @if($sensCode === 'arrivee' && isset($directions))
        <div id="bloc-service-demandeur" class="hidden">
            <label class="{{ $label }}">Service demandeur <span class="text-red-500 normal-case tracking-normal">*</span></label>
            <select name="service_demandeur_structure_id" id="service_demandeur_structure_id" class="{{ $field }}">
                <option value="">— Choisir une direction —</option>
                @foreach($directions as $direction)
                <option value="{{ $direction->id }}" @selected(old('service_demandeur_structure_id') == $direction->id)>{{ $direction->nom }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1.5">Direction ou antenne départementale à l’origine de la demande.</p>
            @error('service_demandeur_structure_id')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
        </div>
        @endif
    </div>
</section>
