@extends('layouts.app')
@section('content-container-class', 'max-w-4xl mx-auto px-4')
@section('page-title', 'Nouveau circuit courrier')

@section('content')
<form method="post" action="{{ route('parametres.circuits-courriers.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 shadow-sm space-y-6">
    @csrf
    @include('parametres.circuits-courriers.partials.form')
    <div class="flex gap-3">
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-semibold">Enregistrer</button>
        <a href="{{ route('parametres.circuits-courriers.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 font-semibold no-underline text-slate-700">Annuler</a>
    </div>
</form>
@endsection
