@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Moratoire — '.$moratoire->fournisseur_libelle)
@section('page-title-info', 'Dette initiale '.number_format((float) $moratoire->montant_dette_initial, 0, ',', ' ').' FCFA — '.$moratoire->libelleStatut())

@section('btn-create')
    <a href="{{ route('moratoires.print', $moratoire) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-emerald-600 text-emerald-800 dark:text-emerald-200 text-sm font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/30 shadow-sm transition-all no-underline">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-4 0v4m0-4H8v4"/></svg>
        Imprimer PDF
    </a>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4">
    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-100">
        <p class="font-semibold">État récapitulatif des paiements progressifs — {{ $moratoire->fournisseur_libelle }}</p>
        <p class="text-xs mt-1 text-emerald-800/90 dark:text-emerald-200/90 leading-snug">
            Échéance type : {{ number_format((float) $moratoire->montant_echeance_defaut, 0, ',', ' ') }} FCFA
            · Payé : {{ number_format($moratoire->montantPaye(), 0, ',', ' ') }} FCFA
            · Solde restant : {{ number_format($moratoire->soldeRestant(), 0, ',', ' ') }} FCFA
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Échéancier</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $moratoire->echeances->count() }} ligne(s) — {{ $moratoire->libelleStatut() }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-center font-bold whitespace-nowrap">N°</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Montant dette</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Échéancier</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Solde</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">N° chèque</th>
                        <th class="px-3 py-2 text-left font-bold">Banque</th>
                        <th class="px-3 py-2 text-left font-bold">OBS</th>
                        @can('update', $moratoire)
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                        @endcan
                    </tr>
                </thead>
                @php
                    $nbColonnes = auth()->user()?->can('update', $moratoire) ? 8 : 7;
                    $echeanceErreurId = old('_echeance_id') ? (int) old('_echeance_id') : null;
                @endphp
                @foreach($moratoire->echeances as $echeance)
                <tbody
                    x-data="{ open: {{ $echeanceErreurId === (int) $echeance->id ? 'true' : 'false' }} }"
                    class="border-b border-slate-100 dark:border-slate-700"
                >
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $echeance->estPayee() ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : ($loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '') }}">
                        <td class="px-3 py-2 text-center font-semibold text-slate-800 dark:text-slate-100">{{ $echeance->numero }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format((float) $echeance->montant_dette, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold whitespace-nowrap text-slate-900 dark:text-slate-100">{{ number_format((float) $echeance->montant_echeance, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format((float) $echeance->solde, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $echeance->numero_cheque ?: '—' }}</td>
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $echeance->banque ?: '—' }}</td>
                        <td class="px-3 py-2 max-w-[140px] truncate text-slate-600 dark:text-slate-300" title="{{ $echeance->observation }}">{{ $echeance->observation ?: '—' }}</td>
                        @can('update', $moratoire)
                        <td class="px-3 py-2 whitespace-nowrap">
                            <button type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg border border-emerald-600/50 text-emerald-700 dark:text-emerald-300 text-[11px] font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
                                    x-text="open ? 'Fermer' : '{{ $echeance->estPayee() ? 'Modifier' : 'Saisir' }}'">
                                {{ $echeance->estPayee() ? 'Modifier' : 'Saisir' }}
                            </button>
                        </td>
                        @endcan
                    </tr>
                    @can('update', $moratoire)
                    <tr x-show="open" x-cloak class="bg-slate-50/90 dark:bg-slate-900/40">
                        <td colspan="{{ $nbColonnes }}" class="px-3 py-3">
                            <form method="post"
                                  action="{{ route('moratoires.echeances.update', [$moratoire, $echeance]) }}"
                                  enctype="multipart/form-data"
                                  data-loading-text="Enregistrement..."
                                  class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-800 p-3 shadow-sm">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="_echeance_id" value="{{ $echeance->id }}">
                                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-2">
                                    Échéance n° {{ $echeance->numero }} — {{ number_format((float) $echeance->montant_echeance, 0, ',', ' ') }} FCFA
                                    @if(! $echeance->estPayee())
                                        <span class="text-amber-700 dark:text-amber-300">· justificatif obligatoire</span>
                                    @endif
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 items-end">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">N° chèque</label>
                                        <input type="text" name="numero_cheque" value="{{ old('_echeance_id') == $echeance->id ? old('numero_cheque') : $echeance->numero_cheque }}" placeholder="N° chèque" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-950 @error('numero_cheque') border-red-500 @enderror">
                                        @if(old('_echeance_id') == $echeance->id) @error('numero_cheque')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror @endif
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Banque</label>
                                        <input type="text" name="banque" value="{{ old('_echeance_id') == $echeance->id ? old('banque') : $echeance->banque }}" placeholder="Banque" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-950">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Date paiement</label>
                                        <input type="date" name="date_paiement" value="{{ old('_echeance_id') == $echeance->id ? old('date_paiement') : $echeance->date_paiement?->format('Y-m-d') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-950">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">OBS</label>
                                        <input type="text" name="observation" value="{{ old('_echeance_id') == $echeance->id ? old('observation') : $echeance->observation }}" placeholder="OBS" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-950">
                                    </div>
                                    <div>
                                        <button type="submit" data-loading-text="Enregistrement..."
                                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-emerald-700 transition-colors">
                                            Enregistrer
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1">
                                        Justificatifs (PDF / images){{ ! $echeance->estPayee() ? ' *' : '' }}
                                    </label>
                                    <input type="file" name="fichiers[]" accept=".pdf,.jpg,.jpeg,.png" multiple
                                           class="w-full text-xs text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-2.5 file:py-1 file:text-white file:font-semibold">
                                    @if(old('_echeance_id') == $echeance->id) @error('fichiers')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror @endif
                                    @if($echeance->suiviPaiement?->dossier_id)
                                        <p class="text-[10px] text-emerald-700 dark:text-emerald-300 mt-1">
                                            Justificatifs déjà déposés — vous pouvez en ajouter d’autres.
                                        </p>
                                    @endif
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endcan
                </tbody>
                @endforeach
            </table>
        </div>
    </div>

    <p class="text-sm">
        <x-table-action :href="route('moratoires.index')">← Retour à la liste</x-table-action>
    </p>
</div>
@endsection
