@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Circuits courriers')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Circuits courriers</span>
    </nav>
@endsection

@section('btn-create')
    <a href="{{ route('parametres.circuits-courriers.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm no-underline">
        Nouveau circuit
    </a>
@endsection

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800">{{ session('error') }}</div>
    @endif

    <p class="text-sm text-slate-600 dark:text-slate-400 max-w-3xl">
        Définissez les circuits métier par type de dossier courrier (facture, courrier général…). Chaque circuit est une chaîne d’étapes paramétrables (acteur, action, mouvement).
    </p>

    <div class="space-y-4">
        @forelse($circuits as $circuit)
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
            <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 dark:border-slate-700">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $circuit->libelle }}</h3>
                        <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700">{{ $circuit->code }}</span>
                        @if($circuit->actif)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Actif</span>
                        @else
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-200 text-slate-600">Inactif</span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-500 mt-1">{{ $circuit->description }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $circuit->etapes_count }} étape(s) · sens initial {{ $circuit->sens_initial }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('parametres.circuits-courriers.edit', $circuit) }}" class="px-3 py-1.5 rounded-lg border border-slate-300 text-sm font-semibold no-underline text-slate-700 hover:bg-slate-50">Modifier</a>
                    <form method="post" action="{{ route('parametres.circuits-courriers.destroy', $circuit) }}" onsubmit="return confirm('Supprimer ce circuit ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-3 py-1.5 rounded-lg border border-red-200 text-sm font-semibold text-red-700 hover:bg-red-50">Supprimer</button>
                    </form>
                </div>
            </div>
            <ol class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($circuit->etapes as $etape)
                <li class="px-5 py-3 flex gap-3 text-sm">
                    <span class="font-bold text-emerald-700 w-6">{{ $etape->ordre }}.</span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $etape->nom }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ $etape->libelleActeur() }} · {{ $etape->libelleAction() }}
                            @if($etape->mouvement !== 'aucun') · {{ $etape->libelleMouvement() }} @endif
                            @if($etape->est_finale) · <span class="text-amber-700 font-semibold">finale</span> @endif
                        </div>
                    </div>
                </li>
                @endforeach
            </ol>
        </div>
        @empty
        <div class="p-10 text-center rounded-2xl border border-dashed border-slate-300 text-slate-500">
            Aucun circuit. Créez-en un ou exécutez <code class="text-xs">php artisan db:seed --class=CircuitCourrierSeeder</code>
        </div>
        @endforelse
    </div>
</div>
@endsection
