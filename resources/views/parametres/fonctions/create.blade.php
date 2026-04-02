@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Nouvelle fonction métier')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.fonctions.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Fonctions métier</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Nouvelle</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
        <form id="form-fonction-create" action="{{ route('parametres.fonctions.store') }}" method="POST">
            @csrf
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code *</label>
                    <input type="text" name="code" value="{{ old('code') }}" required pattern="[a-z0-9_]+" maxlength="50" placeholder="ex. chef_pole"
                        class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white font-mono @error('code') border-red-500 @enderror">
                    @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Libellé *</label>
                    <input type="text" name="libelle" value="{{ old('libelle') }}" required maxlength="255"
                        class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('libelle') border-red-500 @enderror">
                    @error('libelle')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="actif" value="0">
                        <input type="checkbox" name="actif" value="1" {{ old('actif', true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Actif</span>
                    </label>
                </div>
            </div>
            <div class="mt-8 flex gap-4">
                <button type="submit" id="btn-fonction-create-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Enregistrer</button>
                <a href="{{ route('parametres.fonctions.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
            </div>
        </form>
    </div>
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Code</strong> — Identifiant technique (lettres minuscules, chiffres, underscore). Référencé par les structures et les affectations utilisateurs.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Validation</strong> — Lorsqu’une structure définit cette fonction comme « fonction de validation », le titulaire est l’utilisateur affecté à la structure avec la même fonction.</li>
        </ul>
    </aside>
</div>
@push('styles')
<style>
#btn-fonction-create-submit .btn-submit-spinner,
#btn-fonction-create-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: fonction-create-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes fonction-create-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush
@push('scripts')
<script>
(function(){var f=document.getElementById('form-fonction-create'),b=document.getElementById('btn-fonction-create-submit');if(!f||!b)return;f.addEventListener('submit',function(){if(b.dataset.loading==='1')return;b.dataset.loading='1';b.innerHTML='<span class="btn-submit-spinner"></span> Enregistrement...';b.disabled=true;});})();
</script>
@endpush
@endsection
