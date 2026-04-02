@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')

@push('head-scripts')
{{-- Défini AVANT tout Alpine (Laravel Boost, etc.) pour éviter "searchableSelect is not a function" --}}
<script>
window.searchableSelect = function(config) {
    var cfg = config || {};
    return {
        options: cfg.options || [],
        selectedValue: String(cfg.selected ?? ''),
        selectedLabel: '',
        search: '',
        isOpen: false,
        name: cfg.name || 'select',
        placeholder: cfg.placeholder || 'Choisir...',
        searchPlaceholder: cfg.searchPlaceholder || 'Rechercher...',
        init: function() {
            var self = this;
            var opt = this.options.find(function(o) { return String(o.value) === String(self.selectedValue); });
            this.selectedLabel = opt ? opt.label : '';
        },
        filteredOptions: function() {
            var raw = String(this.search || '').trim();
            if (!raw) return this.options;
            var tokens = raw.toLowerCase().split(/\s+/).filter(Boolean);
            return this.options.filter(function(o) {
                if (String(o.value) === '') return false;
                var hay = (String(o.label || '') + ' ' + String(o.search || '')).toLowerCase();
                return tokens.every(function(t) { return hay.indexOf(t) >= 0; });
            });
        },
        select: function(option) {
            this.selectedValue = String(option.value ?? '');
            this.selectedLabel = option.label || '';
            this.search = '';
            this.isOpen = false;
        },
        clear: function() {
            this.selectedValue = '';
            this.selectedLabel = '';
            this.search = '';
            this.isOpen = false;
        }
    };
};
</script>
@endpush

@section('page-title', 'Documents')
@section('page-title-info', 'Gestion des documents déposés')

@section('btn-create')
    <div class="flex items-center gap-2">
        @can('documents.view')
        <a href="{{ route('corbeille.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition-all no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Corbeille
        </a>
        @endcan
        @can('documents.create')
        <a href="{{ route('documents.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 shadow-sm hover:shadow transition-all no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Déposer un document
        </a>
        @endcan
    </div>
@endsection

@section('content')
<div id="documents-alerts">
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
    <span class="flex-1">{{ session('success') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
</div>
@endif
@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    <span class="flex-1">{{ session('error') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-red-200/50 dark:hover:bg-red-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
</div>
@endif
</div>

@php
    $dossierOptions = [['value' => '', 'label' => 'Tous les dossiers']];
    foreach ($dossiers as $d) {
        $dossierOptions[] = ['value' => (string) $d->id, 'label' => $d->chemin_complet];
    }
    $typeOptions = [['value' => '', 'label' => 'Tous']];
    foreach ($types as $t) {
        $typeOptions[] = ['value' => (string) $t->id, 'label' => $t->libelle];
    }
    $optionsDestinatairesEnvoi = [];
    foreach ($utilisateursPourEnvoi as $u) {
        $label = $u->name;
        if (! empty($u->email)) {
            $label .= ' — '.$u->email;
        }
        $parts = array_filter([
            $u->name,
            $u->email ?? '',
            $u->email_professionnel ?? '',
            $u->telephone ?? '',
        ], function ($x) {
            return (string) $x !== '';
        });
        $optionsDestinatairesEnvoi[] = [
            'value' => (string) $u->id,
            'label' => $label,
            'search' => mb_strtolower(implode(' ', $parts)),
        ];
    }
@endphp

<div class="space-y-6">
    {{-- Filtres (Tailwind + Alpine, sans Bootstrap) --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-5">
        <form id="filter-form" action="{{ route('documents.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
            <div class="lg:col-span-3">
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1.5">Dossier</label>
                <div class="relative" x-data="window.searchableSelect(window.__selectDossier || {options:[],selected:'',name:'dossier',placeholder:'Tous les dossiers'})" x-init="$watch('isOpen', v => !v && (search = ''))" x-effect="isOpen && $nextTick(() => $refs.searchInput?.focus())" @click.outside="isOpen = false">
                    <input type="hidden" name="dossier" :value="selectedValue">
                    <div @click="isOpen = !isOpen" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer flex items-center justify-between gap-2 hover:border-emerald-500/50 transition-colors">
                        <span class="truncate" x-text="selectedLabel || placeholder"></span>
                        <span class="flex-shrink-0 text-slate-400 text-xs">▼</span>
                    </div>
                    <div x-show="isOpen" x-cloak x-transition class="absolute top-full left-0 right-0 mt-1 z-[100] bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg shadow-xl max-h-60 overflow-hidden flex flex-col min-w-0">
                        <div class="p-2 border-b border-slate-200 dark:border-slate-600">
                            <input type="text" x-ref="searchInput" @input.stop="search = $event.target.value" @click.stop :placeholder="searchPlaceholder" class="w-full px-3 py-2 rounded border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                        </div>
                        <div class="overflow-y-auto flex-1 p-1 max-h-48">
                            <button type="button" @click.stop="clear()" class="w-full text-left px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 rounded">— Tous les dossiers</button>
                            <template x-for="opt in filteredOptions()" :key="'d-'+opt.value">
                                <button type="button" x-show="opt.value !== ''" @click.stop="select(opt)" class="w-full text-left px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded truncate" x-text="opt.label"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1.5">Type</label>
                <div class="relative" x-data="window.searchableSelect(window.__selectType || {options:[],selected:'',name:'type',placeholder:'Tous'})" x-init="$watch('isOpen', v => !v && (search = ''))" x-effect="isOpen && $nextTick(() => $refs.searchInput?.focus())" @click.outside="isOpen = false">
                    <input type="hidden" name="type" :value="selectedValue">
                    <div @click="isOpen = !isOpen" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 cursor-pointer flex items-center justify-between gap-2 hover:border-emerald-500/50 transition-colors">
                        <span class="truncate" x-text="selectedLabel || placeholder"></span>
                        <span class="flex-shrink-0 text-slate-400 text-xs">▼</span>
                    </div>
                    <div x-show="isOpen" x-cloak x-transition class="absolute top-full left-0 right-0 mt-1 z-[100] bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg shadow-xl max-h-60 overflow-hidden flex flex-col min-w-0">
                        <div class="p-2 border-b border-slate-200 dark:border-slate-600">
                            <input type="text" x-ref="searchInput" @input.stop="search = $event.target.value" @click.stop :placeholder="searchPlaceholder" class="w-full px-3 py-2 rounded border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                        </div>
                        <div class="overflow-y-auto flex-1 p-1 max-h-48">
                            <button type="button" @click.stop="clear()" class="w-full text-left px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 rounded">— Tous</button>
                            <template x-for="opt in filteredOptions()" :key="'t-'+opt.value">
                                <button type="button" x-show="opt.value !== ''" @click.stop="select(opt)" class="w-full text-left px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded truncate" x-text="opt.label"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-3">
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1.5">Recherche</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom ou titre du document..." class="w-full px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
            </div>
            <div class="lg:col-span-2 flex items-center gap-2">
                <a href="{{ route('documents.index') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors no-underline" title="Réinitialiser les filtres">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Rafraîchir
                </a>
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500/50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
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
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Statut</th>
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
                                    <a href="{{ route('documents.fiche', $doc) }}" class="font-medium text-slate-800 dark:text-slate-200 hover:text-emerald-600 dark:hover:text-emerald-400 hover:underline transition-colors block no-underline inline-flex items-center gap-1.5">
                                        @if($doc->confidentiel)
                                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Confidentiel"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                        @endif
                                        {{ $doc->titre ?: $doc->nom_original }}
                                    </a>
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
                        <td class="px-6 py-4">
                            @php
                                $statut = $doc->statutDocument?->libelle ?? ucfirst($doc->statut ?? '');
                                $statutCode = strtolower($doc->statutDocument?->code ?? $doc->statut ?? '');
                                $roleLabelPourUser = function ($user): string {
                                    if (! $user) {
                                        return 'valideur';
                                    }
                                    if ($user->hasRole('dg')) {
                                        return 'DG';
                                    }
                                    if ($user->hasRole('directeur')) {
                                        return 'Directeur';
                                    }
                                    if ($user->hasRole('chef_service')) {
                                        return 'Chef de service';
                                    }

                                    return 'valideur';
                                };
                                $roleAvecArticle = function (string $role): string {
                                    return match ($role) {
                                        'DG' => 'le DG',
                                        'Directeur' => 'le Directeur',
                                        'Chef de service' => 'le Chef de service',
                                        default => 'le valideur',
                                    };
                                };
                                $roleAvecDe = function (string $role): string {
                                    return match ($role) {
                                        'DG' => 'du Directeur Général',
                                        'Directeur' => 'du Directeur',
                                        'Chef de service' => 'du Chef de service',
                                        default => 'du valideur',
                                    };
                                };
                                $roleEnAttente = $roleLabelPourUser($doc->workflowValidateur);
                                $validateursAyantVise = $doc->validations
                                    ->where('action', \App\Models\DocumentValidation::ACTION_APPROBATION)
                                    ->map(fn ($v) => $roleAvecArticle($roleLabelPourUser($v->user)))
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->all();
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-medium
                                @if(in_array($statutCode, ['brouillon'])) bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300
                                @elseif(in_array($statutCode, ['en_attente'])) bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300
                                @elseif(in_array($statutCode, ['valide', 'validé'])) bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300
                                @elseif(in_array($statutCode, ['rejete', 'rejeté'])) bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300
                                @elseif(in_array($statutCode, ['archive', 'archivé'])) bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-300
                                @else bg-slate-100 dark:bg-slate-600 text-slate-700 dark:text-slate-300 @endif">
                                {{ $statut }}
                                @if(in_array($statutCode, ['rejete', 'rejeté']) && ($motifRejet = $doc->dernierMotifRejet()))
                                <button type="button" onclick="ouvrirModalMotifRejet(this)" data-motif="{{ e($motifRejet) }}" data-titre="{{ e($doc->titre ?: $doc->nom_original) }}" class="cursor-pointer inline-flex hover:opacity-100 opacity-80 transition-opacity" title="Voir le motif du rejet">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </button>
                                @endif
                            </span>
                            @if(in_array($statutCode, ['en_attente']))
                                <div class="mt-1 space-y-0.5 text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                                    <p>En attente de validation {{ $roleAvecDe($roleEnAttente) }}.</p>
                                    @if(! empty($validateursAyantVise))
                                        <p>Déjà visé par : {{ implode(', ', $validateursAyantVise) }}.</p>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $docStatut = strtolower($doc->statutDocument?->code ?? $doc->statut ?? '');
                                $peutValiderWorkflow = $doc->workflowEtapeActuelle && $doc->workflowEtapeActuelle->peutValider(auth()->user(), $doc);
                            @endphp
                            <div class="flex items-center justify-end gap-1">
                                @can('documents.view')
                                <a href="{{ route('documents.fiche', $doc) }}" class="p-2 rounded-lg text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition" title="Voir les détails">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </a>
                                <a href="{{ route('documents.download', $doc) }}" class="p-2 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition" title="Télécharger">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                </a>
                                @endcan
                                @if(in_array($docStatut, ['en_attente']) && $peutValiderWorkflow)
                                <form action="{{ route('documents.approuver', $doc) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="button" onclick="flashAlert('Approuver (visa) ce document ?', this.closest('form'), {icon:'✓', danger:false, confirmText:'Approuver'})" class="p-2 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition" title="Approuver (visa)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </button>
                                </form>
                                <form action="{{ route('documents.rejeter', $doc) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="button" onclick="flashAlert('Rejeter ce document ? Il repassera en Déposé ou Rejeté. Veuillez indiquer le motif du rejet.', this.closest('form'), {icon:'✗', danger:true, confirmText:'Rejeter', input:{name:'commentaire', label:'Motif du rejet', placeholder:'Indiquez la raison du rejet...', required:true}})" class="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="Rejeter">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </button>
                                </form>
                                @endif
                                @if(in_array($docStatut, ['brouillon', 'rejete']))
                                @can('envoyerValidation', $doc)
                                @php
                                    $wfCtx = $workflowContextByDocumentId[$doc->id]
                                        ?? \App\Models\WorkflowEtape::contexteEnvoiPourType($doc->type_document_id, $doc->typeDocument?->libelle, $doc->dossier_id);
                                @endphp
                                <button type="button"
                                    data-doc-id="{{ $doc->id }}"
                                    data-doc-titre="{{ e($doc->titre ?: $doc->nom_original) }}"
                                    data-workflow-source="{{ $wfCtx['source'] ?? '' }}"
                                    data-workflow-type-libelle="{{ e($doc->typeDocument?->libelle ?? '') }}"
                                    data-workflow-service-nom="{{ e($wfCtx['service_nom'] ?? '') }}"
                                    data-workflow-hierarchique="{{ ! empty($wfCtx['premiere_validation_hierarchique']) ? '1' : '0' }}"
                                    data-workflow-libre="{{ ! empty($wfCtx['premiere_destinataire_libre']) ? '1' : '0' }}"
                                    data-workflow-etapes="{{ e(json_encode($wfCtx['etapes_libelles'] ?? [], JSON_UNESCAPED_UNICODE)) }}"
                                    onclick="ouvrirModalEnvoiValidation(this)"
                                    class="p-2 rounded-lg text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition @if($utilisateursPourEnvoi->isEmpty()) opacity-40 cursor-not-allowed @endif"
                                    title="Envoyer (propriétaire — choix du destinataire)"
                                    @if($utilisateursPourEnvoi->isEmpty()) disabled @endif>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                </button>
                                @endcan
                                @elseif(in_array($docStatut, ['valide', 'validé']))
                                @can('update', $doc)
                                <form action="{{ route('documents.archiver', $doc) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="button" onclick="flashAlert('Archiver ce document ? Il passera en statut Archivé.', this.closest('form'), {icon:'📦', danger:false, confirmText:'Archiver'})" class="p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition" title="Archiver">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                    </button>
                                </form>
                                @endcan
                                @endif
                                @if(!in_array($docStatut, ['archive', 'archivé']))
                                @can('update', $doc)
                                <a href="{{ route('documents.edit', $doc) }}" class="inline-flex items-center justify-center min-w-[2.5rem] min-h-[2.5rem] p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                                @endcan
                                @endif
                                @can('documents.delete')
                                <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="inline-block">
                                    @csrf @method('DELETE')
                                    <button type="button" onclick="flashAlert('Supprimer ce document ?', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})" class="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition" title="Supprimer">
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

@push('body-modals')
    {{-- Formulaire « envoi validation » : injecté dans flashAlert (même shell que Rejeter / Approuver). --}}
    @if(!$utilisateursPourEnvoi->isEmpty())
    <div id="envoi-validation-flash-pool" class="hidden" aria-hidden="true">
        <form id="formEnvoiValidation" method="POST" action="#" class="mx-auto w-full max-w-none space-y-4 text-left">
            @csrf
            <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 dark:border-slate-600 dark:bg-slate-800/70">
                <div class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Préparer l’envoi en validation</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Choisissez un destinataire autorisé. Le document suivra ensuite le circuit défini.</p>
                </div>
            </div>
            <div class="rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50 to-teal-50 px-4 py-3 text-sm text-slate-700 shadow-sm dark:border-emerald-800/40 dark:from-emerald-950/20 dark:to-teal-950/10 dark:text-slate-200">
                <p id="envoi-workflow-info-text" class="leading-relaxed"></p>
            </div>
            <div id="envoi-workflow-steps-wrap" class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800/80">
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Étapes du circuit</p>
                <ol id="envoi-workflow-etapes" class="max-h-40 list-decimal space-y-1 overflow-y-auto pl-5 text-sm text-slate-800 dark:text-slate-200"></ol>
            </div>
            <p id="envoi-workflow-hierarchique-note" class="hidden rounded-lg bg-sky-50 px-3 py-2 text-xs leading-relaxed text-sky-700 dark:bg-sky-900/20 dark:text-sky-300"></p>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-600 dark:bg-slate-800/70">
                <label id="label-envoi-destinataire" class="mb-1.5 block text-sm font-semibold text-slate-800 dark:text-slate-100">Destinataire <span class="text-red-500 dark:text-red-400">*</span></label>
                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Ouvrez la liste puis tapez pour filtrer (nom, e-mail, téléphone).</p>
                <div class="relative z-[10001]" x-data="window.searchableSelect(window.__selectDestinataireEnvoi || { options: [], selected: '', name: 'destinataire_id', placeholder: 'Choisir un destinataire…', searchPlaceholder: 'Nom, prénom, e-mail, téléphone…' })" x-init="$watch('isOpen', function(v) { if (!v) search = ''; })" x-effect="isOpen && $nextTick(function() { if ($refs.searchInput) $refs.searchInput.focus(); })" @click.outside="isOpen = false" @reset-envoi-destinataire.window="clear()">
                    <input type="hidden" name="destinataire_id" :value="selectedValue">
                    <button type="button" id="envoi_destinataire_combo" class="flex min-h-[48px] w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-left text-sm text-slate-800 shadow-sm transition hover:border-emerald-500/60 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-emerald-400" :aria-expanded="isOpen" aria-haspopup="listbox" aria-labelledby="label-envoi-destinataire" @click="isOpen = !isOpen">
                        <span class="truncate text-slate-700 dark:text-slate-200" x-text="selectedLabel || placeholder"></span>
                        <svg class="h-5 w-5 shrink-0 text-slate-400 transition-transform duration-200 dark:text-slate-500" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <template x-if="isOpen">
                        <div class="absolute left-0 right-0 top-full z-[10002] mt-1.5 flex max-h-80 min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/5 dark:border-slate-600 dark:bg-slate-800 dark:ring-white/10">
                            <div class="border-b border-slate-200 bg-slate-50 p-2.5 dark:border-slate-600 dark:bg-slate-900/40">
                                <input type="text" x-ref="searchInput" @input.stop="search = $event.target.value" @click.stop :placeholder="searchPlaceholder" autocomplete="off" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            </div>
                            <div class="max-h-60 flex-1 overflow-y-auto p-1.5">
                                <button type="button" @click.stop="clear()" class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Effacer la sélection</button>
                                <template x-for="opt in filteredOptions()" :key="'env-'+opt.value">
                                    <button type="button" @click.stop="select(opt)" class="w-full rounded-lg px-3 py-2 text-left text-sm leading-5 text-slate-800 hover:bg-emerald-50 dark:text-slate-200 dark:hover:bg-emerald-900/25 whitespace-normal break-words" x-text="opt.label"></button>
                                </template>
                                <p x-show="String(search || '').trim() && filteredOptions().length === 0" class="px-3 py-4 text-center text-sm text-slate-500 dark:text-slate-400">Aucun résultat pour cette recherche.</p>
                            </div>
                        </div>
                    </template>
                </div>
                <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Astuce : tapez une partie du nom, de l’e-mail ou du téléphone pour filtrer rapidement.</p>
            </div>
        </form>
    </div>
    @endif

    <div id="motifRejetModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto overflow-x-hidden" role="dialog" aria-modal="true" style="left:0;top:0;right:0;bottom:0;width:100%;margin:0;">
        <div onclick="if(event.target===this)fermerModalMotifRejet()" class="flex min-h-full w-full items-center justify-center p-4" style="min-height:100vh;min-height:100dvh;background:rgba(0,0,0,0.45);">
        <div class="relative flex flex-col mx-auto w-[92%] sm:w-[90%] max-w-[460px] max-h-[90vh] overflow-hidden rounded-2xl text-center shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] dark:shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] ring-1 ring-slate-200/80 dark:ring-slate-600/80 bg-white dark:bg-slate-800" onclick="event.stopPropagation()">
            <div class="relative shrink-0 px-5 pt-5 pb-4 sm:px-6 bg-gradient-to-br from-amber-500 via-amber-600 to-orange-700 text-white">
                <button type="button" onclick="fermerModalMotifRejet()" class="absolute top-3 right-3 flex h-9 w-9 items-center justify-center rounded-lg text-white/90 hover:bg-white/15 transition-colors focus:outline-none focus:ring-2 focus:ring-white/40" title="Fermer" aria-label="Fermer la fenêtre">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/30 shadow-inner">
                    <span class="text-3xl leading-none" aria-hidden="true">⚠️</span>
                </div>
                <h3 class="mt-4 text-xl font-bold tracking-tight">Motif du rejet</h3>
                <p id="motifRejetModalTitre" class="mt-2 px-2 text-sm text-amber-50/95 line-clamp-2 font-medium"></p>
            </div>
            <div class="px-5 py-5 sm:px-6 text-left bg-slate-50/50 dark:bg-slate-900/25">
                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Commentaire du valideur</label>
                <textarea id="motifRejetModalContenu" readonly class="w-full resize-none rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 p-4 font-sans text-[0.95rem] leading-relaxed shadow-inner" rows="5" style="min-height:120px; max-height:40vh;"></textarea>
            </div>
            <div class="flex justify-end border-t border-slate-200/90 dark:border-slate-600/80 bg-white dark:bg-slate-800 px-5 py-4 sm:px-6">
                <button type="button" onclick="fermerModalMotifRejet()" class="inline-flex min-h-[42px] items-center justify-center px-5 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-500 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-100 font-semibold text-sm shadow-sm hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                    Fermer
                </button>
            </div>
        </div>
        </div>
    </div>
@endpush

@push('scripts')
<script>
window.__selectDossier = {!! json_encode(['options' => $dossierOptions, 'selected' => request('dossier', ''), 'name' => 'dossier', 'placeholder' => 'Tous les dossiers', 'searchPlaceholder' => 'Rechercher un dossier…']) !!};
window.__selectType = {!! json_encode(['options' => $typeOptions, 'selected' => request('type', ''), 'name' => 'type', 'placeholder' => 'Tous', 'searchPlaceholder' => 'Rechercher un type…']) !!};
window.__selectDestinataireEnvoi = {!! json_encode(['options' => $optionsDestinatairesEnvoi, 'selected' => '', 'name' => 'destinataire_id', 'placeholder' => 'Choisir un destinataire…', 'searchPlaceholder' => 'Nom, prénom, e-mail, téléphone…']) !!};
window.ouvrirModalMotifRejet = function(btn) {
    var motif = btn.getAttribute('data-motif') || '';
    var titre = btn.getAttribute('data-titre') || 'Document';
    document.getElementById('motifRejetModalContenu').value = motif;
    document.getElementById('motifRejetModalTitre').textContent = titre;
    document.getElementById('motifRejetModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};
window.fermerModalMotifRejet = function() {
    document.getElementById('motifRejetModal').classList.add('hidden');
    document.body.style.overflow = '';
};
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var m = document.getElementById('motifRejetModal');
        if (m && !m.classList.contains('hidden')) fermerModalMotifRejet();
    }
});
/** Ancrage direct sous <body> : évite tout recadrage de position:fixed par un ancêtre (transform, etc.). */
document.addEventListener('DOMContentLoaded', function() {
    var node = document.getElementById('motifRejetModal');
    if (node && node.parentElement !== document.body) {
        document.body.appendChild(node);
    }
});
function remplirEnvoiWorkflowDepuisBouton(btn) {
    var source = btn.getAttribute('data-workflow-source') || '';
    var hier = btn.getAttribute('data-workflow-hierarchique') === '1';
    var libre = btn.getAttribute('data-workflow-libre') === '1';
    var etapes = [];
    try {
        etapes = JSON.parse(btn.getAttribute('data-workflow-etapes') || '[]');
        if (!Array.isArray(etapes)) etapes = [];
    } catch (e) { etapes = []; }
    var infoEl = document.getElementById('envoi-workflow-info-text');
    var ol = document.getElementById('envoi-workflow-etapes');
    var note = document.getElementById('envoi-workflow-hierarchique-note');
    var wrap = document.getElementById('envoi-workflow-steps-wrap');
    if (infoEl) {
        var parts = [];
        if (source && etapes.length) {
            parts.push(libre
                ? 'Le destinataire choisi sera utilisé comme valideur de la première étape.'
                : 'Le destinataire choisi sera le premier valideur de la première étape ci-dessous.');
        } else if (!source) {
            parts.push('Aucun workflow n’est configuré pour ce document ; l’envoi peut être refusé. Contactez un administrateur.');
        } else {
            parts.push('Le destinataire choisi sera le premier valideur de la première étape du circuit.');
        }
        infoEl.textContent = parts.join(' ');
    }
    if (ol) {
        ol.innerHTML = '';
        etapes.forEach(function (nom) {
            var li = document.createElement('li');
            li.textContent = nom;
            ol.appendChild(li);
        });
    }
    if (wrap) wrap.style.display = etapes.length ? 'block' : 'none';
    if (note) {
        if (hier) {
            note.classList.remove('hidden');
            note.textContent = 'Première étape en mode hiérarchique : la chaîne des responsables est calculée à partir de l’organigramme.';
        } else {
            note.classList.add('hidden');
            note.textContent = '';
        }
    }
}
window.ouvrirModalEnvoiValidation = function(btn) {
    if (btn.disabled) return;
    var id = btn.getAttribute('data-doc-id');
    var titre = btn.getAttribute('data-doc-titre') || 'Document';
    var form = document.getElementById('formEnvoiValidation');
    if (!form || typeof window.flashAlert !== 'function') return;
    remplirEnvoiWorkflowDepuisBouton(btn);
    var base = @json(rtrim(url('/'), '/'));
    form.action = base + '/documents/' + encodeURIComponent(id) + '/envoyer-validation';
    form.reset();
    window.dispatchEvent(new CustomEvent('reset-envoi-destinataire'));
    window.flashAlert(
        '',
        form,
        {
            title: 'Envoyer en validation',
            documentTitle: titre,
            icon: '✉️',
            danger: false,
            confirmText: 'Envoyer maintenant',
            maxWidth: '820px',
            width: 'min(95vw, 820px)',
            padding: '1.75rem 2rem',
            customBodyId: 'formEnvoiValidation',
            customBodyPoolId: 'envoi-validation-flash-pool',
            onConfirm: function(f) {
                var hid = f.querySelector('input[name="destinataire_id"]');
                if (!hid || !String(hid.value || '').trim()) {
                    alert('Veuillez choisir un destinataire.');
                    return false;
                }
                return true;
            }
        }
    );
};
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('filter-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var url = form.action + (form.action.includes('?') ? '&' : '?') + new URLSearchParams(new FormData(form)).toString();
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        }).then(function(r) { return r.text(); }).then(function(html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newTable = doc.getElementById('documents-table-container');
            var newAlerts = doc.getElementById('documents-alerts');
            var curTable = document.getElementById('documents-table-container');
            var curAlerts = document.getElementById('documents-alerts');
            if (newTable && curTable) curTable.replaceWith(newTable);
            if (newAlerts && curAlerts) curAlerts.replaceWith(newAlerts);
            if (history.pushState) history.pushState({}, '', url);
        }).catch(function() {
            form.submit();
        });
    });
});
</script>
@endpush
@endsection
