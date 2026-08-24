@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Courriers — '.($sensCode === 'depart' ? 'Départ' : 'Arrivée'))
@section('page-title-info', $sensCode === 'depart' ? 'Registre des courriers sortants' : 'Registre des courriers entrants')

@section('btn-create')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ $sensCode === 'depart' ? route('courriers.registres.depart') : route('courriers.registres.arrivee') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm no-underline transition-colors">
            <svg class="w-4.5 h-4.5 w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            Registre
        </a>
        @can('create', App\Models\Courrier::class)
        <a href="{{ route('courriers.create', ['sens' => $sensCode]) }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-semibold hover:from-emerald-700 hover:to-teal-700 shadow-md hover:shadow-lg transition-all no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau courrier {{ $sensCode === 'depart' ? 'départ' : 'arrivée' }}
        </a>
        @endcan
    </div>
@endsection

@section('content')
@php
    $isDepart = $sensCode === 'depart';
@endphp

<div class="space-y-5">
    @include('partials.flash-session')

    {{-- Bandeau onglets + recherche --}}
    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-3 border-b border-slate-100 dark:border-slate-700 bg-gradient-to-r from-slate-50 to-white dark:from-slate-900/50 dark:to-slate-800">
            <div class="inline-flex p-1 rounded-xl bg-slate-100/90 dark:bg-slate-900/60 border border-slate-200/70 dark:border-slate-700">
                <a href="{{ route('courriers.index', ['sens' => 'arrivee']) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-all {{ ! $isDepart ? 'bg-white dark:bg-slate-700 text-emerald-700 dark:text-emerald-300 shadow-sm ring-1 ring-emerald-100 dark:ring-emerald-800/40' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Arrivée
                    @if(($compteursNonLus['arrivee'] ?? 0) > 0)
                        <span class="inline-flex items-center justify-center min-w-[1.35rem] h-5 px-1.5 rounded-full text-[11px] font-bold {{ ! $isDepart ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' }}">{{ $compteursNonLus['arrivee'] }}</span>
                    @endif
                </a>
                <a href="{{ route('courriers.index', ['sens' => 'depart']) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-all {{ $isDepart ? 'bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 shadow-sm ring-1 ring-blue-100 dark:ring-blue-800/40' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 17h-4V7m0 0l-4 4m4-4l4 4"/></svg>
                    Départ
                    @if(($compteursNonLus['depart'] ?? 0) > 0)
                        <span class="inline-flex items-center justify-center min-w-[1.35rem] h-5 px-1.5 rounded-full text-[11px] font-bold {{ $isDepart ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200' }}">{{ $compteursNonLus['depart'] }}</span>
                    @endif
                </a>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                {{ $courriers->total() }} courrier{{ $courriers->total() > 1 ? 's' : '' }}
            </p>
        </div>

        <form method="get" class="p-4 sm:p-5">
            <input type="hidden" name="sens" value="{{ $sensCode }}">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher n° registre, objet, expéditeur, référence…"
                           class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900 pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition">
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-slate-800 dark:bg-slate-700 text-white text-sm font-semibold hover:bg-slate-900 dark:hover:bg-slate-600 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filtrer
                </button>
                @if(request('q'))
                <a href="{{ route('courriers.index', ['sens' => $sensCode]) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 no-underline hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Réinitialiser
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tableau --}}
    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2 {{ $isDepart ? 'bg-gradient-to-r from-blue-50/80 to-white dark:from-blue-950/30 dark:to-slate-800' : 'bg-gradient-to-r from-emerald-50/80 to-white dark:from-emerald-950/20 dark:to-slate-800' }}">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $isDepart ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                        {{ $isDepart ? 'Courriers départ' : 'Courriers arrivée' }}
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $courriers->total() }} résultat{{ $courriers->total() > 1 ? 's' : '' }}</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                        <th class="px-5 py-3.5 font-semibold">N° registre</th>
                        <th class="px-5 py-3.5 font-semibold">Date</th>
                        <th class="px-5 py-3.5 font-semibold">{{ $isDepart ? 'Destinataire' : 'Expéditeur' }}</th>
                        <th class="px-5 py-3.5 font-semibold">Objet</th>
                        <th class="px-5 py-3.5 font-semibold">Statut</th>
                        <th class="px-5 py-3.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($courriers as $c)
                    @php
                        $statutCode = strtolower((string) ($c->statutCourrier?->code ?? ''));
                        $estNonLu = ! (bool) ($c->est_lu ?? false);
                        $statutClasses = match (true) {
                            str_contains($statutCode, 'recu') || $statutCode === 'enregistre' => 'bg-sky-50 text-sky-800 border-sky-100 dark:bg-sky-900/30 dark:text-sky-200 dark:border-sky-800/50',
                            str_contains($statutCode, 'trait') || str_contains($statutCode, 'cours') => 'bg-amber-50 text-amber-800 border-amber-100 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-800/50',
                            str_contains($statutCode, 'exped') || str_contains($statutCode, 'envoye') || str_contains($statutCode, 'clos') || str_contains($statutCode, 'archive') => 'bg-emerald-50 text-emerald-800 border-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-800/50',
                            str_contains($statutCode, 'rejet') || str_contains($statutCode, 'annul') => 'bg-rose-50 text-rose-800 border-rose-100 dark:bg-rose-900/30 dark:text-rose-200 dark:border-rose-800/50',
                            default => 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-700/40 dark:text-slate-200 dark:border-slate-600',
                        };
                    @endphp
                    <tr class="group transition-colors {{ $loop->odd ? 'bg-emerald-100 hover:bg-emerald-200/80 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/45' : 'bg-amber-50 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/35' }}">
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="inline-flex items-center font-mono text-xs {{ $estNonLu ? 'font-bold' : 'font-semibold' }} text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-700/70 px-2.5 py-1 rounded-lg ring-1 ring-slate-200/80 dark:ring-slate-600/60">
                                {{ $c->numeroRegistreComplet() }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap tabular-nums {{ $estNonLu ? 'font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-600 dark:text-slate-300' }}">
                            {{ $c->date_reception?->format('d/m/Y') ?? $c->date_courrier?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5 max-w-[11rem] truncate {{ $estNonLu ? 'font-bold text-slate-900 dark:text-white' : 'font-medium text-slate-700 dark:text-slate-200' }}" title="{{ $isDepart ? ($c->destinataire_libelle ?? '') : ($c->expediteur_libelle ?? '') }}">
                            {{ $isDepart ? ($c->destinataire_libelle ?? '—') : ($c->expediteur_libelle ?? '—') }}
                        </td>
                        <td class="px-5 py-3.5 max-w-md {{ $estNonLu ? 'font-bold text-slate-900 dark:text-white' : 'text-slate-800 dark:text-slate-100' }}">
                            <span class="line-clamp-2 leading-snug" title="{{ $c->objet }}">{{ $c->objet }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $statutClasses }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                {{ $c->statutCourrier?->libelle ?? '—' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('courriers.show', $c) }}"
                                   class="inline-flex items-center justify-center min-w-[2.35rem] min-h-[2.35rem] p-2 rounded-xl text-sky-600 dark:text-sky-400 bg-sky-50/80 dark:bg-sky-900/20 hover:bg-sky-100 dark:hover:bg-sky-900/40 border border-sky-100/80 dark:border-sky-800/40 shadow-sm transition"
                                   title="Consulter">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span class="sr-only">Consulter</span>
                                </a>
                                @can('update', $c)
                                <a href="{{ route('courriers.edit', $c) }}"
                                   class="inline-flex items-center justify-center min-w-[2.35rem] min-h-[2.35rem] p-2 rounded-xl text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-600 border border-slate-200/80 dark:border-slate-600 shadow-sm transition"
                                   title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span class="sr-only">Modifier</span>
                                </a>
                                @elsecan('corriger', $c)
                                <a href="{{ route('courriers.edit', $c) }}"
                                   class="inline-flex items-center justify-center min-w-[2.35rem] min-h-[2.35rem] p-2 rounded-xl text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/25 hover:bg-amber-100 dark:hover:bg-amber-900/40 border border-amber-100 dark:border-amber-800/40 shadow-sm transition"
                                   title="Corriger l’enregistrement">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span class="sr-only">Corriger</span>
                                </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <div class="inline-flex flex-col items-center gap-3 text-slate-500">
                                <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-700/50 text-slate-400">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Aucun courrier pour le moment</p>
                                    <p class="text-xs mt-1 text-slate-500">Modifiez les filtres ou créez un nouvel enregistrement.</p>
                                </div>
                                @can('create', App\Models\Courrier::class)
                                <a href="{{ route('courriers.create', ['sens' => $sensCode]) }}" class="mt-1 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold no-underline hover:bg-emerald-700 shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Nouveau courrier
                                </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($courriers->hasPages())
    <div class="pt-1">
        {{ $courriers->links() }}
    </div>
    @endif
</div>
@endsection
