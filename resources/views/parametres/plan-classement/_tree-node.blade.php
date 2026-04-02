<li class="text-sm">
    <div class="flex flex-wrap items-center gap-2 py-2 px-3 rounded-lg bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600">
        <span class="font-medium text-slate-800 dark:text-slate-200">{{ $node->nom }}</span>
        @if($node->code)
        <span class="text-xs px-2 py-0.5 rounded bg-slate-200/80 dark:bg-slate-600 text-slate-600 dark:text-slate-300 font-mono">{{ $node->code }}</span>
        @endif
        @if(! $node->actif)
        <span class="text-xs px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200">Inactif</span>
        @endif
        @if($node->confidentiel)
        <span class="text-xs px-2 py-0.5 rounded bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">Confidentiel</span>
        @endif
        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $node->documents_count }} doc(s)</span>
        <div class="ml-auto flex flex-wrap items-center gap-1">
            <a href="{{ route('parametres.plan-classement.create', ['parent_id' => $node->id]) }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/30">+ Sous-dossier</a>
            <a href="{{ route('parametres.plan-classement.edit', $node) }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Modifier</a>
            <a href="{{ route('dossiers.show', $node) }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Voir</a>
        </div>
    </div>
    @if($node->relationLoaded('treeChildren') && $node->treeChildren->isNotEmpty())
    <ul class="mt-2 ml-4 pl-3 border-l-2 border-emerald-200/80 dark:border-emerald-800 space-y-2">
        @foreach($node->treeChildren as $child)
            @include('parametres.plan-classement._tree-node', ['node' => $child])
        @endforeach
    </ul>
    @endif
</li>
