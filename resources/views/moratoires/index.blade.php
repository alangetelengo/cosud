@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Moratoires / paiements progressifs')
@section('page-title-info', $moratoires->total().' plan(s)')

@section('btn-create')
    @can('create', App\Models\Moratoire::class)
    <a href="{{ route('moratoires.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-all no-underline">
        Nouveau moratoire
    </a>
    @endcan
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4">
    @if($dettes->isNotEmpty())
    <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-amber-100 dark:border-amber-900/40 bg-amber-50/80 dark:bg-amber-900/20">
            <h2 class="text-sm font-bold text-amber-900 dark:text-amber-100 uppercase tracking-wide">Dettes fournisseurs</h2>
            <p class="text-xs text-amber-800/90 dark:text-amber-200/80 mt-0.5">Factures − paiements (chèques déchargés / échéances renseignées)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold">Fournisseur</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Facturé</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Payé</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Dette</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($dettes as $dette)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '' }}">
                        <td class="px-3 py-2 font-semibold text-slate-800 dark:text-slate-100">{{ $dette['fournisseur_libelle'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format($dette['montant_facture'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format($dette['montant_paye'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-bold whitespace-nowrap text-slate-900 dark:text-slate-100">{{ number_format($dette['dette'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <div class="inline-flex flex-wrap items-center gap-1.5">
                            @if($dette['moratoire_actif_id'])
                                <x-table-action :href="route('moratoires.show', $dette['moratoire_actif_id'])">Voir plan</x-table-action>
                            @else
                                @can('create', App\Models\Moratoire::class)
                                <x-table-action :href="route('moratoires.create', ['fournisseur' => $dette['fournisseur_libelle']])">Créer plan</x-table-action>
                                @endcan
                            @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Plans de paiement progressif</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $moratoires->total() }} plan(s)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold">Fournisseur</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Dette initiale</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Échéance</th>
                        <th class="px-3 py-2 text-center font-bold whitespace-nowrap">Lignes</th>
                        <th class="px-3 py-2 text-left font-bold">Statut</th>
                        <th class="px-3 py-2 text-left font-bold">Créé par</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($moratoires as $m)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '' }}">
                        <td class="px-3 py-2 font-semibold text-slate-800 dark:text-slate-100">{{ $m->fournisseur_libelle }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format((float) $m->montant_dette_initial, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format((float) $m->montant_echeance_defaut, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-center text-slate-700 dark:text-slate-200">{{ $m->echeances_count }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold {{ $m->statut === 'solde' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : ($m->statut === 'annule' ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' : 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200') }}">
                                {{ $m->libelleStatut() }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $m->createur?->name ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <div class="inline-flex flex-wrap items-center gap-1.5">
                                <x-table-action :href="route('moratoires.show', $m)">Ouvrir</x-table-action>
                                <x-table-action :href="route('moratoires.print', $m)" variant="secondary">PDF</x-table-action>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Aucun moratoire pour le moment.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($moratoires->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">{{ $moratoires->links() }}</div>
        @endif
    </div>
</div>
@endsection
