@extends('layouts.app')
@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')
@section('page-title', 'Corriger le courrier n° '.$courrier->numeroRegistreComplet())
@section('page-title-info', $courrier->estArrivee() ? 'Correction de l’enregistrement d’arrivée' : 'Correction avant retransmission au directeur')

@php
    $field = 'w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition-shadow';
    $label = 'block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5';
@endphp

@section('content')
@include('partials.form-submit-loading')

@if(session('error'))
<div class="mb-5 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
@endif

@if($errors->any())
<div class="mb-5 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 text-red-800 text-sm">
    <ul class="list-disc list-inside text-xs space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="post" action="{{ route('courriers.update', $courrier) }}" class="w-full space-y-5" data-loading-text="Enregistrement...">
    @csrf
    @method('PUT')

    <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Informations générales</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                @if($courrier->estArrivee())
                Corrigez une erreur de saisie sans relancer le circuit.
                @else
                Corrigez avant de retransmettre au directeur.
                @endif
            </p>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="{{ $label }}">Objet <span class="text-red-500 normal-case tracking-normal">*</span></label>
                <input type="text" name="objet" value="{{ old('objet', $courrier->objet) }}" required class="{{ $field }}">
                @error('objet')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Type</label>
                    <select name="type_courrier_id" class="{{ $field }}">
                        <option value="">—</option>
                        @foreach($types as $t)<option value="{{ $t->id }}" @selected(old('type_courrier_id', $courrier->type_courrier_id) == $t->id)>{{ $t->libelle }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Priorité</label>
                    <select name="priorite_courrier_id" class="{{ $field }}">
                        <option value="">Normale</option>
                        @foreach($priorites as $p)<option value="{{ $p->id }}" @selected(old('priorite_courrier_id', $courrier->priorite_courrier_id) == $p->id)>{{ $p->libelle }}</option>@endforeach
                    </select>
                </div>
            </div>

            @if($courrier->estArrivee())
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Date de réception</label>
                    <input type="date" name="date_reception" value="{{ old('date_reception', $courrier->date_reception?->format('Y-m-d')) }}" class="{{ $field }}">
                    @error('date_reception')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">Date du courrier</label>
                    <input type="date" name="date_courrier" value="{{ old('date_courrier', $courrier->date_courrier?->format('Y-m-d')) }}" class="{{ $field }}">
                    @error('date_courrier')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Expéditeur</label>
                <input type="text" name="expediteur_libelle" value="{{ old('expediteur_libelle', $courrier->expediteur_libelle) }}" class="{{ $field }}">
                @error('expediteur_libelle')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">E-mail expéditeur <span class="text-slate-400 normal-case tracking-normal font-medium">(optionnel)</span></label>
                    <input type="email" name="expediteur_email" value="{{ old('expediteur_email', $courrier->expediteur_email) }}" class="{{ $field }}" placeholder="contact@exemple.cg">
                    @error('expediteur_email')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">Téléphone expéditeur <span class="text-slate-400 normal-case tracking-normal font-medium">(optionnel, SMS)</span></label>
                    <input type="text" name="expediteur_telephone" value="{{ old('expediteur_telephone', $courrier->expediteur_telephone) }}" class="{{ $field }}" placeholder="+24206…">
                    @error('expediteur_telephone')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">N° fulgurant <span class="text-emerald-600 normal-case tracking-normal font-medium">(recommandé)</span></label>
                    <input type="text" name="numero_fulgurant" value="{{ old('numero_fulgurant', $courrier->numero_fulgurant) }}" class="{{ $field }}">
                    <p class="text-xs text-slate-500 mt-1.5">Un même n° ne peut pas être enregistré deux fois.</p>
                    @error('numero_fulgurant')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">Référence</label>
                    <input type="text" name="reference" value="{{ old('reference', $courrier->reference) }}" class="{{ $field }}">
                    @error('reference')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Nombre de pièces</label>
                    <input type="number" name="nombre_pieces" min="0" value="{{ old('nombre_pieces', $courrier->nombre_pieces) }}" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">N° archives</label>
                    <input type="text" name="numero_archives" value="{{ old('numero_archives', $courrier->numero_archives) }}" class="{{ $field }}">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Observations</label>
                <textarea name="observations" rows="3" class="{{ $field }}">{{ old('observations', $courrier->observations) }}</textarea>
            </div>
            @else
            <div>
                <label class="{{ $label }}">Date du courrier</label>
                <input type="date" name="date_courrier" value="{{ old('date_courrier', $courrier->date_courrier?->format('Y-m-d')) }}" class="{{ $field }}">
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-400 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 px-4 py-3">
                Le destinataire sera choisi après validation du directeur.
            </p>
            @endif
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-3 pb-6">
        <button type="submit" data-loading-text="Enregistrement..." class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
            Enregistrer les corrections
        </button>
        <a href="{{ route('courriers.show', $courrier) }}" class="inline-flex items-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold no-underline text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
            Annuler
        </a>
    </div>
</form>
@endsection
