@extends('layouts.app')

@section('page-title', 'Modifier l\'utilisateur')
@section('page-title-info', $utilisateur->email)

@section('content')
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-5 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200/80 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-4 shadow-sm">
    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold">✓</span>
    <span class="flex-1 font-medium">{{ session('success') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
</div>
@endif
@if(session('error'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-5 p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200/80 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-4 shadow-sm">
    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/50 flex items-center justify-center text-red-600 dark:text-red-400 font-bold">!</span>
    <span class="flex-1 font-medium">{{ session('error') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-red-200/50 dark:hover:bg-red-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
</div>
@endif

<div class="w-full max-w-none" x-cloak
     x-data="{
        tab: (typeof window !== 'undefined' && window.location.hash === '#structures') ? 'structures' : 'compte',
        setTab(t) {
            this.tab = t;
            const base = {{ json_encode(route('utilisateurs.edit', $utilisateur)) }};
            if (t === 'structures') {
                history.replaceState(null, '', base + '#structures');
            } else {
                history.replaceState(null, '', base);
            }
        }
     }"
     @hashchange.window="tab = (window.location.hash === '#structures') ? 'structures' : 'compte'">

    <div class="flex flex-wrap gap-2 mb-6 border-b border-slate-200 dark:border-slate-600 pb-1">
        <button type="button"
                @click="setTab('compte')"
                :class="tab === 'compte' ? 'bg-[#00b464] text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                class="px-5 py-2.5 rounded-t-lg font-semibold text-sm transition-colors">
            Compte & accès
        </button>
        <button type="button"
                @click="setTab('structures')"
                :class="tab === 'structures' ? 'bg-[#00b464] text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                class="px-5 py-2.5 rounded-t-lg font-semibold text-sm transition-colors">
            Structures & fonctions
        </button>
    </div>

    {{-- Onglet Compte --}}
    <div x-show="tab === 'compte'" class="flex flex-row gap-8 w-full items-stretch">
        <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
            <form id="form-utilisateur-edit" action="{{ route('utilisateurs.update', $utilisateur) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom *</label>
                        <input type="text" name="name" value="{{ old('name', $utilisateur->name) }}" required
                            class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('name') border-red-500 @enderror">
                        @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Email *</label>
                        <input type="email" name="email" value="{{ old('email', $utilisateur->email) }}" required
                            class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('email') border-red-500 @enderror">
                        @error('email')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Email professionnel</label>
                        <input type="email" name="email_professionnel" value="{{ old('email_professionnel', $utilisateur->email_professionnel) }}" placeholder="Pour les emails 2FA, notifications..."
                            class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('email_professionnel') border-red-500 @enderror">
                        @error('email_professionnel')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Utilisé pour l'envoi du QR code 2FA et des notifications métier.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Téléphone (pour SMS)</label>
                        <input type="text" name="telephone" value="{{ old('telephone', $utilisateur->telephone ? '+'.$utilisateur->telephone : '') }}" placeholder="+242 06 XXX XX XX"
                            class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('telephone') border-red-500 @enderror">
                        @error('telephone')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Numéro mobile Congo utilisé pour les SMS métier (optionnel). Vide = pas de SMS à cet utilisateur.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                        <input type="password" name="password"
                            class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('password') border-red-500 @enderror">
                        @error('password')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Confirmer le mot de passe</label>
                        <input type="password" name="password_confirmation" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Rôle *</label>
                        <select name="role" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('role') border-red-500 @enderror">
                            @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ old('role', $utilisateur->roles->first()?->name) == $role->name ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Structure (appartenance principale)</label>
                        <select name="structure_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('structure_id') border-red-500 @enderror">
                            <option value="">— Aucune —</option>
                            @foreach($structures as $s)
                            <option value="{{ $s->id }}" {{ (string) old('structure_id', $utilisateur->structure_id) === (string) $s->id ? 'selected' : '' }}>{{ $s->nom }} ({{ $s->code }})</option>
                            @endforeach
                        </select>
                        @error('structure_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Pour les <strong>fonctions métier par structure</strong> et la validation hiérarchique, utilisez l’onglet
                            <button type="button" @click="setTab('structures')" class="text-[#00a055] dark:text-emerald-400 font-semibold hover:underline">Structures & fonctions</button>.
                        </p>
                    </div>
                    <div>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="hidden" name="documents_view_hierarchique" value="0">
                            <input type="checkbox" name="documents_view_hierarchique" value="1" {{ old('documents_view_hierarchique', $utilisateur->hasDirectPermission('documents.view-hierarchique') ? 1 : 0) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Vue hiérarchique (organisation)</span>
                        </label>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 ml-6">Permet de voir les dossiers et documents des collègues de la même structure ou des structures gérées. Permission directe sur ce compte (en plus du rôle).</p>
                    </div>
                    <div>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="hidden" name="actif" value="0">
                            <input type="checkbox" name="actif" value="1" {{ old('actif', $utilisateur->actif ?? true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Compte actif</span>
                        </label>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 ml-6">Désactiver pour empêcher la connexion sans supprimer le compte.</p>
                    </div>
                </div>
                <div class="mt-8 flex flex-wrap gap-4">
                    <button type="submit" id="btn-utilisateur-edit-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Enregistrer le compte</button>
                    <a href="{{ route('utilisateurs.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Retour à la liste</a>
                    <a href="{{ route('utilisateurs.show', $utilisateur) }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Fiche utilisateur</a>
                </div>
            </form>
        </div>
        <aside class="flex-1 min-w-0 max-w-md sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6 self-start">
            <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
            <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                <li><strong class="text-slate-800 dark:text-slate-200">Compte</strong> — Nom, emails, téléphone, mot de passe, rôle applicatif (Spatie). Laissez le mot de passe vide pour le conserver.</li>
                <li><strong class="text-slate-800 dark:text-slate-200">Structure principale</strong> — Champ <code class="text-xs">users.structure_id</code> (affichage, rattachement). Les affectations détaillées sont dans l’autre onglet.</li>
                <li><strong class="text-slate-800 dark:text-slate-200">Vue hiérarchique</strong> — Visibilité étendue ; peut aussi venir d’un rôle dans Paramètres → Rôles.</li>
            </ul>
        </aside>
    </div>

    {{-- Onglet Structures & fonctions --}}
    <div id="structures" x-show="tab === 'structures'" class="w-full">
        @include('utilisateurs.partials.affectations-panel', ['utilisateur' => $utilisateur, 'fonctions' => $fonctions, 'structuresDisponibles' => $structuresDisponibles])
    </div>
</div>
@push('styles')
<style>
[x-cloak] { display: none !important; }
#btn-utilisateur-edit-submit .btn-submit-spinner,
#btn-utilisateur-edit-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: user-edit-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes user-edit-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush
@push('scripts')
<script>
(function () {
    var form = document.getElementById('form-utilisateur-edit');
    var btn = document.getElementById('btn-utilisateur-edit-submit');
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
