@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Tableau de bord — ' . $structure->nom)
@section('page-title-info')
    @if($structuresDisponibles->count() > 1)
    <div class="flex flex-wrap items-center gap-2 mt-2">
        <span class="text-slate-500 dark:text-slate-400 text-sm">Structure :</span>
        <select onchange="if(this.value) window.location.href='{{ route('home') }}?structure_id='+this.value" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm font-medium px-3 py-1.5 focus:ring-2 focus:ring-[#00b464]/25">
            @foreach($structuresDisponibles as $s)
            <option value="{{ $s->id }}" {{ $s->id === $structure->id ? 'selected' : '' }}>{{ $s->nom }}</option>
            @endforeach
        </select>
    </div>
    @else
    Vue d'ensemble de la structure
    @endif
@endsection

@section('content')
<div class="space-y-8">
    {{-- Hero structure --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 p-6 sm:p-8 shadow-xl">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.06\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-50"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div>
                <p class="text-white text-sm font-bold uppercase tracking-wider drop-shadow-sm">Structure</p>
                <h2 class="text-xl sm:text-2xl font-bold text-white mt-1 drop-shadow-sm">{{ $structure->nom }}</h2>
                <p class="text-white font-bold mt-2 drop-shadow-sm">{{ $nbDocuments }} document(s) · {{ $nbDossiers }} dossier(s)</p>
            </div>
            @if($documentsEnAttente->isNotEmpty())
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/20 backdrop-blur-sm">
                    <span class="text-2xl font-bold text-white drop-shadow-sm">{{ $documentsEnAttente->count() }}</span>
                    <span class="text-sm font-bold text-white drop-shadow-sm">en attente</span>
                </div>
                <a href="{{ route('documents.index', ['statut' => 'en_attente']) }}" class="px-4 py-2 rounded-xl bg-white text-emerald-700 font-semibold hover:bg-emerald-50 transition-colors no-underline text-sm">
                    Traiter
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Stats de la structure --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @can('documents.view')
        <a href="{{ route('documents.index') }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-emerald-500/10 group-hover:bg-emerald-500/20 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Documents</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbDocuments }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Ma structure</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">📄</span>
            </div>
        </a>
        @endcan
        @can('dossiers.view')
        <a href="{{ route('dossiers.index') }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-sky-500/10 group-hover:bg-sky-500/20 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">Dossiers</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbDossiers }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Plan de classement</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">📂</span>
            </div>
        </a>
        @endcan
        @can('utilisateurs.view')
        <a href="{{ route('utilisateurs.index') }}?structure_id={{ $structure->id }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
        @else
        <div class="relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700">
        @endcan
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-violet-500/10 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-violet-600 dark:text-violet-400 uppercase tracking-wider">Utilisateurs</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbUtilisateurs }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Rattachés</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center text-xl">👤</span>
            </div>
        @can('utilisateurs.view')
        </a>
        @else
        </div>
        @endcan
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Documents en attente (ma structure) --}}
        @can('documents.view')
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-sky-50 to-indigo-50 dark:from-sky-900/20 dark:to-indigo-900/20 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-sky-200/80 dark:bg-sky-700/60 flex items-center justify-center text-sm">⏳</span>
                    Documents en attente
                </h2>
                @if($documentsEnAttente->isNotEmpty())
                <a href="{{ route('documents.index', ['statut' => 'en_attente']) }}" class="text-sm font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors no-underline">Voir tout →</a>
                @endif
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700 max-h-[360px] overflow-y-auto">
                @forelse($documentsEnAttente as $doc)
                <a href="{{ route('documents.edit', $doc) }}" class="flex items-center gap-4 px-6 py-3 hover:bg-sky-50/50 dark:hover:bg-slate-700/30 transition-colors no-underline group">
                    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center text-sky-600 dark:text-sky-400 group-hover:bg-sky-200 dark:group-hover:bg-sky-800/60 transition-colors">📄</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-800 dark:text-slate-100 truncate group-hover:text-sky-700 dark:group-hover:text-sky-300">{{ $doc->nom_original ?: $doc->titre ?: 'Sans titre' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $doc->createur?->name ?? '—' }} · {{ $doc->dossier?->nom ?? '—' }}</p>
                    </div>
                    <span class="flex-shrink-0 text-slate-400 group-hover:text-sky-500 transition-colors">→</span>
                </a>
                @empty
                <div class="px-6 py-16 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 text-2xl mb-4">✓</span>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Aucun document en attente</p>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Tout est à jour</p>
                </div>
                @endforelse
            </div>
        </div>
        @endcan

        {{-- Derniers dépôts de la structure --}}
        @can('documents.view')
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/50 dark:to-slate-800/50 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-sm">🕐</span>
                    Derniers dépôts (7 jours)
                </h2>
                <a href="{{ route('documents.index') }}" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline no-underline">Voir tout →</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700 max-h-[360px] overflow-y-auto">
                @forelse($documentsRecents as $doc)
                <a href="{{ route('documents.edit', $doc) }}" class="flex items-center gap-4 px-6 py-3 hover:bg-emerald-50/30 dark:hover:bg-slate-700/30 transition-colors no-underline group">
                    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-800/60 transition-colors">📄</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-800 dark:text-slate-100 truncate group-hover:text-emerald-700 dark:group-hover:text-emerald-300">{{ $doc->nom_original ?: $doc->titre ?: 'Sans titre' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $doc->createur?->name ?? '—' }} · {{ $doc->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="flex-shrink-0 text-slate-400 group-hover:text-emerald-500 transition-colors">→</span>
                </a>
                @empty
                <div class="px-6 py-16 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700/50 text-2xl mb-4">📄</span>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Aucun document récent</p>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Les 7 derniers jours</p>
                </div>
                @endforelse
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
