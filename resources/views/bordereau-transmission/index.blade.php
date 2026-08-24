@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Bordereau de transmission')
@section('page-title-info', 'Année '.$annee.' — '.$nbLignes.' ligne(s)')

@section('btn-create')
    <a href="{{ route('bordereau-transmission.export', request()->query()) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all no-underline">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Exporter CSV
    </a>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4">
    <form method="get" action="{{ route('bordereau-transmission.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm">
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Année</label>
            <select name="annee" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900" onchange="this.form.submit()">
                @foreach($annees as $a)
                    <option value="{{ $a }}" @selected((int) $annee === (int) $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Périodicité</label>
            <select name="periode" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900" onchange="this.form.submit()">
                @foreach($periodes as $codePeriode)
                    <option value="{{ $codePeriode }}" @selected($periode === $codePeriode)>
                        {{ $service->libellePeriodeBordereau($codePeriode) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[12rem]">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Recherche</label>
            <input type="search" name="q" value="{{ $q }}" placeholder="N° chèque, banque, bénéficiaire…" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 dark:bg-emerald-600 text-white text-sm font-semibold hover:bg-slate-900 dark:hover:bg-emerald-700">Filtrer</button>
    </form>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Bordereau de transmission</h2>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                    Chèques transmis pour signature (y compris en attente de décharge) — regroupement
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ strtolower($periodeLibelle) }}</span>.
                </p>
            </div>
            @if($nbLignes > 0)
            <p class="text-sm font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">
                Total {{ $annee }} : {{ number_format($totalGeneral, 0, ',', ' ') }} FCFA
            </p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700">
                        <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 whitespace-nowrap" style="color:#475569;">Date</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 whitespace-nowrap" style="color:#475569;">N° pièce</th>
                        <th scope="col" class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 whitespace-nowrap" style="color:#475569;">Montant (FCFA)</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300" style="color:#475569;">Banque</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300" style="color:#475569;">Bénéficiaire</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300" style="color:#475569;">Programmation</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300" style="color:#475569;">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($sections as $section)
                        @foreach($section['lignes'] as $ligne)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                            <td class="px-3 py-2 whitespace-nowrap text-slate-700 dark:text-slate-200">{{ $ligne->date_suivi?->format('d/m/Y') }}</td>
                            <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">
                                @if($ligne->courrier_id)
                                    <a href="{{ route('courriers.show', $ligne->courrier_id) }}" class="text-emerald-700 dark:text-emerald-400 hover:underline no-underline">{{ $ligne->numero_piece }}</a>
                                @else
                                    {{ $ligne->numero_piece }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ number_format((float) $ligne->montant, 0, ',', ' ') }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $ligne->banque ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $ligne->beneficiaire_libelle ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $ligne->programmation ?: '—' }}</td>
                            <td class="px-3 py-2">
                                @php $statut = $service->statutBordereau($ligne); @endphp
                                <span @class([
                                    'inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' => $statut === 'Déchargé',
                                    'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' => $statut === 'Signature DG',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' => ! in_array($statut, ['Déchargé', 'Signature DG'], true),
                                ])>{{ $statut }}</span>
                            </td>
                        </tr>
                        @endforeach
                        <tr class="bg-emerald-50/80 dark:bg-emerald-950/30">
                            <td colspan="2" class="px-3 py-2.5 text-xs font-bold text-emerald-900 dark:text-emerald-100">{{ $section['libelle'] }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-sm font-bold text-emerald-700 dark:text-emerald-300 whitespace-nowrap">{{ number_format($section['total'], 0, ',', ' ') }}</td>
                            <td colspan="4" class="px-3 py-2.5"></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucun chèque enregistré pour cette période.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($nbLignes > 0)
                <tfoot>
                    <tr class="bg-slate-100 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700">
                        <td colspan="2" class="px-3 py-3 text-sm font-bold text-slate-800 dark:text-slate-100">Total général {{ $annee }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-sm font-bold text-emerald-700 dark:text-emerald-300 whitespace-nowrap">{{ number_format($totalGeneral, 0, ',', ' ') }}</td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
