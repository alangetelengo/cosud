@extends('layouts.app')

@section('page-title', 'Documents')
@section('page-title-info', 'Gestion des documents déposés')

@section('btn-create')
    @can('documents.create')
    <a href="{{ route('documents.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 shadow-sm hover:shadow transition-all no-underline">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        Déposer un document
    </a>
    @endcan
@endsection

@section('content')
<div id="documents-alerts">
@if(session('success'))
<div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    {{ session('error') }}
</div>
@endif
</div>

@push('styles')
{{-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet"> --}}
{{-- La protection du sidebar est dans app.css (protection globale contre Bootstrap) --}}
@endpush

<div class="space-y-6">
    {{-- Filtres --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-5 overflow-visible">
        <form id="filter-form" action="{{ route('documents.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label text-slate-600 dark:text-slate-400">Dossier</label>
                <select name="dossier" id="select-dossier" class="form-select">
                    <option value="">Tous les dossiers</option>
                    @foreach($dossiers as $d)
                    <option value="{{ $d->id }}" {{ request('dossier') == $d->id ? 'selected' : '' }}>{{ $d->chemin_complet }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-2">
                <label class="form-label text-slate-600 dark:text-slate-400">Type</label>
                <select name="type" id="select-type" class="form-select">
                    <option value="">Tous</option>
                    @foreach($types as $t)
                    <option value="{{ $t->id }}" {{ request('type') == $t->id ? 'selected' : '' }}>{{ $t->libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label text-slate-600 dark:text-slate-400">Recherche</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom ou titre du document..." class="form-control">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-success">
                    <svg class="me-2" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                    Filtrer
                </button>
            </div>
        </form>
    </div>

    {{-- Tableau --}}
    <div id="documents-table-container" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/70 border-b border-slate-200 dark:border-slate-600">
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Document</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden lg:table-cell">Dossier</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">Déposé par</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden sm:table-cell">Taille</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($documents as $doc)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center
                                    @if(in_array($doc->extension, ['pdf'])) bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400
                                    @elseif(in_array($doc->extension, ['doc','docx'])) bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400
                                    @elseif(in_array($doc->extension, ['xls','xlsx'])) bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400
                                    @elseif(in_array($doc->extension, ['jpg','jpeg','png','gif'])) bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400
                                    @else bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-300
                                    @endif">
                                    @if(in_array($doc->extension, ['pdf']))
                                    <span class="text-lg font-bold">PDF</span>
                                    @elseif(in_array($doc->extension, ['doc','docx']))
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zm-3 8h2v6h-2v-6zm4 0h2v6h-2v-6zm-4 4h2v2h-2v-2zm4 0h2v2h-2v-2z"/></svg>
                                    @elseif(in_array($doc->extension, ['xls','xlsx']))
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zm-2 4h2v2h-2V8zm0 4h2v2h-2v-2zm0 4h2v2h-2v-2zm4-8h2v2h-2V8zm0 4h2v2h-2v-2zm0 4h2v2h-2v-2z"/></svg>
                                    @elseif(in_array($doc->extension, ['jpg','jpeg','png','gif']))
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    @else
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ $doc->titre ?: $doc->nom_original }}</div>
                                    @if($doc->titre)<div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[200px]">{{ $doc->nom_original }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400 hidden lg:table-cell">
                            @if($doc->dossier)
                            <a href="{{ route('dossiers.show', $doc->dossier) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline truncate max-w-[180px] block">{{ $doc->dossier->chemin_complet }}</a>
                            @else<span class="text-slate-400">—</span>@endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-600 text-slate-700 dark:text-slate-300">
                                {{ $doc->typeDocument->libelle }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400 hidden md:table-cell">{{ $doc->user->name }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400 text-sm">{{ $doc->created_at->format('d/m/Y') }}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400 text-sm hidden sm:table-cell">{{ $doc->taille_formatee }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1">
                                @can('documents.view')
                                <a href="{{ route('documents.download', $doc) }}" class="p-2 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition" title="Télécharger">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                </a>
                                @endcan
                                @can('documents.edit')
                                <a href="{{ route('documents.edit', $doc) }}" class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                                @endcan
                                @can('documents.delete')
                                <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="inline-block" onsubmit="return confirm('Supprimer ce document ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-slate-500 dark:text-slate-400">
                                <svg class="w-16 h-16 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <p class="font-medium">Aucun document</p>
                                <p class="text-sm">Déposez votre premier document pour commencer</p>
                                @can('documents.create')
                                <a href="{{ route('documents.create') }}" class="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    Déposer un document
                                </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-700/30">
            {{ $documents->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/i18n/fr.js"></script>
<script>
$(function() {
    $('#select-dossier').select2({
        theme: 'bootstrap-5',
        placeholder: 'Tous les dossiers',
        allowClear: true,
        language: 'fr'
    });
    $('#select-type').select2({
        theme: 'bootstrap-5',
        placeholder: 'Tous',
        allowClear: true,
        language: 'fr'
    });

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var url = form.attr('action') + '?' + form.serialize();
        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                var $response = $(response);
                var newTable = $response.find('#documents-table-container');
                var newAlerts = $response.find('#documents-alerts');
                if (newTable.length && $('#documents-table-container').length) {
                    $('#documents-table-container').replaceWith(newTable);
                }
                if (newAlerts.length && $('#documents-alerts').length) {
                    $('#documents-alerts').replaceWith(newAlerts);
                }
                if (window.history && window.history.pushState) {
                    window.history.pushState({}, '', url);
                }
            },
            error: function() {
                form[0].submit();
            }
        });
    });
});
</script>
@endpush
@endsection
