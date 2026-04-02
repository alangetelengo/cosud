@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Mon tableau de bord')
@section('page-title-info', 'Vos documents et dossiers')

@section('content')
<div class="space-y-8">
    {{-- Hero personnel --}}
    <div class="relative overflow-hidden rounded-2xl bg-slate-800 bg-gradient-to-br from-emerald-600 via-teal-700 to-cyan-800 p-6 sm:p-8 shadow-xl">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.06\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-50"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div>
                <p class="text-slate-500 text-sm font-medium uppercase tracking-wider">Bienvenue</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">Mon espace documentaire</h2>
                <p class="text-slate-600 mt-2">{{ $nbMesDocuments }} document(s) · {{ $nbDossiersFavoris }} dossier(s) favori(s)</p>
            </div>
            @can('documents.create')
            <a href="{{ route('documents.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-emerald-700 font-semibold hover:bg-emerald-50 transition-all shadow-lg no-underline">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Déposer un document
            </a>
            @endcan
        </div>
    </div>

    {{-- Stats personnelles --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @can('documents.view')
        <a href="{{ route('documents.index') }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-emerald-500/10 group-hover:bg-emerald-500/20 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Mes documents</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbMesDocuments }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Créés ou partagés</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">📄</span>
            </div>
        </a>
        @endcan
        @can('dossiers.view')
        <a href="{{ route('dossiers.index', ['filtre' => 'favoris']) }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-amber-500/10 group-hover:bg-amber-500/20 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Favoris</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbDossiersFavoris }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Dossiers épinglés</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">⭐</span>
            </div>
        </a>
        @endcan
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Mes derniers documents --}}
        @can('documents.view')
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-200/80 dark:bg-emerald-700/60 flex items-center justify-center text-sm">📄</span>
                    Mes derniers documents
                </h2>
                <a href="{{ route('documents.index') }}" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors no-underline">Voir tout →</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700 max-h-[360px] overflow-y-auto">
                @forelse($documentsRecents as $doc)
                <a href="{{ route('documents.edit', $doc) }}" class="flex items-center gap-4 px-6 py-3 hover:bg-emerald-50/30 dark:hover:bg-slate-700/30 transition-colors no-underline group">
                    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-800/60 transition-colors">📄</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-800 dark:text-slate-100 truncate group-hover:text-emerald-700 dark:group-hover:text-emerald-300">{{ $doc->nom_original ?: $doc->titre ?: 'Sans titre' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $doc->dossier?->nom ?? '—' }} · {{ $doc->created_at->format('d/m/Y') }}</p>
                    </div>
                    <span class="flex-shrink-0 text-slate-400 group-hover:text-emerald-500 transition-colors">→</span>
                </a>
                @empty
                <div class="px-6 py-16 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700/50 text-3xl mb-4">📄</span>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Aucun document</p>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Commencez par déposer un document</p>
                    @can('documents.create')
                    <a href="{{ route('documents.create') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition-colors no-underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Déposer un document
                    </a>
                    @endcan
                </div>
                @endforelse
            </div>
        </div>
        @endcan

        {{-- Mes dossiers récents --}}
        @can('dossiers.view')
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-sky-50 to-indigo-50 dark:from-sky-900/20 dark:to-indigo-900/20 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-sky-200/80 dark:bg-sky-700/60 flex items-center justify-center text-sm">📂</span>
                    Mes dossiers
                </h2>
                <a href="{{ route('dossiers.index') }}" class="text-sm font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors no-underline">Voir tout →</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700 max-h-[360px] overflow-y-auto">
                @forelse($dossiersRecents as $d)
                <a href="{{ route('dossiers.show', $d) }}" class="flex items-center gap-4 px-6 py-3 hover:bg-sky-50/30 dark:hover:bg-slate-700/30 transition-colors no-underline group">
                    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center text-sky-600 dark:text-sky-400 group-hover:bg-sky-200 dark:group-hover:bg-sky-800/60 transition-colors">📂</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-800 dark:text-slate-100 truncate group-hover:text-sky-700 dark:group-hover:text-sky-300">{{ $d->nom }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Modifié le {{ $d->updated_at->format('d/m/Y') }}</p>
                    </div>
                    <span class="flex-shrink-0 text-slate-400 group-hover:text-sky-500 transition-colors">→</span>
                </a>
                @empty
                <div class="px-6 py-16 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700/50 text-3xl mb-4">📂</span>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Aucun dossier</p>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Parcourez le plan de classement</p>
                    <a href="{{ route('dossiers.index') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-sky-600 text-white text-sm font-semibold hover:bg-sky-700 transition-colors no-underline">
                        Voir les dossiers →
                    </a>
                </div>
                @endforelse
            </div>
        </div>
        @endcan
    </div>

    {{-- Message de bienvenue si peu de contenu --}}
    @if($documentsRecents->isEmpty() && $dossiersRecents->isEmpty())
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-800/80 p-8 shadow-lg border border-slate-200 dark:border-slate-700">
        <div class="absolute top-0 right-0 w-64 h-64 -mr-32 -mt-32 rounded-full bg-emerald-500/10"></div>
        <div class="relative">
            <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                <span class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">📚</span>
                Bienvenue sur GED
            </h2>
            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                GED est le système de <strong>Gestion Électronique des Documents</strong>. Utilisez le menu à gauche pour naviguer : Documents, Dossiers, Recherche, etc.
            </p>
            <div class="flex flex-wrap gap-4 mt-6">
                @can('documents.create')
                <a href="{{ route('documents.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition-colors no-underline text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Déposer un document
                </a>
                @endcan
                @can('dossiers.view')
                <a href="{{ route('dossiers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline text-sm">
                    Parcourir les dossiers →
                </a>
                @endcan
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
