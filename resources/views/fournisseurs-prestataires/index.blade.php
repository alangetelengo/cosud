@extends('layouts.app')
@use('App\Support\ReturnUrl')
@use('App\Models\FournisseurPrestataire')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Fournisseurs ou prestataires')
@section('page-title-info', $fiches->total().' fiche(s)')

@section('btn-create')
    <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
        <a href="{{ route('fournisseurs-prestataires.print', array_filter($filtres)) }}"
           class="inline-flex items-center justify-center gap-2 min-h-[2.5rem] px-4 py-2 rounded-lg border-2 border-emerald-600 text-emerald-800 dark:text-emerald-200 text-sm font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/30 shadow-sm transition-all no-underline">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-4 0v4m0-4H8v4"/></svg>
            Imprimer le tableau
        </a>
        @can('create', FournisseurPrestataire::class)
        <a href="{{ route('fournisseurs-prestataires.create', ReturnUrl::forRoute()) }}"
           class="inline-flex items-center justify-center gap-2 min-h-[2.5rem] px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-all no-underline">
            Nouvelle fiche
        </a>
        @endcan
    </div>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4">
    <form method="get" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem] flex-1">
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Recherche</label>
            <input type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Nom, type de contrat, observation…"
                   class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Type</label>
            <select name="type" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm dark:bg-slate-900">
                <option value="">Tous</option>
                <option value="fournisseur" @selected($filtres['type'] === 'fournisseur')>Fournisseur</option>
                <option value="prestataire" @selected($filtres['type'] === 'prestataire')>Prestataire</option>
                <option value="partenaire" @selected($filtres['type'] === 'partenaire')>Partenaire</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Contrat</label>
            <select name="contrat" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm dark:bg-slate-900">
                <option value="">Tous</option>
                <option value="oui" @selected($filtres['contrat'] === 'oui')>Oui</option>
                <option value="non" @selected($filtres['contrat'] === 'non')>Non</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Dossier fiscal</label>
            <select name="fiscal" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm dark:bg-slate-900">
                <option value="">Tous</option>
                <option value="oui" @selected($filtres['fiscal'] === 'oui')>Oui</option>
                <option value="non" @selected($filtres['fiscal'] === 'non')>Non</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Actif</label>
            <select name="actif" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm dark:bg-slate-900">
                <option value="oui" @selected($filtres['actif'] === 'oui')>Actifs</option>
                <option value="non" @selected($filtres['actif'] === 'non')>Inactifs</option>
                <option value="tous" @selected($filtres['actif'] === 'tous')>Tous</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-1.5 rounded-lg bg-slate-800 dark:bg-slate-700 text-white text-sm font-semibold">Filtrer</button>
    </form>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
                        <th class="px-4 py-3 font-semibold">N°</th>
                        <th class="px-4 py-3 font-semibold">Nom</th>
                        <th class="px-4 py-3 font-semibold">Type</th>
                        <th class="px-4 py-3 font-semibold">Type de contrats</th>
                        <th class="px-4 py-3 font-semibold">Contrat</th>
                        <th class="px-4 py-3 font-semibold">Dossier fiscal</th>
                        <th class="px-4 py-3 font-semibold">Observation</th>
                        <th class="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($fiches as $fiche)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3 tabular-nums text-slate-500">{{ $fiches->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ $fiche->nom }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $fiche->libelleType() }}</td>
                        <td class="px-4 py-3 max-w-xs text-slate-700 dark:text-slate-200">
                            <span class="line-clamp-2" title="{{ $fiche->type_contrat }}">{{ $fiche->type_contrat ?: '—' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $fiche->a_contrat ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">
                                {{ $fiche->libelleContratCourt() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $fiche->a_dossier_fiscal ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">
                                {{ $fiche->libelleDossierFiscalCourt() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 max-w-[12rem] text-slate-500 truncate" title="{{ $fiche->observation }}">{{ $fiche->observation ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex gap-1">
                                <a href="{{ route('fournisseurs-prestataires.show', ReturnUrl::forRoute($fiche)) }}" class="px-2.5 py-1 rounded-lg border text-xs font-semibold no-underline text-sky-700 border-sky-200 hover:bg-sky-50">Ouvrir</a>
                                @can('update', $fiche)
                                <a href="{{ route('fournisseurs-prestataires.edit', ReturnUrl::forRoute($fiche)) }}" class="px-2.5 py-1 rounded-lg border text-xs font-semibold no-underline text-slate-700 border-slate-200 hover:bg-slate-50">Modifier</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-slate-500">Aucun fournisseur ou prestataire pour ces filtres.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($fiches->hasPages())
        {{ $fiches->links() }}
    @endif
</div>
@endsection
