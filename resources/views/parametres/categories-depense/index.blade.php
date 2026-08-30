@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Catégories de dépense')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Catégories de dépense</span>
    </nav>
@endsection

@section('btn-create')
    <a href="{{ route('parametres.categories-depense.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouvelle catégorie
    </a>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
    <div class="px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Liste des catégories</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $categories->total() }} catégorie{{ $categories->total() > 1 ? 's' : '' }}
                — utilisées dans le suivi des dépenses
            </p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/80 dark:to-slate-800/80 border-b-2 border-slate-200 dark:border-slate-600">
                    <th class="px-5 sm:px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Ordre</th>
                    <th class="px-5 sm:px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Code</th>
                    <th class="px-5 sm:px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Libellé</th>
                    <th class="px-5 sm:px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Statut</th>
                    <th class="px-5 sm:px-6 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                @forelse($categories as $categorie)
                <tr class="group transition-colors {{ $loop->odd ? 'bg-white dark:bg-slate-800' : 'bg-slate-50/60 dark:bg-slate-900/20' }} hover:bg-emerald-50/50 dark:hover:bg-emerald-900/15">
                    <td class="px-5 sm:px-6 py-3.5">
                        <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/70 text-slate-600 dark:text-slate-300 text-xs font-semibold tabular-nums ring-1 ring-slate-200/80 dark:ring-slate-600/50">
                            {{ $categorie->ordre }}
                        </span>
                    </td>
                    <td class="px-5 sm:px-6 py-3.5">
                        <code class="inline-flex items-center font-mono text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700/70 px-2.5 py-1 rounded-lg ring-1 ring-slate-200/80 dark:ring-slate-600/60">
                            {{ $categorie->code }}
                        </code>
                    </td>
                    <td class="px-5 sm:px-6 py-3.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $categorie->libelle }}</span>
                            @if($categorie->est_systeme)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-50 text-amber-800 border border-amber-200/80 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-700/50">
                                    Système
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 sm:px-6 py-3.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $categorie->actif
                            ? 'bg-emerald-50 text-emerald-800 border-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-800/50'
                            : 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-700/40 dark:text-slate-300 dark:border-slate-600' }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                            {{ $categorie->actif ? 'Actif' : 'Inactif' }}
                        </span>
                    </td>
                    <td class="px-5 sm:px-6 py-3.5">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('parametres.categories-depense.edit', $categorie) }}"
                               class="inline-flex items-center justify-center min-w-[2.35rem] min-h-[2.35rem] p-2 rounded-xl text-sky-600 dark:text-sky-400 bg-sky-50/80 dark:bg-sky-900/20 hover:bg-sky-100 dark:hover:bg-sky-900/40 border border-sky-100/80 dark:border-sky-800/40 shadow-sm transition"
                               title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                <span class="sr-only">Modifier</span>
                            </a>
                            @unless($categorie->est_systeme)
                            <form action="{{ route('parametres.categories-depense.destroy', $categorie) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button"
                                        onclick="flashAlert('Supprimer cette catégorie ?', this.closest('form'), {danger:true, confirmText:'Supprimer'})"
                                        class="inline-flex items-center justify-center min-w-[2.35rem] min-h-[2.35rem] p-2 rounded-xl text-rose-600 dark:text-rose-400 bg-rose-50/80 dark:bg-rose-900/20 hover:bg-rose-100 dark:hover:bg-rose-900/40 border border-rose-100/80 dark:border-rose-800/40 shadow-sm transition"
                                        title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <span class="sr-only">Supprimer</span>
                                </button>
                            </form>
                            @else
                            <span class="inline-flex items-center justify-center min-w-[2.35rem] min-h-[2.35rem] p-2 rounded-xl text-slate-300 dark:text-slate-600 border border-transparent" title="Catégorie système — suppression impossible" aria-hidden="true">
                                <svg class="w-4 h-4 opacity-40" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </span>
                            @endunless
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center gap-4">
                            <span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700/80 text-slate-400">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                            </span>
                            <div>
                                <p class="font-semibold text-slate-700 dark:text-slate-200">Aucune catégorie</p>
                                <p class="text-xs text-slate-500 mt-1">Créez une catégorie ou exécutez le seeder dédié.</p>
                            </div>
                            <a href="{{ route('parametres.categories-depense.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00b464] text-white text-sm font-semibold hover:bg-[#00a055] shadow-sm no-underline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Nouvelle catégorie
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($categories->hasPages())
    <div class="px-5 sm:px-6 py-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
        {{ $categories->links() }}
    </div>
    @endif
</div>
@endsection
