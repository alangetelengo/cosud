@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')

@section('page-title', 'Corbeille')
@section('page-title-info', 'Documents supprimés - restaurez ou supprimez définitivement')

@section('content')
<div id="corbeille-alerts">
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
    <span class="flex-1">{{ session('success') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 flex items-center justify-center text-lg font-bold">×</button>
</div>
@endif
@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-2">
    <span class="flex-1">{{ session('error') }}</span>
</div>
@endif
</div>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-5">
        <form action="{{ route('corbeille.index') }}" method="GET" class="flex gap-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Rechercher..." class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            <button type="submit" class="px-4 py-2.5 rounded-lg bg-emerald-600 text-white font-medium">Filtrer</button>
            <a href="{{ route('corbeille.index') }}" class="px-4 py-2.5 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-medium hover:bg-slate-50 dark:hover:bg-slate-700 inline-flex items-center gap-2 no-underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Rafraîchir
            </a>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/70 border-b border-slate-200 dark:border-slate-600">
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Document</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase hidden lg:table-cell">Dossier</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Supprimé le</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($documents as $doc)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800 dark:text-slate-200">{{ $doc->titre ?: $doc->nom_original }}</div>
                            @if($doc->titre)<div class="text-xs text-slate-500 truncate max-w-[200px]">{{ $doc->nom_original }}</div>@endif
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400 hidden lg:table-cell">
                            @if($doc->dossier)<span class="truncate max-w-[180px] block">{{ $doc->dossier->chemin_complet }}</span>@else—@endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-600">{{ $doc->typeDocument->libelle ?? '—' }}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400 text-sm">{{ $doc->date_suppression?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                @can('documents.delete')
                                <form action="{{ route('corbeille.restore', $doc) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-700/50 hover:bg-emerald-200 dark:hover:bg-emerald-800/40 transition-colors" title="Restaurer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                        Restaurer
                                    </button>
                                </form>
                                <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="button" onclick="flashAlert('Supprimer DÉFINITIVEMENT ce document ? Cette action est irréversible.', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 border border-red-200/60 dark:border-red-700/50 hover:bg-red-200 dark:hover:bg-red-800/40 transition-colors" title="Supprimer définitivement">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        Supprimer
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center text-slate-500 dark:text-slate-400">
                            La corbeille est vide.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600">{{ $documents->links() }}</div>
        @endif
    </div>
</div>
@endsection
