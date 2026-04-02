@extends('layouts.app')

@section('page-title', 'Plan de classement')
@section('page-title-info', 'Arborescence des dossiers du GED')
@section('btn-create')
    @can('create', App\Models\Dossier::class)
    <a href="{{ route('dossiers.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 shadow-sm transition-all no-underline text-sm">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
        Créer un dossier
    </a>
    @endcan
@endsection

@section('content')
<div class="space-y-4">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        <span class="flex-1">{{ session('success') }}</span>
        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-800">×</button>
    </div>
    @endif

    {{-- Filtres, recherche et contrôles --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-4" x-data>
        <div class="flex flex-wrap items-center gap-3">
            {{-- Onglets filtre --}}
            <div class="flex flex-wrap gap-1">
                <a href="{{ route('dossiers.index', ['filtre' => 'tous', 'q' => $q]) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($filtre ?? 'tous') === 'tous' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                    Tous
                </a>
                <a href="{{ route('dossiers.index', ['filtre' => 'mes', 'q' => $q]) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($filtre ?? '') === 'mes' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                    Mes dossiers
                </a>
                <a href="{{ route('dossiers.index', ['filtre' => 'partages', 'q' => $q]) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($filtre ?? '') === 'partages' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                    Dossiers partagés
                </a>
                <a href="{{ route('dossiers.index', ['filtre' => 'recents', 'q' => $q]) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($filtre ?? '') === 'recents' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                    Récents
                </a>
                <a href="{{ route('dossiers.index', ['filtre' => 'favoris', 'q' => $q]) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($filtre ?? '') === 'favoris' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                    ⭐ Favoris
                </a>
            </div>

            {{-- Recherche (arbre uniquement) --}}
            @if(in_array($filtre ?? 'tous', ['tous', 'mes']))
            <form action="{{ route('dossiers.index') }}" method="GET" class="flex items-center gap-2 flex-1 min-w-[200px]">
                <input type="hidden" name="filtre" value="{{ $filtre ?? 'tous' }}">
                <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Rechercher un dossier..." class="flex-1 min-w-0 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 text-sm">
                <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 text-sm font-medium">
                    Rechercher
                </button>
                <a href="{{ route('dossiers.index') }}" class="px-4 py-2 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium inline-flex items-center gap-1.5 no-underline" title="Réinitialiser les filtres">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Rafraîchir
                </a>
            </form>
            @endif

            {{-- Tout replier / développer (arbre uniquement) --}}
            @if(in_array($filtre ?? 'tous', ['tous', 'mes']) && $dossiers->isNotEmpty())
            <div class="flex gap-1">
                <button type="button" @click="$dispatch('expand-all-dossiers')" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">
                    Tout développer
                </button>
                <button type="button" @click="$dispatch('collapse-all-dossiers')" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">
                    Tout replier
                </button>
            </div>
            @endif
        </div>
    </div>

    {{-- Contenu : arbre ou listes --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="p-6 lg:p-8">
            @if(($filtre ?? '') === 'favoris')
                @if(($dossiersFavoris ?? collect())->isEmpty())
                <p class="text-slate-500 dark:text-slate-400 py-8 text-center">Aucun dossier en favori. Cliquez sur ⭐ pour ajouter.</p>
                @else
                <div class="space-y-2">
                    @foreach($dossiersFavoris as $d)
                    <a href="{{ route('dossiers.show', $d) }}" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:border-emerald-400 dark:hover:border-emerald-600 hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10 transition-all group">
                        <span class="text-xl">📂</span>
                        <div class="flex-1 min-w-0">
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $d->nom }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">{{ $d->chemin_complet }}</span>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ $d->documents_count ?? $d->documents()->count() }} doc.</span>
                        <form action="{{ route('dossiers.favori', $d) }}" method="POST" class="inline-block" onclick="event.preventDefault(); this.submit();">
                            @csrf
                            <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                            <button type="submit" class="p-2 rounded-lg text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20" title="Retirer des favoris">⭐</button>
                        </form>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    @endforeach
                </div>
                @endif
            @elseif(($filtre ?? '') === 'recents')
                @if(($dossiersRecents ?? collect())->isEmpty())
                <p class="text-slate-500 dark:text-slate-400 py-8 text-center">Aucun dossier avec activité récente (30 derniers jours).</p>
                @else
                <div class="space-y-2">
                    @foreach($dossiersRecents as $d)
                    <a href="{{ route('dossiers.show', $d) }}" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:border-emerald-400 dark:hover:border-emerald-600 hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10 transition-all group">
                        <span class="text-xl">📂</span>
                        <div class="flex-1 min-w-0">
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $d->nom }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">{{ $d->chemin_complet }}</span>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ $d->documents_count ?? $d->documents()->count() }} doc.</span>
                        @if(in_array($d->id, $favoriIds ?? []))
                        <form action="{{ route('dossiers.favori', $d) }}" method="POST" class="inline-block" onclick="event.preventDefault(); this.submit();">
                            @csrf
                            <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                            <button type="submit" class="p-2 rounded-lg text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20" title="Retirer des favoris">⭐</button>
                        </form>
                        @else
                        <form action="{{ route('dossiers.favori', $d) }}" method="POST" class="inline-block" onclick="event.preventDefault(); this.submit();">
                            @csrf
                            <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                            <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20" title="Ajouter aux favoris">☆</button>
                        </form>
                        @endif
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    @endforeach
                </div>
                @endif
            @elseif(($filtre ?? '') === 'partages')
                @if(($dossiersPartages ?? collect())->isEmpty())
                <p class="text-slate-500 dark:text-slate-400 py-8 text-center">Aucun dossier partagé avec vous.</p>
                @else
                <div class="space-y-2">
                    @foreach($dossiersPartages as $d)
                    @php $partageActif = $d->partages->first(); @endphp
                    <a href="{{ route('dossiers.show', $d) }}" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:border-emerald-400 dark:hover:border-emerald-600 hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10 transition-all group">
                        <span class="text-xl">📂</span>
                        <div class="flex-1 min-w-0">
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $d->nom }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">{{ $d->chemin_complet }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Propriétaire : {{ $d->proprietaire?->name ?? 'Non défini' }}</span>
                            @if($partageActif)
                            <span class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 {{ $partageActif->droits_lecture ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">Lecture</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 {{ $partageActif->droits_ecriture ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">Ecriture</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 {{ $partageActif->droits_suppression ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">Suppression</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                    Expire : {{ $partageActif->date_expiration ? $partageActif->date_expiration->format('d/m/Y') : 'Jamais' }}
                                </span>
                            </span>
                            @endif
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ $d->documents_count ?? $d->documents()->count() }} doc.</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    @endforeach
                </div>
                @endif
            @else
                {{-- Arbre --}}
                @if($dossiers->isEmpty())
                <p class="text-slate-500 dark:text-slate-400 py-8 text-center">
                    @if($q ?? '')
                        Aucun dossier trouvé pour "{{ $q }}".
                    @elseif(($filtre ?? '') === 'mes')
                        Aucun dossier directement lié à votre compte (propriétaire, créateur ou partagé).
                    @else
                        Aucun dossier.
                    @endif
                </p>
                @else
                <div class="space-y-0.5" id="dossiers-tree">
                    @foreach($dossiers as $dossier)
                        @include('dossiers.partials.dossier-node', ['dossier' => $dossier, 'niveau' => 0, 'favoriIds' => $favoriIds ?? []])
                    @endforeach
                </div>
                @endif
            @endif
        </div>
    </div>
</div>

@endsection
