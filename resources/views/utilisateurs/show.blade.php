@extends('layouts.app')

@use('App\Support\ReturnUrl')

@section('page-title', 'Détail utilisateur')
@section('page-title-info', $utilisateur->email)

@section('btn-create')
    @can('utilisateurs.edit')
    <a href="{{ route('utilisateurs.edit', ReturnUrl::propagate($utilisateur, ReturnUrl::validated(request()->query('return')))) }}#structures" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border-2 border-[#00b464]/40 text-[#00a055] dark:text-emerald-400 font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all no-underline">
        🏢 Structures & fonctions
    </a>
    <a href="{{ route('utilisateurs.edit', ReturnUrl::propagate($utilisateur, ReturnUrl::validated(request()->query('return')))) }}"
       x-data="{ loading: false }"
       @click.prevent="if(!loading){ loading=true; window.location.href=$el.href }"
       class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] transition-all no-underline"
       :class="{ 'pointer-events-none opacity-70': loading }">
        <span x-show="!loading" class="inline-flex items-center gap-2"><span>✏️</span> Modifier</span>
        <span x-show="loading" x-cloak class="inline-flex items-center gap-2"><span class="link-modifier-spinner" style="width:1em;height:1em;flex-shrink:0"></span> Chargement...</span>
    </a>
    @endcan
@endsection

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 overflow-hidden max-w-2xl">
    <div class="p-8">
        <div class="flex items-center gap-6">
            <img src="https://ui-avatars.com/api/?name={{ urlencode($utilisateur->name) }}&background=00b464&color=fff&size=96" alt="Avatar" class="w-24 h-24 rounded-full flex-shrink-0 border-2 border-[#00b464]/30">
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $utilisateur->name }}</h2>
                <p class="text-slate-600 dark:text-slate-400 mt-0.5">{{ $utilisateur->email }}</p>
                @if($utilisateur->email_professionnel)
                <p class="text-slate-600 dark:text-slate-400 text-sm mt-0.5">📧 Pro : {{ $utilisateur->email_professionnel }}</p>
                @endif
                <p class="text-slate-600 dark:text-slate-400 text-sm mt-0.5">
                    Tél. SMS :
                    @if($utilisateur->telephone)
                        <span class="font-mono tabular-nums">+{{ $utilisateur->telephone }}</span>
                    @else
                        <span class="text-slate-400">non renseigné</span>
                    @endif
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $utilisateur->hasRole('admin') ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200' : 'bg-slate-100 dark:bg-slate-600 text-slate-700 dark:text-slate-300' }}">
                        {{ $utilisateur->roles->first()?->name ?? 'Aucun rôle' }}
                    </span>
                    @php $actif = $utilisateur->actif ?? true; @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $actif ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-600 text-slate-500 dark:text-slate-400' }}">
                        {{ $actif ? 'Actif' : 'Inactif' }}
                    </span>
                    @if($utilisateur->structure)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-700/50">
                        🏢 {{ $utilisateur->structure->nom }}
                    </span>
                    @endif
                </div>
            </div>
        </div>

        <dl class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase">Compte créé le</dt>
                <dd class="mt-1 text-slate-800 dark:text-slate-200">{{ $utilisateur->created_at->format('d/m/Y à H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase">Dernière mise à jour</dt>
                <dd class="mt-1 text-slate-800 dark:text-slate-200">{{ $utilisateur->updated_at->format('d/m/Y à H:i') }}</dd>
            </div>
            @if($utilisateur->email_verified_at)
            <div>
                <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase">Email vérifié</dt>
                <dd class="mt-1 text-emerald-600 dark:text-emerald-400">Oui ({{ $utilisateur->email_verified_at->format('d/m/Y') }})</dd>
            </div>
            @endif
        </dl>

        <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-600 flex flex-wrap gap-4">
            @can('utilisateurs.edit')
            <a href="{{ route('utilisateurs.edit', $utilisateur) }}#structures" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border-2 border-[#00b464]/40 text-[#00a055] dark:text-emerald-400 font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all no-underline">
                🏢 Structures & fonctions
            </a>
            <a href="{{ route('utilisateurs.edit', $utilisateur) }}"
               x-data="{ loading: false }"
               @click.prevent="if(!loading){ loading=true; window.location.href=$el.href }"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] transition-all no-underline"
               :class="{ 'pointer-events-none opacity-70': loading }">
                <span x-show="!loading" class="inline-flex items-center gap-2"><span>✏️</span> Modifier</span>
                <span x-show="loading" x-cloak class="inline-flex items-center gap-2"><span class="link-modifier-spinner" style="width:1em;height:1em;flex-shrink:0"></span> Chargement...</span>
            </a>
            @endcan
            @can('utilisateurs.delete')
            @if($utilisateur->id !== auth()->id())
            <form action="{{ route('utilisateurs.destroy', $utilisateur) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="button" onclick="flashAlert('Supprimer définitivement cet utilisateur ? Cette action est irréversible.', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 transition-all">
                    <span>🗑️</span> Supprimer
                </button>
            </form>
            @endif
            @endcan
            <a href="{{ $retourUrl }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all no-underline">
                ← Retour à la liste
            </a>
        </div>
    </div>
</div>
@endsection
