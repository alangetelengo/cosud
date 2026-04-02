@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Journal d\'audit')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Journal d'audit</span>
    </nav>
@endsection

@section('content')
<div class="space-y-6">
    <p class="text-slate-600 dark:text-slate-400 text-sm max-w-2xl">
        Historique des actions effectuées dans l'application (documents, utilisateurs, structures, etc.).
    </p>

    {{-- Filtres --}}
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <form action="{{ route('parametres.audit.index') }}" method="GET" class="flex flex-wrap items-end gap-4">
            <div class="min-w-[120px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Module</label>
                <select name="module" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                    <option value="">Tous</option>
                    @foreach($modules as $m)
                    <option value="{{ $m }}" {{ request('module') == $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[140px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Utilisateur</label>
                <select name="user_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                    <option value="">Tous</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ (string) request('user_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[120px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Action (recherche)</label>
                <input type="text" name="action" value="{{ request('action') }}" placeholder="ex. document.depot" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
            </div>
            <div class="min-w-[130px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Du</label>
                <input type="date" name="date_debut" value="{{ request('date_debut') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
            </div>
            <div class="min-w-[130px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Au</label>
                <input type="date" name="date_fin" value="{{ request('date_fin') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Filtrer
            </button>
            <a href="{{ route('parametres.audit.index') }}" class="px-5 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 text-sm inline-flex items-center gap-2 no-underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Rafraîchir
            </a>
        </form>
    </div>

    {{-- Tableau --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b-2 border-slate-200 dark:border-slate-600">
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Date/Heure</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Action</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Module</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Utilisateur</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase hidden md:table-cell">Cible</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase hidden lg:table-cell">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($entries as $e)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                        <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                            {{ $e->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-6 py-3">
                            <span class="font-mono text-xs px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">{{ $e->action }}</span>
                        </td>
                        <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $e->module ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm">
                            {{ $e->user?->name ?? 'Système' }}
                        </td>
                        <td class="px-6 py-3 text-sm hidden md:table-cell">
                            @if($e->document)
                            <a href="{{ route('documents.edit', $e->document) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">{{ $e->document->nom_original }}</a>
                            @elseif($e->dossier)
                            <a href="{{ route('dossiers.show', $e->dossier) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">{{ $e->dossier->nom }}</a>
                            @else
                            —
                            @endif
                        </td>
                        <td class="px-6 py-3 text-xs text-slate-500 dark:text-slate-500 font-mono hidden lg:table-cell">{{ $e->adresse_ip ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">Aucune entrée dans le journal.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/30">
            {{ $entries->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
