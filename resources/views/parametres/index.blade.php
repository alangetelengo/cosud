@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Paramètres')
@section('page-title-info', "Centre d'administration et de configuration COSUD")

@push('styles')
<style>
@media (min-width: 768px) {
    #parametres-grid-2x2 {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 1rem !important;
    }
}
</style>
@endpush

@section('content')
{{-- Grille 2x2 : Bloc 1 | Bloc 2 / Bloc 3 | Bloc 3 --}}
<div id="parametres-grid-2x2" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- BLOC 1 (haut gauche) : Configuration du système --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <p class="text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider">Administration</p>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mt-1">Configuration du système</h2>
            <p class="text-slate-600 dark:text-slate-400 mt-1 text-sm">Gérez l'organisation, les documents et les accès</p>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('parametres.cosud-acces') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">🔑</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-emerald-700 dark:group-hover:text-emerald-400">Politique d’accès documents</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Lecture du dossier lors d’un partage / envoi (désactivé par défaut)</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    {{-- BLOC 2 (haut droite) : Statistiques --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Vue d'ensemble</h3>
        </div>
        <div class="p-4 flex flex-wrap gap-3">
            <div class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-900/60 flex-1 min-w-[100px]">
                <span class="text-xl">📄</span>
                <div>
                    <span class="text-xl font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">{{ \App\Models\Document::count() }}</span>
                    <span class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Documents</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-sky-50 dark:bg-sky-950/40 border border-sky-100 dark:border-sky-900/60 flex-1 min-w-[100px]">
                <span class="text-xl">📂</span>
                <div>
                    <span class="text-xl font-bold text-sky-700 dark:text-sky-300 tabular-nums">{{ \App\Models\Dossier::where('actif', true)->count() }}</span>
                    <span class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Dossiers</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-violet-50 dark:bg-violet-950/40 border border-violet-100 dark:border-violet-900/60 flex-1 min-w-[100px]">
                <span class="text-xl">👤</span>
                <div>
                    <span class="text-xl font-bold text-violet-700 dark:text-violet-300 tabular-nums">{{ \App\Models\User::count() }}</span>
                    <span class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Utilisateurs</span>
                </div>
            </div>
        </div>
    </div>

    {{-- BLOC 3 (bas gauche) : Organisation + Documents --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 bg-emerald-50/80 dark:bg-emerald-950/30">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-sm">🏢</span>
                Organisation & Documents
            </h3>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="{{ route('parametres.structures.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">🏛️</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-emerald-700 dark:group-hover:text-emerald-400">Organigramme</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Hiérarchie, fonctions et titulaires</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.fonctions.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-700 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">🎖️</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-violet-700 dark:group-hover:text-violet-400">Fonctions métier</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Référentiel validation hiérarchique</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.plan-classement.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">📂</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-emerald-700 dark:group-hover:text-emerald-400">Plan de classement</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Arborescence des dossiers</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.types-dossiers.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">📑</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-emerald-700 dark:group-hover:text-emerald-400">Types de dossiers</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Catégories et couleurs</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.categories-depense.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">🏷️</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-emerald-700 dark:group-hover:text-emerald-400">Catégories de dépense</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Référentiel Suivi de dépense</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('types-documents.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-sky-300 dark:hover:border-sky-700 hover:bg-sky-50/50 dark:hover:bg-sky-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">📄</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-sky-700 dark:group-hover:text-sky-400">Types de documents</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Extensions, workflows</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-sky-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.workflow.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-sky-300 dark:hover:border-sky-700 hover:bg-sky-50/50 dark:hover:bg-sky-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">🔄</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-sky-700 dark:group-hover:text-sky-400">Workflow</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Validation hiérarchique</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-sky-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.circuits-courriers.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-700 hover:bg-amber-50/50 dark:hover:bg-amber-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">✉️</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-amber-700 dark:group-hover:text-amber-400">Circuits courriers</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Parcours métier paramétrables</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.types-courriers.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-700 hover:bg-amber-50/50 dark:hover:bg-amber-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">📬</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-amber-700 dark:group-hover:text-amber-400">Types de courriers</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Association type → circuit</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('parametres.types-metadonnees.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-sky-300 dark:hover:border-sky-700 hover:bg-sky-50/50 dark:hover:bg-sky-950/20 transition-all no-underline">
                <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">🏷️</span>
                <div class="min-w-0 flex-1">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-sky-700 dark:group-hover:text-sky-400">Métadonnées</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Extraction PDF</span>
                </div>
                <svg class="w-4 h-4 text-slate-300 group-hover:text-sky-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    {{-- BLOC 3 (bas droite) : Sécurité & Audit + Système + Organigramme --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col">
        <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 bg-violet-50/80 dark:bg-violet-950/30">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-violet-500/20 flex items-center justify-center text-violet-600 dark:text-violet-400 text-sm">🔐</span>
                Sécurité, Audit & Système
            </h3>
        </div>
        <div class="p-4 flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4 min-h-0">
            {{-- Liens Sécurité --}}
            <div class="space-y-2">
                <a href="{{ route('utilisateurs.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-700 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 transition-all no-underline">
                    <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">👤</span>
                    <div class="min-w-0 flex-1">
                        <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-violet-700 dark:group-hover:text-violet-400">Utilisateurs</span>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 group-hover:text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('parametres.roles.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-700 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 transition-all no-underline">
                    <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">🛡️</span>
                    <div class="min-w-0 flex-1">
                        <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-violet-700 dark:group-hover:text-violet-400">Rôles & permissions</span>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 group-hover:text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('parametres.audit.index') }}" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-700 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 transition-all no-underline">
                    <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg flex-shrink-0">📜</span>
                    <div class="min-w-0 flex-1">
                        <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm block group-hover:text-violet-700 dark:group-hover:text-violet-400">Journal d'audit</span>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 group-hover:text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            {{-- Infos Système + Organigramme --}}
            <div class="space-y-3">
                <div class="text-xs space-y-1.5 py-2 px-3 rounded-lg bg-slate-50 dark:bg-slate-700/40">
                    <div class="flex justify-between"><span class="text-slate-500">App</span><span class="font-medium">{{ config('app.name') }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Laravel</span><span class="font-mono">{{ app()->version() }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Env</span><span class="px-1.5 py-0.5 rounded text-xs {{ config('app.env') === 'production' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-700' }}">{{ config('app.env') }}</span></div>
                </div>
                <div>
                    <a href="{{ route('parametres.structures.index') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Organigramme ACSI →</a>
                    @php $dg = $structures->firstWhere('code', 'DG'); @endphp
                    @if($dg)
                    <div class="mt-1.5 pl-2 border-l-2 border-slate-200 dark:border-slate-600 space-y-1">
                        @foreach($structures->where('parent_id', $dg->id)->take(3) as $enfant)
                        <div class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate">{{ $enfant->nom }}</div>
                        @endforeach
                        @if($structures->where('parent_id', $dg->id)->count() > 3)
                        <span class="text-xs text-slate-500">+{{ $structures->where('parent_id', $dg->id)->count() - 3 }} autres</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
