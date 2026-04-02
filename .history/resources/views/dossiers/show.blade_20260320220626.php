@extends('layouts.app')

@section('page-title', $dossier->nom)
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('dossiers.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">Plan de classement</a>
        @foreach($dossier->cheminAncetres() as $ancetre)
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <a href="{{ route('dossiers.show', $ancetre) }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">{{ $ancetre->nom }}</a>
        @endforeach
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">{{ $dossier->nom }}</span>
    </nav>
@endsection

@section('btn-create')
    @can('documents.create')
    <a href="{{ route('documents.create', ['dossier' => $dossier->id]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 shadow-sm hover:shadow transition-all no-underline text-sm">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l4-4m-4 4V8" /></svg>
        Déposer un document
    </a>
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    @if($dossier->description)
    <div class="bg-slate-50 dark:bg-slate-700/30 rounded-xl p-5 border border-slate-100 dark:border-slate-700">
        <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed">{{ $dossier->description }}</p>
    </div>
    @endif

    {{-- Sous-dossiers --}}
    @if($dossier->children->isNotEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
            <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                Sous-dossiers
            </h3>
        </div>
        <div class="p-4 grid gap-3">
            @foreach($dossier->children as $child)
            <a href="{{ route('dossiers.show', $child) }}" class="group flex items-center gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-600 hover:border-emerald-400 dark:hover:border-emerald-600 hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10 transition-all">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-2xl group-hover:scale-105 transition-transform">
                    📂
                </div>
                <div class="flex-1 min-w-0">
                    <span class="font-semibold text-slate-800 dark:text-slate-200 block truncate">{{ $child->nom }}</span>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $child->documents()->count() }} document(s)</span>
                </div>
                <svg class="w-5 h-5 text-slate-400 group-hover:text-emerald-500 transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Documents du dossier --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
            <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Documents
                @if($dossier->documents->isNotEmpty())
                <span class="text-sm font-normal text-slate-500 dark:text-slate-400">({{ $dossier->documents->count() }})</span>
                @endif
            </h3>
        </div>

        @if($dossier->documents->isEmpty())
            <div class="p-12 text-center">
                <div class="inline-flex w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700/50 items-center justify-center text-3xl mb-4">📄</div>
                <p class="text-slate-600 dark:text-slate-400 font-medium">Aucun document dans ce dossier</p>
                <p class="text-slate-500 dark:text-slate-500 text-sm mt-1">Utilisez le bouton «&nbsp;Déposer un document&nbsp;» ci-dessus pour en ajouter</p>
            </div>
        @else
            {{-- Table des documents --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Document</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden sm:table-cell">Type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">Déposé par</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden lg:table-cell">Date</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                        @foreach($dossier->documents as $doc)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0
                                        @php
                                            $ext = strtolower($doc->extension ?? pathinfo($doc->nom_original ?? '', PATHINFO_EXTENSION));
                                            $lib = strtolower($doc->typeDocument->libelle ?? '');
                                            $icon = match(true) {
                                                $ext === 'pdf' || str_contains($lib, 'pdf') => ['bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400', '📄'],
                                                in_array($ext, ['doc','docx']) || str_contains($lib, 'word') => ['bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400', '📝'],
                                                in_array($ext, ['xls','xlsx']) || str_contains($lib, 'excel') => ['bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400', '📊'],
                                                in_array($ext, ['jpg','jpeg','png','gif','webp']) => ['bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400', '🖼️'],
                                                default => ['bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400', '📄'],
                                            };
                                        @endphp
                                        {{ $icon[0] }}">
                                        <span class="text-lg">{{ $icon[1] }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-medium text-slate-800 dark:text-slate-200 block truncate">{{ $doc->titre ?: $doc->nom_original }}</span>
                                        <span class="text-xs text-slate-500 sm:hidden">{{ $doc->typeDocument->libelle }} · {{ $doc->created_at->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400 hidden sm:table-cell">{{ $doc->typeDocument->libelle }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400 hidden md:table-cell">{{ $doc->user->name }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400 hidden lg:table-cell">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('documents.view')
                                    <a href="{{ route('documents.download', $doc) }}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition" title="Télécharger">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    </a>
                                    @endcan
                                    @can('documents.edit')
                                    <a href="{{ route('documents.edit', $doc) }}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition" title="Modifier">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
