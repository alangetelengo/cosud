@php
    $moteur = app(\App\Services\CircuitCourrierMoteurService::class);
    $circuitCompatible = $courrier->circuit
        && $moteur->circuitCompatibleAvecSens($courrier->circuit, $courrier->sensCourrier?->code);
    $etapesCircuit = $circuitCompatible ? $moteur->etapesPourAffichage($courrier) : [];
    $peutAvancerCircuit = $circuitCompatible && $moteur->peutAgir($courrier, auth()->user());
    $etapeCourante = $circuitCompatible ? $courrier->circuitEtapeActuelle : null;
    $etapeExigeInstructions = $etapeCourante?->action === \App\Models\CircuitCourrierEtape::ACTION_INSTRUIRE;
    // « Traitement / préparation » se termine via la transmission pour signature.
    $etapeTraiteeViaSoumissionReponse = $peutSoumettreReponse ?? false;
    // « Validation / signature de la réponse » se termine via Signer / Rejeter dans Actions.
    $etapeTraiteeViaValidationReponse = ($etapeCourante?->code === 'validation_reponse_dg');
    $etapeTraiteeViaExpeditionReponse = ($etapeCourante?->code === 'expedition_reponse');
    // « AC établit le chèque » se termine via l’envoi au DG dans Actions.
    $etapeTraiteeViaEnvoiChequeAc = ($etapeCourante?->code === 'ac_etablit_cheque');
    // « DG signe le chèque » se termine via confirmation dans Actions (sans scan).
    $etapeTraiteeViaSignatureChequeDg = ($etapeCourante?->code === 'dg_signe_cheque');
    // « Décharge AC » se termine via le bordereau dans Actions (clôture du circuit).
    $etapeTraiteeViaPreuvePaiement = ($etapeCourante?->code === 'preuve_paiement');
    // Contrôle Eleni hors circuit — plus d’étape dédiée.
    $etapeTraiteeViaControleDepense = false;
    // Ne pas présenter « création courrier réponse » pour les étapes facture dédiées
    // ni pour la préparation particulière (gérée via transmission pour signature).
    $etapeCompleteeParReponse = ($etapeCourante?->meneVersCreationDepart() ?? false)
        && ! in_array($etapeCourante?->code, ['dg_signe_cheque', 'traitement_particuliere'], true);
    // Relais facture validés automatiquement (pas de « Valider l’étape »).
    $etapeRelaisFactureAuto = in_array($etapeCourante?->code, [
        'ac_vers_caissiers',
        'retour_caisse_depenses',
    ], true);
    $placeholderInstructions = match ($courrier->circuit?->code) {
        'facture_prestataire' => 'Ex. : Bon pour accord — à payer avant le 30 du mois…',
        default => 'Ex. : Répondre favorablement, préparer un projet de note…',
    };
@endphp

@if($circuitCompatible)
<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h3 class="font-semibold text-slate-800 dark:text-slate-100 text-sm">Circuit métier</h3>
                <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ $courrier->circuit->libelle }}</p>
            </div>
            @if($courrier->circuitEtapeActuelle)
                <span class="shrink-0 px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 text-[10px] font-bold">En cours</span>
            @else
                <span class="shrink-0 px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[10px] font-bold">Terminé</span>
            @endif
        </div>
    </div>

    <div class="p-3 space-y-3">
        @if($courrier->circuitEtapeActuelle)
        <div class="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2">
            <p class="text-xs font-bold text-amber-900">{{ $courrier->circuitEtapeActuelle->nom }}</p>
            <p class="text-[11px] text-amber-800/80 mt-0.5">{{ $moteur->libelleActeurPour($courrier, $courrier->circuitEtapeActuelle) }}
                @if($courrier->circuit_etape_depuis) · {{ $courrier->circuit_etape_depuis->diffForHumans() }} @endif
            </p>
            @if($courrier->circuitEtapeActuelle->instructions_aide)
            <p class="text-[11px] text-amber-900 mt-1.5 leading-snug">{{ $courrier->circuitEtapeActuelle->instructions_aide }}</p>
            @endif
            @if($courrier->instructions_dg)
            <p class="text-[11px] text-amber-950 mt-1.5 leading-snug"><strong>Instructions :</strong> {{ $courrier->instructions_dg }}</p>
            @endif
            @if($courrier->libelleDelaiExecution())
            <p class="text-[11px] text-amber-950 mt-1 leading-snug"><strong>Délai d’exécution :</strong> {{ $courrier->libelleDelaiExecution() }}</p>
            @endif
            @if($courrier->agentsConfies->isNotEmpty() || $courrier->agentConfie)
            <p class="text-[11px] text-amber-950 mt-1 leading-snug">
                <strong>Confié à :</strong>
                {{ implode(', ', $courrier->libellesAgentsConfies()) }}
            </p>
            @endif
        </div>
        @endif

        <div class="flex flex-wrap gap-1">
            @foreach($etapesCircuit as $item)
                @php $e = $item['etape']; @endphp
                <div
                    class="h-2 flex-1 min-w-[1.25rem] rounded-full
                    {{ $item['statut'] === 'en_cours' ? 'bg-amber-400' : ($item['statut'] === 'terminee' ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-600') }}"
                    title="{{ $e->ordre }}. {{ $e->nom }}"
                ></div>
            @endforeach
        </div>
        <p class="text-[10px] text-slate-400">
            {{ collect($etapesCircuit)->where('statut', 'terminee')->count() }}/{{ count($etapesCircuit) }} étapes
            <span class="text-slate-300 dark:text-slate-500">· actions manuelles</span>
        </p>

        @if($peutAvancerCircuit && $etapeExigeInstructions)
        <form method="post" action="{{ route('courriers.circuit.instruire', $courrier) }}" class="space-y-2 pt-1 border-t border-slate-100 dark:border-slate-700">
            @csrf
            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">A — Instruire le dossier</p>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Vos instructions <span class="text-red-500">*</span></label>
            <textarea name="instructions" required rows="3" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="{{ $placeholderInstructions }}">{{ old('instructions') }}</textarea>
            @error('instructions')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Délai d’exécution <span class="font-normal text-slate-400">(facultatif)</span></label>
                <div class="flex items-center gap-2">
                    <input type="number" name="delai_execution_jours" min="1" max="365" step="1"
                           value="{{ old('delai_execution_jours') }}"
                           class="w-24 rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900"
                           placeholder="Ex. 7">
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">jours auprès du destinataire</span>
                </div>
                @error('delai_execution_jours')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Envoyer / confier à <span class="font-normal text-slate-400">(facultatif)</span></label>
                @php
                    $agentsConfieCandidats = ($agentsOrientation ?? collect())
                        ->filter(fn ($ag) => $ag->hasRole('directeur'))
                        ->values();
                    $agentsConfieOptions = $agentsConfieCandidats->map(fn ($ag) => [
                        'value' => (string) $ag->id,
                        'label' => $ag->libelleDestinataireCourrier(),
                        'search' => trim($ag->name.' '.($ag->email ?? '')),
                    ])->values()->all();
                    $agentsConfieSelected = collect(old('agent_confie_ids', []))
                        ->map(fn ($id) => (string) $id)
                        ->values()
                        ->all();
                @endphp
                <script>
                    window.__agentsConfieSelect = {
                        options: @json($agentsConfieOptions),
                        selected: @json($agentsConfieSelected),
                        name: 'agent_confie_ids[]',
                        placeholder: 'Ajouter un directeur…',
                        searchPlaceholder: 'Directeur, structure…'
                    };
                    window.searchableMultiSelect = window.searchableMultiSelect || function (config) {
                        var cfg = config || {};
                        return {
                            options: cfg.options || [],
                            selectedValues: (cfg.selected || []).map(function (v) { return String(v); }),
                            search: '',
                            isOpen: false,
                            name: cfg.name || 'ids[]',
                            placeholder: cfg.placeholder || 'Ajouter des destinataires…',
                            searchPlaceholder: cfg.searchPlaceholder || 'Rechercher…',
                            selectedOptions: function () {
                                var map = {};
                                this.options.forEach(function (o) { map[String(o.value)] = o; });
                                return this.selectedValues.map(function (v) { return map[String(v)]; }).filter(Boolean);
                            },
                            filteredOptions: function () {
                                var selected = {};
                                this.selectedValues.forEach(function (v) { selected[String(v)] = true; });
                                var raw = String(this.search || '').trim().toLowerCase();
                                var tokens = raw ? raw.split(/\s+/).filter(Boolean) : [];
                                return this.options.filter(function (o) {
                                    if (selected[String(o.value)]) return false;
                                    if (!tokens.length) return true;
                                    var hay = (String(o.label || '') + ' ' + String(o.search || '')).toLowerCase();
                                    return tokens.every(function (t) { return hay.indexOf(t) >= 0; });
                                });
                            },
                            add: function (option) {
                                var value = String(option.value ?? '');
                                if (!value || this.selectedValues.indexOf(value) >= 0) return;
                                this.selectedValues.push(value);
                                this.search = '';
                                this.isOpen = true;
                                var self = this;
                                this.$nextTick(function () { if (self.$refs.searchInput) self.$refs.searchInput.focus(); });
                            },
                            remove: function (value) {
                                var v = String(value);
                                this.selectedValues = this.selectedValues.filter(function (x) { return x !== v; });
                                var self = this;
                                this.$nextTick(function () { if (self.$refs.searchInput) self.$refs.searchInput.focus(); });
                            },
                            onKeydown: function (event) {
                                if (event.key === 'Backspace' && !String(this.search || '') && this.selectedValues.length) {
                                    this.selectedValues.pop();
                                    return;
                                }
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    var first = this.filteredOptions()[0];
                                    if (first) this.add(first);
                                }
                                if (event.key === 'Escape') this.isOpen = false;
                            }
                        };
                    };
                </script>
                <div
                    class="relative"
                    x-data="window.searchableMultiSelect(window.__agentsConfieSelect)"
                    @click.outside="isOpen = false"
                >
                    <template x-for="val in selectedValues" :key="'hid-'+val">
                        <input type="hidden" :name="name" :value="val">
                    </template>
                    <div
                        @click="isOpen = true; $nextTick(() => $refs.searchInput?.focus())"
                        class="min-h-[2.5rem] w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-2 py-1.5 flex flex-wrap items-center gap-1.5 cursor-text focus-within:ring-2 focus-within:ring-emerald-500/30 focus-within:border-emerald-500 transition"
                    >
                        <template x-for="opt in selectedOptions()" :key="'chip-'+opt.value">
                            <span class="inline-flex items-center gap-1 max-w-full rounded-full bg-emerald-50 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200/80 dark:border-emerald-800/60 pl-2.5 pr-1 py-0.5 text-[11px] font-semibold">
                                <span class="truncate" x-text="opt.label" :title="opt.search || opt.label"></span>
                                <button type="button" @click.stop="remove(opt.value)" class="flex-shrink-0 w-4 h-4 rounded-full hover:bg-emerald-200/70 dark:hover:bg-emerald-800/60 flex items-center justify-center text-[10px] leading-none" title="Retirer">×</button>
                            </span>
                        </template>
                        <input
                            type="text"
                            x-ref="searchInput"
                            x-model="search"
                            @focus="isOpen = true"
                            @keydown="onKeydown($event)"
                            :placeholder="selectedValues.length ? '' : placeholder"
                            class="flex-1 min-w-[8rem] border-0 bg-transparent p-1 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:ring-0 focus:outline-none"
                            autocomplete="off"
                        >
                    </div>
                    <div
                        x-show="isOpen"
                        x-cloak
                        x-transition
                        class="absolute z-30 top-full left-0 right-0 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-xl max-h-56 overflow-hidden flex flex-col"
                    >
                        <div class="px-2.5 py-1.5 border-b border-slate-100 dark:border-slate-700 text-[10px] text-slate-500 dark:text-slate-400" x-text="searchPlaceholder"></div>
                        <div class="overflow-y-auto flex-1 p-1 max-h-48">
                            <template x-if="filteredOptions().length === 0">
                                <p class="px-3 py-2 text-xs text-slate-500">Aucun destinataire trouvé.</p>
                            </template>
                            <template x-for="opt in filteredOptions()" :key="'opt-'+opt.value">
                                <button
                                    type="button"
                                    @click.stop="add(opt)"
                                    class="w-full text-left px-2.5 py-1.5 rounded text-xs text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-900/30"
                                >
                                    <span class="font-semibold block truncate" x-text="opt.label"></span>
                                    <span class="text-[10px] text-slate-400 truncate block" x-text="opt.search" x-show="opt.search"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                <p class="text-[10px] text-slate-500 mt-1">Uniquement les <strong>directeurs</strong>. Tapez pour rechercher, cliquez pour ajouter. Vide = suite normale du circuit.</p>
                @error('agent_confie_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('agent_confie_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="button"
                    onclick="flashAlert('Enregistrer ces instructions et transmettre pour traitement ?', this.closest('form'), {icon:'📝', danger:false, confirmText:'Enregistrer', title:'Instructions'})"
                    class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white font-semibold text-xs hover:bg-emerald-700">
                Enregistrer les instructions
            </button>
        </form>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaSoumissionReponse)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine en transmettant le courrier de réponse pour signature (voir « Actions » ci-dessous).
        </p>
        @elseif($peutAvancerCircuit && $etapeCompleteeParReponse)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine automatiquement lors de la création du courrier réponse (voir « Actions » ci-dessous).
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaValidationReponse)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Signez ou rejetez la réponse via « Actions » ci-dessous.
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaExpeditionReponse)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Expédiez le courrier départ signé via « Actions » (lien vers le départ).
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaEnvoiChequeAc)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine automatiquement en envoyant le chèque au DG (voir « Actions » ci-dessous).
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaSignatureChequeDg)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Confirmez la signature du chèque (sans scan) via « Actions » ci-dessous — le dossier revient à l’AC.
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaPreuvePaiement)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Enregistrez le bordereau et les pièces de décharge via « Actions » — cette action clôture le circuit.
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaControleDepense)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Contrôlez les pièces via « Actions » (hors circuit — sans clôturer).
        </p>
        @elseif($etapeRelaisFactureAuto)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape de relais est validée automatiquement — pas d’action manuelle requise.
        </p>
        @elseif($peutAvancerCircuit && $courrier->circuitEtapeActuelle)
        <form method="post" action="{{ route('courriers.circuit.avancer', $courrier) }}" class="space-y-2 pt-1 border-t border-slate-100 dark:border-slate-700">
            @csrf
            <input type="text" name="commentaire" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Commentaire (optionnel)">
            <button type="button"
                    onclick="flashAlert('Valider l’étape « {{ $courrier->circuitEtapeActuelle->nom }} » ?', this.closest('form'), {icon:'✓', danger:false, confirmText:'Valider l’étape', title:'Circuit métier'})"
                    class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white font-semibold text-xs hover:bg-emerald-700">
                Valider l’étape
            </button>
        </form>
        @endif

        @if(auth()->user()->aAccesTotal()
            && $courrier->circuitEtapeActuelle
            && ! $moteur->userCorrespondActeur(auth()->user(), $courrier->circuitEtapeActuelle, $courrier))
        <form method="post" action="{{ route('courriers.circuit.relancer', $courrier) }}" class="space-y-2">
            @csrf
            <input type="text" name="commentaire" class="w-full rounded-lg border border-amber-300 px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Message de relance…">
            <button type="button"
                    onclick="flashAlert('Relancer le responsable de l’étape en cours ?', this.closest('form'), {icon:'🔔', danger:false, confirmText:'Relancer', title:'Relance'})"
                    class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white font-semibold text-xs hover:bg-amber-700">
                Relancer le responsable
            </button>
        </form>
        @endif
    </div>
</div>
@endif
