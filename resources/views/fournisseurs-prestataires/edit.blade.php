@extends('layouts.app')

@section('page-title', 'Modifier — '.$fiche->nom)
@section('page-title-info', 'Référentiel fournisseurs / prestataires')

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="max-w-2xl">
    <form method="post" action="{{ route('fournisseurs-prestataires.update', $fiche) }}"
          data-loading-text="Enregistrement..."
          class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm p-5 space-y-4">
        @csrf
        @method('PUT')
        @include('fournisseurs-prestataires.partials.form', ['fiche' => $fiche])
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">Enregistrer</button>
            <a href="{{ $retourUrl }}" class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm font-semibold no-underline text-slate-700 dark:text-slate-200">Annuler</a>
        </div>
    </form>
</div>
@endsection
