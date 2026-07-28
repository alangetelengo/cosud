@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Courriers internes à réceptionner')
@section('page-title-info', 'Courriers départ expédiés par un autre secrétariat ACSI, en attente de votre réception.')

@section('btn-create')
    <a href="{{ route('courriers.index', ['sens' => 'arrivee']) }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm no-underline transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Retour aux courriers
    </a>
@endsection

@section('content')
<div class="space-y-5">
    @include('partials.flash-session')

    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-sky-50/90 to-white dark:from-sky-950/30 dark:to-slate-800">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0H4m8 0v6"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">File d’attente de réception</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ $courriers->total() }} courrier{{ $courriers->total() > 1 ? 's' : '' }} en attente
                    </p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                        <th class="px-5 py-3.5 font-semibold">N° registre</th>
                        <th class="px-5 py-3.5 font-semibold">Objet</th>
                        <th class="px-5 py-3.5 font-semibold">Émetteur</th>
                        <th class="px-5 py-3.5 font-semibold">Date expédition</th>
                        <th class="px-5 py-3.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($courriers as $c)
                    <tr id="courrier-{{ $c->id }}" class="group hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors {{ (int) request('highlight') === (int) $c->id ? 'bg-sky-50 dark:bg-sky-900/30 ring-2 ring-inset ring-sky-300 dark:ring-sky-700' : '' }}">
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="inline-flex items-center font-mono text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-700/70 px-2.5 py-1 rounded-lg ring-1 ring-slate-200/80 dark:ring-slate-600/60">
                                {{ $c->numeroRegistreComplet() }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-800 dark:text-slate-100 max-w-md">
                            <span class="line-clamp-2 leading-snug" title="{{ $c->objet }}">{{ $c->objet }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-700 dark:text-slate-200 font-medium max-w-[12rem] truncate" title="{{ $c->structure?->nom ?? $c->createur?->name }}">
                            {{ $c->structure?->nom ?? $c->createur?->name ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-slate-600 dark:text-slate-300 whitespace-nowrap tabular-nums">
                            {{ $c->date_expedition?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('courriers.show', $c) }}"
                                   class="inline-flex items-center justify-center min-w-[2.35rem] min-h-[2.35rem] p-2 rounded-xl text-sky-600 dark:text-sky-400 bg-sky-50/80 dark:bg-sky-900/20 hover:bg-sky-100 dark:hover:bg-sky-900/40 border border-sky-100/80 dark:border-sky-800/40 shadow-sm transition"
                                   title="Consulter">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span class="sr-only">Consulter</span>
                                </a>

                                @can('recevoir', $c)
                                <form method="post" action="{{ route('courriers.accepter-reception', $c) }}" class="inline">
                                    @csrf
                                    <button type="button"
                                            onclick="flashAlert('Accepter la réception du courrier n° {{ $c->numeroRegistreComplet() }} ? Un courrier arrivée interne sera créé.', this.closest('form'), {icon:'📥', danger:false, confirmText:'Accepter', title:'Réception'})"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/25 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 border border-emerald-100 dark:border-emerald-800/40 shadow-sm text-xs font-semibold transition"
                                            title="Accepter la réception">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Accepter
                                    </button>
                                </form>

                                <button type="button"
                                        onclick="flashAlert('Refuser la réception du courrier n° {{ $c->numeroRegistreComplet() }} ? Vous indiquerez ensuite le motif.', function () { var p = document.getElementById('refus-panel-{{ $c->id }}'); if (p) { p.classList.remove('hidden'); p.scrollIntoView({behavior:'smooth', block:'nearest'}); } }, {icon:'🚫', danger:true, confirmText:'Continuer', title:'Refus de réception'})"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-900/25 hover:bg-rose-100 dark:hover:bg-rose-900/40 border border-rose-100 dark:border-rose-800/40 shadow-sm text-xs font-semibold transition"
                                        title="Refuser la réception">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Refuser
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center">
                            <div class="inline-flex flex-col items-center gap-3 text-slate-500">
                                <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-sky-50 dark:bg-sky-900/30 text-sky-400">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0H4m8 0v6"/></svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Aucun courrier en attente de réception</p>
                                    <p class="text-xs mt-1 text-slate-500">Les courriers internes adressés à votre structure apparaîtront ici.</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach($courriers as $c)
        @can('recevoir', $c)
        <div id="refus-panel-{{ $c->id }}" class="hidden rounded-2xl border border-rose-200 dark:border-rose-800/50 bg-rose-50/60 dark:bg-rose-950/20 p-4 sm:p-5 shadow-sm">
            <div class="flex items-start gap-3 mb-3">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-300 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-rose-900 dark:text-rose-100">Refuser le courrier {{ $c->numeroRegistreComplet() }}</h3>
                    <p class="text-xs text-rose-700/80 dark:text-rose-300/80 mt-0.5">Indiquez le motif du refus (non-conformité, erreur d’adressage…).</p>
                </div>
            </div>
            <form method="post" action="{{ route('courriers.refuser-reception', $c) }}" class="space-y-3">
                @csrf
                <textarea name="motif_rejet" required rows="3"
                          class="w-full rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/25 focus:border-rose-500"
                          placeholder="Motif du refus…"></textarea>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            onclick="flashAlert('Confirmer le refus de réception du courrier n° {{ $c->numeroRegistreComplet() }} ?', this.closest('form'), {icon:'🚫', danger:true, confirmText:'Refuser', title:'Refus de réception'})"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-sm font-semibold shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Confirmer le refus
                    </button>
                    <button type="button" data-refus-toggle="refus-panel-{{ $c->id }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
        @endcan
    @endforeach

    @if($courriers->hasPages())
    <div class="pt-1">
        {{ $courriers->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-refus-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-refus-toggle');
            var panel = document.getElementById(id);
            if (!panel) return;
            panel.classList.toggle('hidden');
            if (!panel.classList.contains('hidden')) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                var ta = panel.querySelector('textarea');
                if (ta) ta.focus();
            }
        });
    });
});
</script>
@endpush
