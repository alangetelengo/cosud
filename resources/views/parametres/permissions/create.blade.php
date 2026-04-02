@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Nouvelle permission')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.roles.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Rôles</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.permissions.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Permissions</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Nouvelle</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
        <form id="form-permission-create" action="{{ route('parametres.permissions.store') }}" method="POST">
            @csrf
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom de la permission *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="ex. documents.view, dossiers.create" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white font-mono @error('name') border-red-500 @enderror">
                    @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Format : minuscules, chiffres, tirets et points uniquement (ex. module.action)</p>
                </div>
            </div>
            <div class="mt-8 flex gap-4">
                <button type="submit" id="btn-permission-create-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Créer</button>
                <a href="{{ route('parametres.permissions.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Format</strong> — Utilisez la convention module.action (ex. documents.view, dossiers.create, utilisateurs.delete).</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Utilisation</strong> — Une fois créée, la permission pourra être associée aux rôles depuis la page de modification de chaque rôle.</li>
        </ul>
    </aside>
</div>
@push('styles')
<style>
/* Spinner chargement (identique page login) */
#btn-permission-create-submit .btn-submit-spinner,
#btn-permission-create-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: perm-create-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes perm-create-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush
@push('scripts')
<script>
(function(){var f=document.getElementById('form-permission-create'),b=document.getElementById('btn-permission-create-submit');if(!f||!b)return;f.addEventListener('submit',function(){if(b.dataset.loading==='1')return;b.dataset.loading='1';b.innerHTML='<span class="btn-submit-spinner"></span> Enregistrement...';b.disabled=true;});})();
</script>
@endpush
@endsection
