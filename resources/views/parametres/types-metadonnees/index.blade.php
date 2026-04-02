@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Types de métadonnées')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Types de métadonnées</span>
    </nav>
@endsection

@section('btn-create')
    <a href="{{ route('parametres.types-metadonnees.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouveau type
    </a>
@endsection

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200/80 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-4 shadow-sm">
        <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold">✓</span>
        <span class="flex-1 font-medium">{{ session('success') }}</span>
        <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
    </div>
    @endif

    <p class="text-slate-600 dark:text-slate-400 text-sm">Types de métadonnées utilisés pour l'extraction automatique des documents (PDF).</p>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b-2 border-slate-200 dark:border-slate-600">
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Code</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Libellé</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Statut</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @foreach($types as $t)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                        <td class="px-6 py-4 font-mono text-sm text-slate-800 dark:text-slate-200">{{ $t->code }}</td>
                        <td class="px-6 py-4 font-medium text-slate-800 dark:text-slate-200">{{ $t->libelle }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $t->type_valeur }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold {{ $t->actif ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300' }}">
                                {{ $t->actif ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('parametres.types-metadonnees.edit', $t) }}"
                                   x-data="{ loading: false }"
                                   @click.prevent="if(!loading){ loading=true; window.location.href=$el.href }"
                                   class="relative inline-flex items-center justify-center min-w-[2.5rem] min-h-[2.5rem] p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200"
                                   :class="{ 'pointer-events-none opacity-70': loading }"
                                   title="Modifier">
                                    <span x-show="!loading"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                                    <span x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center"><span class="link-modifier-spinner"></span></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($types->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600">{{ $types->links() }}</div>
        @endif
    </div>
</div>
@endsection
