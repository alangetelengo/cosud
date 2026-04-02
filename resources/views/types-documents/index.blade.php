@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Types de documents')
@section('page-title-info', 'Gérer les catégories de documents')

@section('btn-create')
    @can('types-documents.create')
    <a href="{{ route('types-documents.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouveau type
    </a>
    @endcan
@endsection

@section('content')
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-5 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200/80 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-4 shadow-sm">
    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold">✓</span>
    <span class="flex-1 font-medium">{{ session('success') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
</div>
@endif
@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-5 p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200/80 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-4 shadow-sm">
    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/50 flex items-center justify-center text-red-600 dark:text-red-400 font-bold">!</span>
    <span class="flex-1 font-medium">{{ session('error') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-red-200/50 dark:hover:bg-red-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
</div>
@endif

{{-- Tableau des types --}}
<div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/80 dark:to-slate-800/80 border-b-2 border-slate-200 dark:border-slate-600">
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Code</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Libellé</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Extension</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Conservation</th>
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
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium">.{{ $type->extension_defaut }}</span>
                    </td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400 font-medium">{{ $type->libelleDureeConservation() }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold {{ $type->actif ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-700/50' : 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 border border-slate-300/50 dark:border-slate-500/30' }}">
                            {{ $type->actif ? 'Actif' : 'Inactif' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-1">
                            @can('types-documents.edit')
                            <a href="{{ route('types-documents.edit', $type) }}"
                               x-data="{ loading: false }"
                               @click.prevent="if(!loading){ loading=true; window.location.href=$el.href }"
                               class="relative inline-flex items-center justify-center min-w-[2.5rem] min-h-[2.5rem] p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200"
                               :class="{ 'pointer-events-none opacity-70': loading }"
                               title="Modifier">
                                <span x-show="!loading"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                                <span x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center"><span class="link-modifier-spinner"></span></span>
                            </a>
                            @endcan
                            @can('types-documents.delete')
                            <form action="{{ route('types-documents.destroy', $type) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" onclick="flashAlert('Supprimer ce type de document ?', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})" class="p-2.5 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200" title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center gap-5">
                            <span class="flex items-center justify-center w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-700/80 text-slate-400 dark:text-slate-500">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </span>
                            <div>
                                <p class="font-bold text-slate-700 dark:text-slate-300 text-lg">Aucun type de document</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Créez un premier type pour commencer</p>
                            </div>
                            @can('types-documents.create')
                            <a href="{{ route('types-documents.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00b464] text-white text-sm font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Nouveau type
                            </a>
                            @endcan
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
@endsection
