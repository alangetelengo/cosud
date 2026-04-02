@extends('layouts.app')

@section('page-title', 'Nouvelle étape de workflow')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 hover:text-emerald-600">Paramètres</a>
        <span class="text-slate-300">/</span>
        <a href="{{ route('parametres.workflow.index') }}" class="text-slate-500 hover:text-emerald-600">Workflow</a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Créer</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        <form action="{{ route('parametres.workflow.store') }}" method="POST" class="space-y-6">
            @csrf
            @include('parametres.workflow._fields', ['etape' => null, 'types' => $types, 'fonctions' => $fonctions, 'services' => $services, 'utilisateurs' => $utilisateurs, 'etapes' => $etapes])
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Enregistrer</button>
                <a href="{{ route('parametres.workflow.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
    {{-- Bloc 2 : Aide (droite) – panneau fixe --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Nom</strong> — Libellé affiché pour l'étape (ex. : Validation chef de service).</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Code</strong> — Identifiant technique unique (ex. : validation_cs). Utilisé par les règles métier.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Ordre</strong> — Position dans le flux (1, 2, 3…). Détermine l'ordre d'affichage et d'exécution.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Type de document</strong> — Si « Global », l'étape s'applique à tous les types. Sinon, elle ne s'applique qu'au type sélectionné.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Validation hiérarchique</strong> — Active la chaîne automatique depuis l'organigramme : responsable de la structure du créateur → parent → … → DG.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Rôle requis</strong> — Si non hiérarchique : indiquez le nom du rôle Spatie (admin, dg, etc.) requis pour valider.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Validateur spécifique</strong> — Désigne une personne précise comme validateur, au lieu d'un rôle ou de la hiérarchie.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Étape suivante</strong> — Étape qui vient après celle-ci. « Aucune » = dernière étape du flux.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Dernière étape</strong> — Cochez si cette étape clôture le workflow (validation finale).</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Actif</strong> — Désactivez pour masquer l'étape sans la supprimer.</li>
        </ul>
    </aside>
</div>
@endsection
