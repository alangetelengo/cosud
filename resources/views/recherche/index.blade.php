@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')

@section('page-title', 'Recherche')
@section('page-title-info', 'Rechercher dans les documents et dossiers')

@section('content')
<div class="space-y-6">
    <form action="{{ route('recherche.index') }}" method="GET" class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-6">
        <div class="flex gap-4">
            <input type="text" name="q" value="{{ $q }}" placeholder="Nom, titre, description..." autofocus
                class="flex-1 px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-[#00b464] focus:border-transparent">
            <button type="submit" class="px-6 py-3 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] transition-colors">
                🔍 Rechercher
            </button>
        </div>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Minimum 2 caractères. Recherche dans les noms, titres et descriptions.</p>
    </form>

    @if(strlen($q) >= 2)
        {{-- Dossiers trouvés --}}
        @if($dossiers->isNotEmpty())
        <div>
            <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200 mb-3">Dossiers ({{ $dossiers->count() }})</h2>
            <div class="space-y-2">
                @foreach($dossiers as $dossier)
                <a href="{{ route('dossiers.show', $dossier) }}" class="flex items-center gap-3 p-4 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-[#00b464] hover:bg-emerald-50/50 dark:hover:bg-emerald-900/20 transition-all">
                    <span class="text-2xl">📂</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $dossier->chemin_complet }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Documents trouvés --}}
        <div>
            <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200 mb-3">Documents ({{ $documents->total() }})</h2>
            @if($documents->isEmpty())
                <div class="text-center py-12 text-slate-500 dark:text-slate-400 rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-600">
                    Aucun document trouvé pour « {{ $q }} ».
                </div>
            @else
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Document</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Dossier</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Type</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                            @foreach($documents as $doc)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 font-medium text-slate-800 dark:text-slate-200">{{ $doc->titre ?: $doc->nom_original }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                    @if($doc->dossier)
                                    <a href="{{ route('dossiers.show', $doc->dossier) }}" class="text-[#00b464] hover:underline">{{ $doc->dossier->chemin_complet }}</a>
                                    @else — @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ $doc->typeDocument->libelle }}</td>
                                <td class="px-6 py-4 text-right">
                                    @can('documents.view')
                                    <a href="{{ route('documents.download', $doc) }}" class="text-[#00b464] hover:underline text-sm">Télécharger</a>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($documents->hasPages())
                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600">{{ $documents->links() }}</div>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="text-center py-16 text-slate-500 dark:text-slate-400 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-600">
            <p class="text-lg mb-2">Saisissez au moins 2 caractères pour lancer une recherche.</p>
            <p>Recherche dans les documents (nom, titre, description) et les dossiers.</p>
        </div>
    @endif
</div>
@endsection
