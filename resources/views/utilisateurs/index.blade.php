@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Utilisateurs')
@section('page-title-info', 'Gestion des utilisateurs et des rôles')

@section('btn-create')
    <div class="flex items-center gap-2 flex-wrap">
        @can('utilisateurs.view')
        <a href="{{ route('utilisateurs.export', request()->query()) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Exporter CSV
        </a>
        @endcan
        @can('utilisateurs.create')
        <a href="{{ route('utilisateurs.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvel utilisateur
        </a>
        @endcan
    </div>
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

{{-- Barre de filtres --}}
<div class="mb-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 shadow-sm">
    <form action="{{ route('utilisateurs.index') }}" method="GET" class="flex flex-wrap items-end gap-4" id="filter-utilisateurs-form">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="per_page" value="{{ request('per_page', 15) }}">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Recherche</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 z-10 text-slate-400 pointer-events-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, email ou téléphone SMS..."
                    class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-[#00b464]/25 focus:border-[#00b464] transition-all placeholder:text-slate-400">
            </div>
        </div>
        <div class="w-48">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Rôle</label>
            <select name="role" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-[#00b464]/25 focus:border-[#00b464] transition-all">
                <option value="">Tous les rôles</option>
                @foreach($roles as $r)
                <option value="{{ $r->name }}" {{ request('role') == $r->name ? 'selected' : '' }}>{{ ucfirst($r->name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-48">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Structure</label>
            <select name="structure_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-[#00b464]/25 focus:border-[#00b464] transition-all">
                <option value="">Toutes</option>
                @foreach($structures as $s)
                <option value="{{ $s->id }}" {{ (string) request('structure_id') === (string) $s->id ? 'selected' : '' }}>{{ $s->nom }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-36">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Statut</label>
            <select name="actif" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-[#00b464]/25 focus:border-[#00b464] transition-all">
                <option value="">Tous</option>
                <option value="1" {{ (string) request('actif') === '1' ? 'selected' : '' }}>Actifs</option>
                <option value="0" {{ (string) request('actif') === '0' ? 'selected' : '' }}>Inactifs</option>
            </select>
        </div>
        <button type="submit" class="px-5 py-3 rounded-xl bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all duration-200 text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            Filtrer
        </button>
        <a href="{{ route('utilisateurs.index') }}" class="px-5 py-3 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all text-sm inline-flex items-center gap-2 no-underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Rafraîchir
        </a>
    </form>
</div>

{{-- Actions bulk 2FA --}}
@can('utilisateurs.edit')
<div id="bulk2faActions" class="mb-4 flex items-center gap-2" style="display: none;">
    <form method="POST" action="{{ route('utilisateurs.bulk-2fa') }}" id="usersBulk2faForm" class="inline-flex items-center gap-2">
        @csrf
        <input type="hidden" name="action" id="bulk2faActionInput">
        <button type="button" onclick="bulk2faSubmit('enable')" class="px-4 py-2 rounded-lg bg-[#00b464] text-white text-sm font-semibold hover:bg-[#00a055] shadow-sm transition-all">
            Activer 2FA
        </button>
        <button type="button" onclick="bulk2faSubmit('disable')" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 shadow-sm transition-all">
            Désactiver 2FA
        </button>
    </form>
    <span id="bulk2faCount" class="text-sm text-slate-500 dark:text-slate-400"></span>
</div>
@endcan

{{-- Tableau des utilisateurs --}}
<div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/80 dark:to-slate-800/80 border-b-2 border-slate-200 dark:border-slate-600">
                    @can('utilisateurs.edit')
                    <th class="px-4 py-4 w-10"><input type="checkbox" id="selectAll2fa" title="Tout sélectionner" class="rounded border-slate-300 dark:border-slate-600 text-[#00b464] focus:ring-[#00b464]/25"></th>
                    @endcan
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Utilisateur</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest hidden lg:table-cell">Tél. SMS</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest hidden sm:table-cell">Rôle</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest hidden md:table-cell">Structure</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Actif</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">2FA</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest hidden md:table-cell">Inscrit le</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                @forelse($users as $user)
                <tr class="group hover:bg-emerald-50/50 dark:hover:bg-slate-700/40 transition-colors duration-200">
                    @can('utilisateurs.edit')
                    <td class="px-4 py-4"><input type="checkbox" form="usersBulk2faForm" name="user_ids[]" value="{{ $user->id }}" class="user-2fa-cb rounded border-slate-300 dark:border-slate-600 text-[#00b464] focus:ring-[#00b464]/25"></td>
                    @endcan
                    <td class="px-6 py-4">
                        <a href="{{ route('utilisateurs.show', $user) }}" class="flex items-center gap-4 group/link">
                            <div class="relative">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=00b464&color=fff&size=44" alt="" class="w-11 h-11 rounded-xl flex-shrink-0 ring-2 ring-slate-100 dark:ring-slate-600 group-hover/link:ring-[#00b464]/40 group-hover/link:scale-105 transition-all duration-200">
                            </div>
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100 group-hover/link:text-[#00b464] transition-colors">{{ $user->name }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                            </div>
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 hidden lg:table-cell font-mono tabular-nums">
                        @if($user->telephone)
                            +{{ $user->telephone }}
                        @else
                            <span class="text-slate-400 dark:text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 hidden sm:table-cell">
                        @php
                            $roleName = $user->roles->first()?->name ?? null;
                            $roleClass = $roleName === 'admin'
                                ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 border border-amber-200/50 dark:border-amber-700/50'
                                : 'bg-slate-100 dark:bg-slate-600/80 text-slate-700 dark:text-slate-300 border border-slate-200/50 dark:border-slate-500/30';
                        @endphp
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold {{ $roleClass }}">
                            {{ ucfirst($roleName ?? '—') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 hidden md:table-cell">
                        {{ $user->structure?->nom ?? '—' }}
                    </td>
                    <td class="px-6 py-4">
                        @php $actif = $user->actif ?? true; @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold {{ $actif ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-600/80 text-slate-600 dark:text-slate-400' }}">
                            {{ $actif ? 'Oui' : 'Non' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold {{ $user->hasTwoFactorEnabled() ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-600/80 text-slate-600 dark:text-slate-400' }}">
                            {{ $user->hasTwoFactorEnabled() ? 'Oui' : 'Non' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 hidden md:table-cell font-medium">
                        {{ $user->created_at->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-1">
                            @can('utilisateurs.view')
                            <a href="{{ route('utilisateurs.show', $user) }}" class="p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200" title="Voir">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            @endcan
                            @can('utilisateurs.edit')
                            <a href="{{ route('utilisateurs.edit', $user) }}#structures" class="p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200" title="Structures & fonctions (édition utilisateur)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </a>
                            <a href="{{ route('utilisateurs.edit', $user) }}"
                               x-data="{ loading: false }"
                               @click.prevent="if(!loading){ loading=true; window.location.href=$el.href }"
                               class="relative inline-flex items-center justify-center min-w-[2.5rem] min-h-[2.5rem] p-2.5 rounded-xl text-slate-400 hover:text-[#00b464] hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all duration-200"
                               :class="{ 'pointer-events-none opacity-70': loading }"
                               title="Modifier">
                                <span x-show="!loading"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                                <span x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center"><span class="link-modifier-spinner"></span></span>
                            </a>
                            @endcan
                            @can('utilisateurs.delete')
                            @if($user->id !== auth()->id())
                            <form action="{{ route('utilisateurs.destroy', $user) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" onclick="flashAlert('Supprimer définitivement cet utilisateur ?', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})" class="p-2.5 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200" title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ auth()->user()->can('utilisateurs.edit') ? 9 : 8 }}" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center gap-5">
                            <span class="flex items-center justify-center w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-700/80 text-slate-400 dark:text-slate-500">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </span>
                            <div>
                                <p class="font-bold text-slate-700 dark:text-slate-300 text-lg">Aucun utilisateur</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Commencez par créer un nouvel utilisateur</p>
                            </div>
                            @can('utilisateurs.create')
                            <a href="{{ route('utilisateurs.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00b464] text-white text-sm font-semibold hover:bg-[#00a055] shadow-sm hover:shadow transition-all no-underline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Nouvel utilisateur
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{-- Barre de pagination --}}
    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/30 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Affichage de <span class="font-semibold">{{ $users->firstItem() ?? 0 }}</span> à <span class="font-semibold">{{ $users->lastItem() ?? 0 }}</span> sur <span class="font-semibold">{{ $users->total() }}</span> utilisateur(s)
            </p>
            <form action="{{ route('utilisateurs.index') }}" method="GET" class="flex items-center gap-2" id="per-page-form">
                <input type="hidden" name="page" value="1">
                @foreach(request()->only(['q', 'role', 'structure_id', 'actif']) as $k => $v)
                @if($v !== null && $v !== '')
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
                @endforeach
                <label for="per_page" class="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">Par page :</label>
                <select name="per_page" id="per_page" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-[#00b464]/25 focus:border-[#00b464]">
                    @foreach([10, 15, 25, 50, 100] as $n)
                    <option value="{{ $n }}" {{ (int) request('per_page', 15) === $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @if($users->hasPages())
        <div class="flex items-center gap-2">
            {{ $users->appends(request()->query())->links('vendor.pagination.links-only') }}
        </div>
        @endif
    </div>
</div>

@can('utilisateurs.edit')
<script>
(function() {
    function toggleBulk2faButtons() {
        var checked = document.querySelectorAll('.user-2fa-cb:checked');
        var actions = document.getElementById('bulk2faActions');
        var countEl = document.getElementById('bulk2faCount');
        if (checked.length > 0) {
            actions.style.display = 'flex';
            countEl.textContent = checked.length + ' utilisateur(s) sélectionné(s)';
        } else {
            actions.style.display = 'none';
        }
    }
    window.bulk2faSubmit = function(action) {
        var checked = document.querySelectorAll('.user-2fa-cb:checked');
        if (checked.length === 0) {
            flashAlert('Veuillez sélectionner au moins un utilisateur.', null, { noCancel: true });
            return;
        }
        var form = document.getElementById('usersBulk2faForm');
        form.querySelector('#bulk2faActionInput').value = action;
        var n = checked.length;
        var msg = action === 'enable'
            ? 'Activer la 2FA pour ' + n + ' utilisateur(s) ? Un email contenant le QR code sera envoyé à chaque utilisateur.'
            : 'Désactiver la 2FA pour ' + n + ' utilisateur(s) ?';
        var opts = action === 'enable'
            ? { confirmText: 'Activer', icon: '🔐', danger: false }
            : { confirmText: 'Désactiver', icon: '🔓', danger: true };
        flashAlert(msg, form, opts);
    };
    document.getElementById('selectAll2fa')?.addEventListener('change', function() {
        document.querySelectorAll('.user-2fa-cb').forEach(function(cb) { cb.checked = this.checked; }, this);
        toggleBulk2faButtons();
    });
    document.querySelectorAll('.user-2fa-cb').forEach(function(cb) {
        cb.addEventListener('change', toggleBulk2faButtons);
    });
})();
</script>
@endcan
@endsection
