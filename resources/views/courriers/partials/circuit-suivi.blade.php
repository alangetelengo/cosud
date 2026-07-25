@php
    $moteur = app(\App\Services\CircuitCourrierMoteurService::class);
    $circuitCompatible = $courrier->circuit
        && $moteur->circuitCompatibleAvecSens($courrier->circuit, $courrier->sensCourrier?->code);
    $etapesCircuit = $circuitCompatible ? $moteur->etapesPourAffichage($courrier) : [];
    $peutAvancerCircuit = $circuitCompatible && $moteur->peutAgir($courrier, auth()->user());
    $etapeCourante = $circuitCompatible ? $courrier->circuitEtapeActuelle : null;
    $etapeExigeInstructions = $etapeCourante?->action === \App\Models\CircuitCourrierEtape::ACTION_INSTRUIRE;
    // « Traitement par la particulière » se termine via la soumission du projet de réponse.
    $etapeTraiteeViaSoumissionReponse = $peutSoumettreReponse ?? false;
    // « Validation de la réponse » se termine via Valider / Rejeter dans Actions.
    $etapeTraiteeViaValidationReponse = ($etapeCourante?->code === 'validation_reponse_dg');
    // « AC établit le chèque » se termine via l’envoi au DG dans Actions.
    $etapeTraiteeViaEnvoiChequeAc = ($etapeCourante?->code === 'ac_etablit_cheque');
    // « DG signe le chèque » se termine via le scan signé dans Actions.
    $etapeTraiteeViaSignatureChequeDg = ($etapeCourante?->code === 'dg_signe_cheque');
    // « Preuve de paiement » se termine via le dépôt dans Actions.
    $etapeTraiteeViaPreuvePaiement = ($etapeCourante?->code === 'preuve_paiement');
    // Ne pas présenter « création courrier réponse » pour les étapes facture dédiées.
    $etapeCompleteeParReponse = ($etapeCourante?->meneVersCreationDepart() ?? false)
        && ! in_array($etapeCourante?->code, ['dg_signe_cheque', 'traitement_dossiers_vers_ac'], true);
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
            @if($courrier->agentConfie)
            <p class="text-[11px] text-amber-950 mt-1 leading-snug"><strong>Confié à :</strong> {{ $courrier->agentConfie->name }}</p>
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
        </p>

        @if($peutAvancerCircuit && $etapeExigeInstructions)
        <form method="post" action="{{ route('courriers.circuit.instruire', $courrier) }}" class="space-y-2 pt-1 border-t border-slate-100 dark:border-slate-700">
            @csrf
            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">A — Instruire le dossier</p>
            <p class="text-[10.5px] text-slate-500 leading-snug">Sans pièce jointe. Vous pouvez confier le dossier à un agent (facultatif) : il devient le prochain acteur.</p>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Vos instructions <span class="text-red-500">*</span></label>
            <textarea name="instructions" required rows="3" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. : À payer avant le 30 du mois, transmettre à l’Agent Comptable…">{{ old('instructions') }}</textarea>
            @error('instructions')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Confier à un agent <span class="font-normal text-slate-400">(facultatif)</span></label>
                <select name="agent_confie_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900">
                    <option value="">— Suite normale du circuit —</option>
                    @foreach(($agentsOrientation ?? collect()) as $ag)
                    <option value="{{ $ag->id }}" @selected((string) old('agent_confie_id') === (string) $ag->id)>{{ $ag->name }}</option>
                    @endforeach
                </select>
                @error('agent_confie_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="button"
                    onclick="flashAlert('Enregistrer ces instructions et transmettre pour traitement ?', this.closest('form'), {icon:'📝', danger:false, confirmText:'Enregistrer', title:'Instructions'})"
                    class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white font-semibold text-xs hover:bg-emerald-700">
                Enregistrer les instructions
            </button>
        </form>
        @elseif($peutAvancerCircuit && $etapeCompleteeParReponse)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine automatiquement lors de la création du courrier réponse (voir « Actions » ci-dessous).
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaSoumissionReponse)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine par la soumission du projet de réponse au DG (voir « Actions » ci-dessous).
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaValidationReponse)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Validez ou rejetez le projet via « Actions » ci-dessous.
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaEnvoiChequeAc)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine automatiquement en envoyant le chèque au DG (voir « Actions » ci-dessous).
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaSignatureChequeDg)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine en enregistrant le scan du chèque signé (voir « Actions » ci-dessous).
        </p>
        @elseif($peutAvancerCircuit && $etapeTraiteeViaPreuvePaiement)
        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-700 italic">
            Cette étape se termine en déposant la preuve de paiement (voir « Actions » ci-dessous) — le dossier sera clôturé.
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
