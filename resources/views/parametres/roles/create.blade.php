@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Nouveau rôle')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.roles.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Rôles</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Nouveau</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0">
@php
    $groups = [
        'Documents' => array_filter($permissions->pluck('name')->toArray(), fn($p) => str_starts_with($p, 'documents.')),
        'Types de documents' => array_filter($permissions->pluck('name')->toArray(), fn($p) => str_starts_with($p, 'types-documents.')),
        'Recherche' => array_filter($permissions->pluck('name')->toArray(), fn($p) => str_starts_with($p, 'recherche.')),
        'Corbeille' => array_filter($permissions->pluck('name')->toArray(), fn($p) => str_starts_with($p, 'corbeille.')),
        'Dossiers' => array_filter($permissions->pluck('name')->toArray(), fn($p) => str_starts_with($p, 'dossiers.')),
        'Courriers' => array_filter($permissions->pluck('name')->toArray(), fn($p) => str_starts_with($p, 'courriers.')),
        'Utilisateurs' => array_filter($permissions->pluck('name')->toArray(), fn($p) => str_starts_with($p, 'utilisateurs.')),
    ];
    $allGrouped = collect($groups)->flatten()->toArray();
    $others = array_values(array_diff($permissions->pluck('name')->toArray(), $allGrouped));
    if (!empty($others)) { $groups['Autres'] = $others; }
    $labels = [
        'view' => 'Consulter', 'create' => 'Créer', 'edit' => 'Modifier', 'delete' => 'Supprimer',
        'view-confidentiel' => 'Voir confidentiel', 'create-structure' => 'Créer (structure)',
        'orienter' => 'Orienter', 'ventiler' => 'Ventiler', 'signer' => 'Signer', 'rejeter' => 'Rejeter',
        'transmettre' => 'Transmettre', 'archiver' => 'Archiver', 'recevoir' => 'Recevoir',
    ];
    $descriptions = config('permissions_descriptions', []);
@endphp

<form id="form-role-create" action="{{ route('parametres.roles.store') }}" method="POST" class="space-y-6">
    @csrf
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/30">
            <div class="max-w-md">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom du rôle *</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="ex. responsable, moderateur" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Minuscules, chiffres et tirets uniquement</p>
            </div>
        </div>
        <div class="p-6 space-y-8">
            @foreach($groups as $title => $perms)
                @if(!empty($perms))
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">{{ $title }}</h3>
                        <button type="button" onclick="toggleGroup(this)" data-target="{{ Str::slug($title) }}" class="text-xs font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400">Tout cocher / Décocher</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-group="{{ Str::slug($title) }}">
                        @foreach($perms as $p)
                        @php $part = Str::afterLast($p, '.'); $label = $labels[$part] ?? $part; $desc = $descriptions[$p] ?? null; @endphp
                        <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                            <input type="checkbox" name="permissions[]" value="{{ $p }}" {{ in_array($p, old('permissions', [])) ? 'checked' : '' }} class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 flex-shrink-0">
                            <div class="min-w-0">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300 block">{{ $label }}</span>
                                @if($desc)
                                <span class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 block">{{ $desc }}</span>
                                @endif
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    <div class="mt-8 flex gap-4">
        <button type="submit" id="btn-role-create-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Créer le rôle</button>
        <a href="{{ route('parametres.roles.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
    </div>
</form>
    </div>
    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Nom du rôle</strong> — Identifiant unique (ex. responsable, moderateur). Minuscules, chiffres et tirets uniquement.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Permissions</strong> — Cochez les actions que les utilisateurs ayant ce rôle pourront effectuer. Vous pouvez gérer le référentiel des permissions depuis la liste des rôles.</li>
        </ul>
    </aside>
</div>
@push('styles')
<style>
/* Spinner chargement (identique page login) */
#btn-role-create-submit .btn-submit-spinner,
#btn-role-create-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: role-create-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes role-create-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush
@push('scripts')
<script>
function toggleGroup(btn) {
    const target = btn.dataset.target;
    const container = document.querySelector(`[data-group="${target}"]`);
    if (!container) return;
    const checks = container.querySelectorAll('input[type="checkbox"]');
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => { c.checked = !allChecked; });
}
</script>
<script>
(function(){var f=document.getElementById('form-role-create'),b=document.getElementById('btn-role-create-submit');if(!f||!b)return;f.addEventListener('submit',function(){if(b.dataset.loading==='1')return;b.dataset.loading='1';b.innerHTML='<span class="btn-submit-spinner"></span> Enregistrement...';b.disabled=true;});})();
</script>
@endpush
@endsection
