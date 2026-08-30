@can('view', $dossier)
@php
    $pl = $niveau * 28;
    // Les enfants sont déjà filtrés dans DossierController::filtrerEnfantsVisibles (aligné sur DossierPolicy::view).
    $hasChildren = $dossier->children->isNotEmpty();
    $typeLabel = match($dossier->type) {
        'administratif' => 'Administration',
        'finance' => 'Finance',
        'projet' => 'Projet',
        'client' => 'Client',
        'operation' => 'Opération',
        'archive' => 'Archive',
        'confidentiel' => 'Confidentiel',
        default => null,
    };
    $docCount = $dossier->documents_count ?? $dossier->documents()->count();
    $favoriIds = $favoriIds ?? [];
    $isFavori = in_array($dossier->id, $favoriIds);
    $user = auth()->user();
    $droitLabel = $dossier->proprietaire_id === $user->id ? 'Propriétaire' : ($dossier->createur_id === $user->id ? 'Créateur' : ($dossier->estLieA($user) ? 'Partagé' : null));
@endphp
<div class="dossier-node" x-data="{ open: true }" @expand-all-dossiers.window="open = true" @collapse-all-dossiers.window="open = false" style="margin-left: {{ $pl }}px;">
    <div class="flex items-center gap-2 py-2.5 pl-3 pr-4 -ml-px border-l-2 border-slate-200 dark:border-slate-600 rounded-r-lg hover:bg-emerald-50/50 dark:hover:bg-slate-700/40 hover:border-emerald-300/50 dark:hover:border-emerald-700/50 transition-all duration-150 group">
        @if($hasChildren)
        <button type="button" @click="open = !open" class="flex-shrink-0 w-6 h-6 rounded flex items-center justify-center text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-100/50 dark:hover:bg-emerald-900/30 transition-colors">
            <svg class="w-4 h-4 transition-transform duration-200" :class="{ '-rotate-90': !open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        </button>
        @else
        <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center text-slate-300 dark:text-slate-600">│</span>
        @endif
        <a href="{{ route('dossiers.show', $dossier) }}" class="flex items-center gap-3 flex-1 min-w-0">
            <span class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center {{ $hasChildren ? 'bg-emerald-100/80 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-600/80 text-slate-500 dark:text-slate-400' }}" title="Dossier">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
            </span>
            <div class="flex-1 min-w-0">
                <span class="font-medium text-slate-800 dark:text-slate-200 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">{{ $dossier->nom }}</span>
                @if($typeLabel)
                    <span class="ml-2 px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 dark:bg-slate-600/80 text-slate-600 dark:text-slate-300">{{ $typeLabel }}</span>
                @endif
                @if($droitLabel)
                    <span class="ml-2 px-2 py-0.5 rounded-md text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300" title="Droits sur ce dossier">{{ $droitLabel }}</span>
                @endif
                @if($dossier->confidentiel)
                    <span class="ml-2 px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300">🔒 Confidentiel</span>
                @endif
            </div>
            <span class="flex-shrink-0 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100/80 dark:bg-slate-700/80 text-slate-600 dark:text-slate-400">{{ $docCount }} doc.</span>
            <form action="{{ route('dossiers.favori', $dossier) }}" method="POST" class="inline-block flex-shrink-0" data-skip-submit-loading="1" onclick="event.stopPropagation()">
                @csrf
                <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                <button type="submit" class="p-1.5 rounded-lg {{ $isFavori ? 'text-amber-500' : 'text-slate-400 hover:text-amber-500' }} hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors" title="{{ $isFavori ? 'Retirer des favoris' : 'Ajouter aux favoris' }}">{{ $isFavori ? '⭐' : '☆' }}</button>
            </form>
            <svg class="flex-shrink-0 w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </a>
    </div>
    @if($hasChildren)
    <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-0.5" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        @foreach($dossier->children as $child)
            @include('dossiers.partials.dossier-node', ['dossier' => $child, 'niveau' => $niveau + 1, 'favoriIds' => $favoriIds])
        @endforeach
    </div>
    @endif
</div>
@endcan
