<div class="mb-4">
    <div class="flex flex-wrap items-center gap-2 py-3 px-4 rounded-xl {{ $structure->code === 'DG' ? 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600' }}">
        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $structure->nom }}</span>
        <span class="text-xs px-2 py-0.5 rounded bg-slate-200/80 dark:bg-slate-600 text-slate-600 dark:text-slate-300 font-mono">{{ $structure->code }}</span>
        @if($structure->fonction)
        <span class="text-xs px-2 py-0.5 rounded bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-200 font-medium">{{ $structure->fonction->libelle }}</span>
        @if($structure->role_technique)
        <span class="text-xs px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-200 font-mono">{{ $structure->role_technique }}</span>
        @endif
        @endif
        @php $t = $structure->titulaireValidationActuel(); @endphp
        @if($t)
        <span class="text-sm text-slate-500 dark:text-slate-400">→ {{ $t->name }}</span>
        @elseif($structure->fonction_id)
        <span class="text-xs italic text-amber-600 dark:text-amber-400">Aucun titulaire (affectez un utilisateur avec cette fonction)</span>
        @endif
        @if(auth()->user()->hasRole('admin'))
        <a href="{{ route('parametres.structures.edit', $structure) }}" class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/30">
            Modifier
        </a>
        @endif
    </div>
    @if(! empty($structure->users) && $structure->users->isNotEmpty())
    <div class="mt-2 ml-4 pl-3 border-l border-slate-200/80 dark:border-slate-700">
        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Utilisateurs rattachés (actifs)</p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach($structure->users->sortBy('name') as $u)
            <span class="inline-flex items-center gap-2 px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/40 text-xs font-medium text-slate-700 dark:text-slate-200">
                {{ $u->name }}
                @if($u->pivot?->role)
                <span class="text-slate-500 dark:text-slate-300 font-semibold">({{ $u->pivot->role }})</span>
                @endif
            </span>
            @endforeach
        </div>
    </div>
    @endif
    @php $enfants = $byParent->get($structure->id, collect()); @endphp
    @if($enfants->isNotEmpty())
    <div class="mt-2 ml-6 pl-4 border-l-2 border-emerald-200/80 dark:border-emerald-800 space-y-2">
        @foreach($enfants->sortBy('nom') as $enfant)
            @include('parametres.structures._node', ['structure' => $enfant, 'byParent' => $byParent])
        @endforeach
    </div>
    @endif
</div>
