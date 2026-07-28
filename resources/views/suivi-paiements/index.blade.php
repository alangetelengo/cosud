@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', $titreFiche)
@section('page-title-info', 'Année '.$annee.' — '.$lignes->count().' ligne(s)')

@section('btn-create')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('suivi-paiements.export', request()->query()) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all no-underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Exporter Excel (CSV)
        </a>
    </div>
@endsection

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('suivi-paiements.index', array_merge(request()->except('type'), ['type' => \App\Models\SuiviPaiement::TYPE_FSP_FACTURE])) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-colors {{ $type === \App\Models\SuiviPaiement::TYPE_FSP_FACTURE ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
            FSP FACTURE
        </a>
        <a href="{{ route('suivi-paiements.index', array_merge(request()->except('type'), ['type' => \App\Models\SuiviPaiement::TYPE_FSP_MAD])) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-colors {{ $type === \App\Models\SuiviPaiement::TYPE_FSP_MAD ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
            FSP MAD
        </a>
    </div>

    <form method="get" action="{{ route('suivi-paiements.index') }}" class="flex flex-wrap items-end gap-3 p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <input type="hidden" name="type" value="{{ $type }}">
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Année</label>
            <select name="annee" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900 min-w-[100px]">
                @foreach($annees as $a)
                    <option value="{{ $a }}" @selected((int) $a === (int) $annee)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Recherche</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Intitulé, fournisseur, demandeur…"
                   class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900">
            Filtrer
        </button>
        @if(request()->filled('q'))
        <a href="{{ route('suivi-paiements.index', ['type' => $type, 'annee' => $annee]) }}"
           class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 no-underline">Réinitialiser</a>
        @endif
    </form>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">{{ $libelleType }}</h2>
            <p class="text-xs text-slate-500 mt-0.5">{{ $titreFiche }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="w-[4%] px-3 py-2 text-left font-bold whitespace-nowrap">N°</th>
                        <th class="w-[6%] px-3 py-2 text-left font-bold whitespace-nowrap">Date</th>
                        <th class="w-[18%] px-3 py-2 text-left font-bold">Intitulé</th>
                        <th class="w-[8%] px-3 py-2 text-right font-bold whitespace-nowrap">Montant</th>
                        @if($type === \App\Models\SuiviPaiement::TYPE_FSP_MAD)
                            <th class="w-[10%] px-3 py-2 text-left font-bold">Demandeur</th>
                            <th class="w-[10%] px-3 py-2 text-left font-bold">Responsable chargé du dossier</th>
                        @else
                            <th class="w-[10%] px-3 py-2 text-left font-bold">Fournisseur</th>
                            <th class="w-[10%] px-3 py-2 text-left font-bold">Service demandeur</th>
                        @endif
                        <th class="w-[14%] px-3 py-2 text-left font-bold">Instruction du DG</th>
                        <th class="w-[14%] px-3 py-2 text-left font-bold">Observation</th>
                        <th class="w-[6%] px-3 py-2 text-left font-bold whitespace-nowrap">Courrier</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($lignes as $ligne)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20">
                        <td class="px-3 py-2 font-semibold whitespace-nowrap">{{ $ligne->numeroComplet() }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $ligne->date_suivi->format('d/m/Y') }}</td>
                        <td class="px-3 py-2 leading-snug">{{ $ligne->intitule }}</td>
                        <td class="px-3 py-2 text-right font-semibold whitespace-nowrap tabular-nums">
                            {{ app(\App\Services\SuiviPaiementService::class)->formaterMontant($ligne->montant) }}
                        </td>
                        @if($type === \App\Models\SuiviPaiement::TYPE_FSP_MAD)
                            <td class="px-3 py-2">{{ $ligne->demandeur_libelle ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $ligne->responsableDossier?->name ?? '—' }}</td>
                        @else
                            <td class="px-3 py-2">{{ $ligne->fournisseur_libelle ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $ligne->service_demandeur_libelle ?? '—' }}</td>
                        @endif
                        <td class="px-3 py-2 leading-snug">{{ $ligne->instruction_dg ?? '—' }}</td>
                        <td class="px-3 py-2 leading-snug text-slate-500">{{ $ligne->observation ?? '' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if($ligne->courrier)
                            <a href="{{ route('courriers.show', $ligne->courrier) }}" class="text-emerald-600 font-semibold no-underline hover:underline">
                                n° {{ $ligne->courrier->numeroRegistreComplet() }}
                            </a>
                            @else
                            —
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">
                            Aucune entrée pour {{ $libelleType }} en {{ $annee }}.
                            <span class="block text-xs mt-1">Les lignes sont créées automatiquement quand l’AC établit un chèque.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($lignes->isNotEmpty())
                <tfoot class="bg-emerald-50 dark:bg-emerald-900/20 border-t-2 border-emerald-200 dark:border-emerald-800">
                    <tr>
                        <td colspan="3" class="px-3 py-3 text-sm font-bold text-emerald-900 dark:text-emerald-200">
                            Total {{ now()->format('d/m/Y') }}
                        </td>
                        <td class="px-3 py-3 text-right text-sm font-bold text-emerald-900 dark:text-emerald-200 tabular-nums">
                            {{ app(\App\Services\SuiviPaiementService::class)->formaterMontant($totalMontant) }}
                        </td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
