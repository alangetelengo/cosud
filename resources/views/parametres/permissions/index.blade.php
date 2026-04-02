@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Permissions')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.roles.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Rôles</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Permissions</span>
    </nav>
@endsection

@section('btn-create')
    <a href="{{ route('parametres.permissions.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouvelle permission
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

    <p class="text-slate-600 dark:text-slate-400 text-sm">Référentiel des permissions. Format conseillé : <code class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-600 text-sm font-mono">module.action</code> (ex. documents.view, dossiers.create).</p>

    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/80 dark:to-slate-800/80 border-b-2 border-slate-200 dark:border-slate-600">
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Permission</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Rôles associés</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($permissions as $p)
                    <tr class="group hover:bg-emerald-50/50 dark:hover:bg-slate-700/40 transition-colors duration-200">
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm font-medium text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 px-2.5 py-1 rounded-lg">{{ $p->name }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $p->roles_count }} rôle(s)</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('parametres.permissions.edit', $p) }}"
                                   x-data="{ loading: false }"
                                   @click.prevent="if(!loading){ loading=true; window.location.href=$el.href }"
                                   class="relative inline-flex items-center justify-center min-w-[2.5rem] min-h-[2.5rem] p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200"
                                   :class="{ 'pointer-events-none opacity-70': loading }"
                                   title="Modifier">
                                    <span x-show="!loading"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                                    <span x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center"><span class="link-modifier-spinner"></span></span>
                                </a>
                                <form action="{{ route('parametres.permissions.destroy', $p) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="flashAlert('Supprimer cette permission la retirera de tous les rôles. Continuer ?', this.closest('form'), {icon:'⚠️', danger:true, confirmText:'Supprimer'})" class="p-2.5 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center gap-5">
                                <span class="flex items-center justify-center w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-700/80 text-slate-400 dark:text-slate-500">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </span>
                                <div>
                                    <p class="font-bold text-slate-700 dark:text-slate-300 text-lg">Aucune permission</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Créez une permission ou exécutez les seeders</p>
                                </div>
                                <a href="{{ route('parametres.permissions.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00b464] text-white text-sm font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Nouvelle permission
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($permissions->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/30">
            {{ $permissions->links() }}
        </div>
        @endif
    </div>

    <div class="flex gap-4">
        <a href="{{ route('parametres.roles.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">
            ← Retour aux rôles
        </a>
    </div>
</div>
@endsection
