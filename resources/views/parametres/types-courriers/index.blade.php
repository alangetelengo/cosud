@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Types de courriers')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Types de courriers</span>
    </nav>
@endsection

@section('btn-create')
    <a href="{{ route('parametres.types-courriers.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
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
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200/80 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-4 shadow-sm">
        <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/50 flex items-center justify-center text-red-600 dark:text-red-400 font-bold">!</span>
        <span class="flex-1 font-medium">{{ session('error') }}</span>
        <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-red-200/50 dark:hover:bg-red-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
    </div>
    @endif

    <p class="text-sm text-slate-600 dark:text-slate-400 max-w-3xl">
        Le circuit de traitement d’un courrier arrivée est déterminé uniquement par son type. Associez ici chaque type au circuit métier qui doit s’appliquer.
    </p>

    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/80 dark:to-slate-800/80 border-b-2 border-slate-200 dark:border-slate-600">
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Code</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Libellé</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Circuit associé</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Statut</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($types as $type)
                    <tr class="group hover:bg-emerald-50/50 dark:hover:bg-slate-700/40 transition-colors duration-200">
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm font-medium text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 px-2.5 py-1 rounded-lg">{{ $type->code }}</span>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-800 dark:text-slate-200">{{ $type->libelle }}</td>
                        <td class="px-6 py-4">
                            @if($type->circuit)
                                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-200/50 dark:border-amber-800/50">
                                    {{ $type->circuit->libelle }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400 italic">Aucun — enregistrement simple</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold {{ $type->actif ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-700/50' : 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 border border-slate-300/50 dark:border-slate-500/30' }}">
                                {{ $type->actif ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('parametres.types-courriers.edit', $type) }}"
                                   class="relative inline-flex items-center justify-center min-w-[2.5rem] min-h-[2.5rem] p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200"
                                   title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form action="{{ route('parametres.types-courriers.destroy', $type) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer ce type de courrier ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2.5 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center gap-5">
                                <span class="flex items-center justify-center w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-700/80 text-slate-400 dark:text-slate-500">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                </span>
                                <div>
                                    <p class="font-bold text-slate-700 dark:text-slate-300 text-lg">Aucun type de courrier</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Créez un type ou exécutez les seeders</p>
                                </div>
                                <a href="{{ route('parametres.types-courriers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00b464] text-white text-sm font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Nouveau type
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($types->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/30">
            {{ $types->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
