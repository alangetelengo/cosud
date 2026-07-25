@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Tableau de bord — Direction Générale')
@section('page-title-info', 'Vue d\'ensemble de l\'organisation et des activités documentaires')

@section('content')
<div class="space-y-8">
    {{-- Hero / Bandeau d'accueil --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#00b464] via-emerald-600 to-teal-700 p-6 sm:p-8 shadow-xl">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.06\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-50"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-white drop-shadow-sm">Vue d'ensemble GED</h2>
                <p class="text-white font-bold mt-1 drop-shadow-sm">Activité documentaire de l'organisation en temps réel</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/20 backdrop-blur-sm">
                    <span class="text-2xl font-bold text-white drop-shadow-sm">{{ $nbEnAttente }}</span>
                    <span class="text-sm font-bold text-white drop-shadow-sm">docs en attente</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/20 backdrop-blur-sm">
                    <span class="text-2xl font-bold text-white drop-shadow-sm">{{ $nbCourriersEnRetard }}</span>
                    <span class="text-sm font-bold text-white drop-shadow-sm">courriers en retard</span>
                </div>
                @if($nbEnAttente > 0)
                <a href="{{ route('documents.index', ['statut' => 'en_attente']) }}" class="px-4 py-2 rounded-xl bg-white text-emerald-700 font-semibold hover:bg-emerald-50 transition-colors no-underline text-sm">
                    Traiter
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Stats globales (cartes améliorées) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        @can('documents.view')
        <a href="{{ route('documents.index') }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-emerald-500/10 group-hover:bg-emerald-500/20 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Documents</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbDocuments }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Hors corbeille</p>
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
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Actifs</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">📂</span>
            </div>
        </a>
        @endcan
        @can('types-documents.view')
        <a href="{{ route('types-documents.index') }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-amber-500/10 group-hover:bg-amber-500/20 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Types</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbTypes }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Catégories</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">📋</span>
            </div>
        </a>
        @endcan
        @can('utilisateurs.view')
        <a href="{{ route('utilisateurs.index') }}" class="group relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-lg border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 no-underline">
            <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-violet-500/10 group-hover:bg-violet-500/20 transition-colors"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-violet-600 dark:text-violet-400 uppercase tracking-wider">Utilisateurs</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">{{ $nbUsers }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Actifs</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">👤</span>
            </div>
        </a>
        @endcan
        <div class="relative overflow-hidden bg-gradient-to-br from-sky-50 to-indigo-50 dark:from-sky-900/20 dark:to-indigo-900/20 rounded-2xl p-6 shadow-lg border-2 border-sky-200/60 dark:border-sky-700/60">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-sky-700 dark:text-sky-300 uppercase tracking-wider">En attente</p>
                    <p class="text-3xl font-extrabold text-sky-800 dark:text-sky-200 mt-1 tabular-nums">{{ $nbEnAttente }}</p>
                    <p class="text-xs text-sky-600 dark:text-sky-400 mt-2">Validation requise</p>
                </div>
                <span class="w-12 h-12 rounded-xl bg-sky-200/60 dark:bg-sky-800/60 flex items-center justify-center text-xl">⏳</span>
            </div>
        </div>
    </div>

    @if($nbCourriersEnRetard > 0)
    <div class="rounded-2xl border-2 border-amber-300 bg-amber-50/80 dark:bg-amber-950/30 dark:border-amber-700 overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-amber-200 dark:border-amber-800 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-bold text-amber-900 dark:text-amber-200">Courriers en retard de traitement</h2>
                <p class="text-xs text-amber-800/80 dark:text-amber-300/80">Étape non traitée depuis plus de {{ $delaiRetardHeures }} h — interpellez le responsable</p>
            </div>
            <span class="px-3 py-1 rounded-lg bg-amber-600 text-white text-sm font-bold">{{ $nbCourriersEnRetard }}</span>
        </div>
        <ul class="divide-y divide-amber-200/80 dark:divide-amber-800">
            @foreach($courriersEnRetard->take(8) as $c)
            <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                <div class="min-w-0">
                    <a href="{{ route('courriers.show', $c) }}" class="font-semibold text-slate-800 dark:text-slate-100 no-underline hover:text-emerald-700">
                        n° {{ $c->numeroRegistreComplet() }} — {{ $c->objet }}
                    </a>
                    <p class="text-xs text-amber-900/70 dark:text-amber-200/70 mt-0.5">
                        Étape : {{ $c->circuitEtapeActuelle?->nom ?? '—' }}
                        · Responsable : {{ $c->circuitEtapeActuelle?->libelleActeur() ?? '—' }}
                        · Depuis {{ $c->circuit_etape_depuis?->diffForHumans() }}
                    </p>
                </div>
                <form method="post" action="{{ route('courriers.circuit.relancer', $c) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700">Relancer</button>
                </form>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Répartition par statut avec barres de progression --}}
    @php
        $totalDocs = $nbDocuments ?: 1;
        $pctBrouillon = round($nbBrouillons / $totalDocs * 100);
        $pctValide = round($nbValides / $totalDocs * 100);
        $pctRejete = round($nbRejetes / $totalDocs * 100);
        $pctArchive = round($nbArchives / $totalDocs * 100);
    @endphp
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/50 dark:to-slate-800/50">
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-slate-200 dark:bg-slate-600 flex items-center justify-center text-sm">📊</span>
                Documents par statut
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $nbDocuments }} document(s) au total</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold text-amber-700 dark:text-amber-400">Déposés</span>
                        <span class="text-slate-600 dark:text-slate-400">{{ $nbBrouillons }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500 transition-all duration-500" style="width: {{ min($pctBrouillon, 100) }}%"></div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold text-emerald-700 dark:text-emerald-400">Validés</span>
                        <span class="text-slate-600 dark:text-slate-400">{{ $nbValides }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ min($pctValide, 100) }}%"></div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold text-red-700 dark:text-red-400">Rejetés</span>
                        <span class="text-slate-600 dark:text-slate-400">{{ $nbRejetes }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-red-500 transition-all duration-500" style="width: {{ min($pctRejete, 100) }}%"></div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="font-semibold text-slate-600 dark:text-slate-400">Archivés</span>
                        <span class="text-slate-600 dark:text-slate-400">{{ $nbArchives }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-slate-500 transition-all duration-500" style="width: {{ min($pctArchive, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Répartition par structure --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/50 dark:to-slate-800/50 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-sm">🏢</span>
                    Par structure
                </h2>
            </div>
            <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Structure</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Docs</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Dossiers</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Utilisateurs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($statsParStructure as $row)
                        <tr class="hover:bg-emerald-50/30 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-3">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $row['structure']->nom }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-sm font-semibold">{{ $row['nb_documents'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 text-sm font-semibold">{{ $row['nb_dossiers'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-lg bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 text-sm font-semibold">{{ $row['nb_utilisateurs'] }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">Aucune structure</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Documents en attente de validation --}}
        @can('documents.view')
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-sky-50 to-indigo-50 dark:from-sky-900/20 dark:to-indigo-900/20 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-sky-200/80 dark:bg-sky-700/60 flex items-center justify-center text-sm animate-pulse">⏳</span>
                    Documents en attente
                </h2>
                @if($documentsEnAttente->isNotEmpty())
                <a href="{{ route('documents.index', ['statut' => 'en_attente']) }}" class="text-sm font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors no-underline">Voir tout →</a>
                @endif
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700 max-h-[420px] overflow-y-auto">
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
    </div>

    {{-- Documents récents (7 jours) --}}
    @can('documents.view')
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-gradient-to-r from-slate-50 to-slate-100/80 dark:from-slate-700/50 dark:to-slate-800/50 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-sm">🕐</span>
                Derniers dépôts (7 jours)
            </h2>
            <a href="{{ route('documents.index') }}" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline no-underline">Voir les documents →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Document</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase hidden sm:table-cell">Créateur</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase hidden md:table-cell">Dossier</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($documentsRecents as $doc)
                    <tr class="hover:bg-emerald-50/30 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-3">
                            <a href="{{ route('documents.edit', $doc) }}" class="font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors truncate block max-w-[220px]">{{ $doc->nom_original ?: $doc->titre ?: 'Sans titre' }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400 hidden sm:table-cell">{{ $doc->createur?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400 hidden md:table-cell">{{ $doc->dossier?->nom ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $doc->created_at->format('d/m/Y') }}</span>
                            <span class="text-xs text-slate-400 dark:text-slate-500 block">{{ $doc->created_at->format('H:i') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">Aucun document récent</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endcan
</div>
@endsection
