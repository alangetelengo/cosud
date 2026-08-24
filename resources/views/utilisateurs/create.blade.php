@extends('layouts.app')

@section('page-title', 'Nouvel utilisateur')
@section('page-title-info', 'Créer un compte utilisateur')

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
    <form id="form-utilisateur-create" action="{{ route('utilisateurs.store') }}" method="POST">
        @csrf
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Email *</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Email professionnel</label>
                <input type="email" name="email_professionnel" value="{{ old('email_professionnel') }}" placeholder="Pour les emails 2FA, notifications..."
                    class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('email_professionnel') border-red-500 @enderror">
                @error('email_professionnel')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Utilisé pour l'envoi du QR code 2FA et des notifications métier.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Téléphone (pour SMS)</label>
                <input type="text" name="telephone" value="{{ old('telephone') }}" placeholder="+242 06 XXX XX XX"
                    class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('telephone') border-red-500 @enderror">
                @error('telephone')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Numéro mobile Congo utilisé pour les SMS métier (optionnel). Ex. +242 06 XXX XX XX.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Mot de passe *</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('password') border-red-500 @enderror">
                @error('password')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Confirmer le mot de passe *</label>
                <input type="password" name="password_confirmation" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Rôle *</label>
                <select name="role" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('role') border-red-500 @enderror">
                    @foreach($roles as $role)
                    <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
                @error('role')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Structure</label>
                <select name="structure_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('structure_id') border-red-500 @enderror">
                    <option value="">— Aucune —</option>
                    @foreach($structures as $s)
                    <option value="{{ $s->id }}" {{ (string) old('structure_id') === (string) $s->id ? 'selected' : '' }}>{{ $s->nom }} ({{ $s->code }})</option>
                    @endforeach
                </select>
                @error('structure_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" value="1" {{ old('actif', true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Compte actif</span>
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 ml-6">Un compte désactivé ne peut plus se connecter.</p>
            </div>
        </div>
        <div class="mt-8 flex gap-4">
            <button type="submit" id="btn-utilisateur-create-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Enregistrer</button>
            <a href="{{ route('utilisateurs.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
        </div>
    </form>
    </div>
    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Informations</strong> — Le nom et l'email sont obligatoires. L'email professionnel est utilisé pour l'envoi du QR code 2FA et des notifications métier. Le téléphone permet d'envoyer des alertes SMS.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Rôle</strong> — Admin : accès complet. Utilisateur : dépôt, consultation et modification de ses documents. Le rôle détermine les permissions.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Compte actif</strong> — Désactiver empêche la connexion sans supprimer le compte.</li>
        </ul>
    </aside>
</div>
@push('styles')
<style>
/* Spinner chargement (identique page login) */
#btn-utilisateur-create-submit .btn-submit-spinner,
#btn-utilisateur-create-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: user-create-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes user-create-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush
@push('scripts')
<script>
(function () {
    var form = document.getElementById('form-utilisateur-create');
    var btn = document.getElementById('btn-utilisateur-create-submit');
    if (!form || !btn) return;
    form.addEventListener('submit', function () {
        if (btn.dataset.loading === '1') return;
        btn.dataset.loading = '1';
        btn.innerHTML = '<span class="btn-submit-spinner"></span> Enregistrement...';
        btn.disabled = true;
    });
})();
</script>
@endpush
@endsection
