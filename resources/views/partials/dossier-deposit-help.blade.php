{{-- Aide contextuelle : liste des dossiers au dépôt / édition document --}}
<div role="note" aria-label="Information sur la liste des dossiers" class="mt-3 flex gap-3.5 rounded-xl border border-emerald-200/80 dark:border-emerald-700/60 bg-gradient-to-br from-emerald-100 via-emerald-50 to-teal-50 dark:from-emerald-950/70 dark:via-emerald-950/50 dark:to-slate-900/40 px-4 py-3.5 shadow-sm ring-1 ring-emerald-200/50 dark:ring-emerald-800/40">
    <div class="flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-full bg-emerald-200/70 text-emerald-700 dark:bg-emerald-800/80 dark:text-emerald-300" aria-hidden="true">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100 tracking-tight">Autorisations de classement</p>
        <p class="mt-1 text-xs sm:text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Seuls les dossiers pour lesquels vous disposez d’un <span class="font-medium text-slate-700 dark:text-slate-300">droit de dépôt</span> figurent dans cette liste.
        </p>
        <ul class="mt-2.5 space-y-2 text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed list-none p-0 m-0">
            <li class="flex gap-2.5">
                <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500 dark:bg-emerald-400 shadow-sm" aria-hidden="true"></span>
                <span><span class="font-medium text-slate-700 dark:text-slate-300">Mon espace personnel</span> (racine et sous-dossiers) est listé en premier lorsqu’il existe.</span>
            </li>
            <li class="flex gap-2.5">
                <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500 dark:bg-emerald-400 shadow-sm" aria-hidden="true"></span>
                <span>Emplacements du <span class="font-medium text-slate-700 dark:text-slate-300">plan de classement</span> (structure) ensuite, lorsque vous y avez le droit de dépôt.</span>
            </li>
            <li class="flex gap-2.5">
                <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500 dark:bg-emerald-400 shadow-sm" aria-hidden="true"></span>
                <span>Dossiers partagés avec le droit <span class="font-medium text-slate-700 dark:text-slate-300">Écriture (dépôt)</span> — un partage en lecture seule n’ouvre pas le dépôt depuis cet écran.</span>
            </li>
        </ul>
    </div>
</div>
