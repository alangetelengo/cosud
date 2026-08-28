@php
    $cleAnnulation = $courrier->cleFormulaireAnnulation();
    $motifAnnulationRequis = $courrier->motifAnnulationRequis();
@endphp

@can('annuler', $courrier)
@if($cleAnnulation)
<div id="action-annuler-courrier" class="rounded-lg border border-red-200 dark:border-red-800/60 bg-red-50/50 dark:bg-red-950/20 p-3 space-y-2">
    <div>
        <p class="text-xs font-semibold text-red-900 dark:text-red-200">Annuler le courrier</p>
        <p class="text-[11px] text-red-800/80 dark:text-red-300/80 mt-0.5 leading-snug">
            Le n° {{ $courrier->numeroRegistreComplet() }} restera visible au registre avec le statut « Annulé » (trace conservée).
        </p>
    </div>
    <button type="button"
            x-show="form !== @js($cleAnnulation)"
            @click="form = @js($cleAnnulation); $nextTick(() => document.getElementById('action-annuler-courrier')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
            class="w-full px-3 py-2 rounded-lg border border-red-400/70 dark:border-red-700 text-red-800 dark:text-red-200 text-xs font-semibold hover:bg-red-100/80 dark:hover:bg-red-900/30 transition-colors">
        Annuler ce courrier
    </button>
    <form x-show="form === @js($cleAnnulation)" x-cloak method="post" action="{{ route('courriers.annuler', $courrier) }}" class="space-y-2">
        @csrf
        <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-200">
            Motif @if(! $motifAnnulationRequis)<span class="font-normal text-slate-400">(facultatif)</span>@else<span class="text-red-500">*</span>@endif
        </label>
        <textarea name="motif_annulation" rows="2" @if($motifAnnulationRequis) required @endif
                  class="w-full rounded-lg border border-red-200 dark:border-red-800 px-2.5 py-1.5 text-xs dark:bg-slate-900"
                  placeholder="Ex. doublon, mauvaise saisie, courrier retiré…">{{ old('motif_annulation') }}</textarea>
        @error('motif_annulation')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-2">
            <button type="button"
                    onclick="flashAlert('Confirmer l’annulation de ce courrier ?', this.closest('form'), {icon:'🚫', danger:true, confirmText:'Annuler le courrier', title:'Annulation'})"
                    class="flex-1 min-w-[8rem] px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold">
                Confirmer l’annulation
            </button>
            <button type="button" @click="form = null"
                    class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Fermer
            </button>
        </div>
    </form>
</div>
@endif
@endcan

@can('delete', $courrier)
<div id="action-supprimer-courrier" class="rounded-lg border border-red-300/70 dark:border-red-900/50 bg-white dark:bg-slate-900/40 p-3 space-y-2">
    <div>
        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">Supprimer l’enregistrement</p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
            Effacement définitif — uniquement si le courrier n’est pas engagé dans le circuit. Vous pourrez le resaisir ensuite.
        </p>
    </div>
    <button type="button"
            x-show="form !== 'supprimer-courrier'"
            @click="form = 'supprimer-courrier'; $nextTick(() => document.getElementById('action-supprimer-courrier')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
            class="w-full px-3 py-2 rounded-lg border border-red-500/50 text-red-800 dark:text-red-200 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
        Supprimer définitivement
    </button>
    <form x-show="form === 'supprimer-courrier'" x-cloak method="post" action="{{ route('courriers.destroy', $courrier) }}" class="space-y-2">
        @csrf
        @method('DELETE')
        <div class="flex flex-wrap gap-2">
            <button type="button"
                    onclick="flashAlert('Confirmer la suppression définitive de ce courrier ?', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer', title:'Suppression'})"
                    class="flex-1 min-w-[8rem] px-3 py-2 rounded-lg bg-red-700 hover:bg-red-800 text-white text-xs font-semibold">
                Confirmer la suppression
            </button>
            <button type="button" @click="form = null"
                    class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Fermer
            </button>
        </div>
    </form>
</div>
@endcan
