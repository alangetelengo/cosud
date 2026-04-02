@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Rôles et permissions')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Rôles</span>
    </nav>
@endsection

@section('btn-create')
    <div class="flex items-center gap-3">
        <a href="{{ route('parametres.permissions.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
            Référentiel des permissions
        </a>
        <a href="{{ route('parametres.roles.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau rôle
        </a>
    </div>
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

    <p class="text-slate-600 dark:text-slate-400 text-sm">Gérez les permissions associées à chaque rôle. Les utilisateurs héritent des permissions de leur rôle.</p>

    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/80 dark:to-slate-800/80 border-b-2 border-slate-200 dark:border-slate-600">
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Rôle</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Utilisateurs</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Permissions</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($roles as $r)
                    <tr class="group hover:bg-emerald-50/50 dark:hover:bg-slate-700/40 transition-colors duration-200">
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold {{ $r->name === 'admin' ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 border border-amber-200/50 dark:border-amber-700/50' : ($r->name === 'dg' ? 'bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-200 border border-sky-200/50 dark:border-sky-700/50' : 'bg-slate-100 dark:bg-slate-600/80 text-slate-700 dark:text-slate-300 border border-slate-300/50 dark:border-slate-500/30') }}">
                                {{ ucfirst($r->name) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $r->users_count }} utilisateur(s)</td>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $r->permissions_count }} permission(s)</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('parametres.roles.edit', $r) }}"
                                   x-data="{ loading: false }"
                                   @click.prevent="if(!loading){ loading=true; window.location.href=$el.href }"
                                   class="relative inline-flex items-center justify-center min-w-[2.5rem] min-h-[2.5rem] p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200"
                                   :class="{ 'pointer-events-none opacity-70': loading }"
                                   title="Modifier">
                                    <span x-show="!loading"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                                    <span x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center"><span class="link-modifier-spinner"></span></span>
                                </a>
                                @if($r->name !== 'admin')
                                <form action="{{ route('parametres.roles.destroy', $r) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="flashAlert('Supprimer ce rôle ? Les utilisateurs concernés devront être réassignés.', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})" class="p-2.5 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200 {{ $r->users_count > 0 ? 'opacity-50 cursor-not-allowed' : '' }}" @if($r->users_count > 0) disabled title="{{ $r->users_count }} utilisateur(s) ont ce rôle" @else title="Supprimer" @endif>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center gap-5">
                                <span class="flex items-center justify-center w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-700/80 text-slate-400 dark:text-slate-500">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                </span>
                                <div>
                                    <p class="font-bold text-slate-700 dark:text-slate-300 text-lg">Aucun rôle</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Créez un rôle ou exécutez les seeders</p>
                                </div>
                                <a href="{{ route('parametres.roles.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00b464] text-white text-sm font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Nouveau rôle
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($roles->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/30">
            {{ $roles->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
