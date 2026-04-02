@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Partager le dossier')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('dossiers.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">Plan de classement</a>
        @foreach($dossier->cheminAncetres() as $ancetre)
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <a href="{{ route('dossiers.show', $ancetre) }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">{{ $ancetre->nom }}</a>
        @endforeach
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <a href="{{ route('dossiers.show', $dossier) }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">{{ $dossier->nom }}</a>
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Partage</span>
    </nav>
@endsection

@section('content')
<div class="space-y-6" x-data="partageEditModal()">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-3">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        <span class="flex-1">{{ session('success') }}</span>
        <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
    </div>
    @endif
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-3">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span class="flex-1">{{ session('error') }}</span>
        <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-red-200/50 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
    </div>
    @endif

    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-600 text-sm text-slate-700 dark:text-slate-300">
        <strong class="font-semibold text-slate-800 dark:text-slate-200">Partage par dossier (accès ciblé)</strong>
        <p class="mt-2 leading-relaxed">Les droits s’appliquent <strong>uniquement à ce dossier</strong> : documents déposés ici et navigation réservée à ce niveau. Les <strong>sous-dossiers restent privés</strong> pour le destinataire tant qu’ils n’ont pas reçu leur propre partage (ou qu’il n’en est pas propriétaire).</p>
        @if(in_array(strtolower((string) $dossier->type), ['projet', 'project'], true))
        <p class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-600 leading-relaxed">
            <strong>Recommandation Projet</strong> : après création par le chef de service, partagez ce dossier au <strong>chef de projet</strong> et aux <strong>membres de l’équipe</strong> avec le droit <strong>Écriture (dépôt)</strong>.
        </p>
        @endif
        @if($dossier->estSoumisReglePartageTitulaireDirection())
        <p class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-600 leading-relaxed text-slate-600 dark:text-slate-400">Règle globale : seuls le <strong>propriétaire</strong>, le <strong>créateur</strong> du dossier, ou un <strong>administrateur</strong> peuvent gérer les partages.</p>
        @endif
    </div>

    {{-- Deux blocs pleine largeur (une ligne chacun) --}}
    <div class="flex flex-col gap-6 w-full">
        {{-- Ligne 1 : Formulaire ajout --}}
        <div class="w-full">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden w-full">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Ajouter un partage
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Sélectionnez un ou plusieurs utilisateurs ; les mêmes droits s’appliquent à tous.</p>
                </div>
                <form id="form-partage-add" action="{{ route('dossiers.partages.store', $dossier) }}" method="POST" class="p-6 space-y-5">
                    @csrf
                    {{-- Pas de grille 12 cols ici : le 2e enfant se retrouvait en 1/12 de large. Empilement = liste pleine largeur. --}}
                    <div class="space-y-5">
                        <div class="w-full min-w-0">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Utilisateurs (sélection multiple) *</label>
                            <select name="user_ids[]" multiple required
                                    size="{{ min(max($utilisateurs->count(), 6), 14) }}"
                                    class="block w-full max-w-none min-w-0 box-border px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-colors min-h-[220px] @error('user_ids') border-red-500 @enderror">
                                @foreach($utilisateurs as $u)
                                <option value="{{ $u->id }}" {{ in_array((int) $u->id, old('user_ids', []), true) ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @if($utilisateurs->isEmpty())
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tous les utilisateurs ont déjà un partage.</p>
                            @endif
                            @error('user_ids')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div class="w-full max-w-md">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Date d'expiration (optionnel)</label>
                            <input type="date" name="date_expiration" value="{{ old('date_expiration') }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-colors">
                        </div>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Droits</span>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <input type="hidden" name="droits_lecture" value="0">
                                <input type="checkbox" name="droits_lecture" value="1" {{ old('droits_lecture', true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                                <span class="text-sm text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">Lecture</span>
                            </label>
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <input type="hidden" name="droits_ecriture" value="0">
                                <input type="checkbox" name="droits_ecriture" value="1" {{ old('droits_ecriture') ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">Écriture (dépôt)</span>
                            </label>
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <input type="hidden" name="droits_suppression" value="0">
                                <input type="checkbox" name="droits_suppression" value="1" {{ old('droits_suppression') ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                                <span class="text-sm text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">Suppression</span>
                            </label>
                        </div>
                    </div>
                    <div class="pt-1">
                        <label class="inline-flex items-start gap-2.5 cursor-pointer">
                            <input type="hidden" name="appliquer_sous_dossiers" value="0">
                            <input type="checkbox" name="appliquer_sous_dossiers" value="1" {{ old('appliquer_sous_dossiers') ? 'checked' : '' }} class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                Appliquer aussi ce partage aux sous-dossiers
                                <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pratique pour les dossiers projet afin d’éviter le partage manuel dossier par dossier.</span>
                            </span>
                        </label>
                    </div>
                    <div class="pt-1">
                        <label class="inline-flex items-start gap-2.5 cursor-pointer">
                            <input type="hidden" name="propager_aux_sous_dossiers" value="0">
                            <input type="checkbox" name="propager_aux_sous_dossiers" value="1" {{ old('propager_aux_sous_dossiers') ? 'checked' : '' }} class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                Appliquer automatiquement aux sous-dossiers futurs
                                <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Chaque nouveau sous-dossier créé héritera automatiquement ce partage.</span>
                            </span>
                        </label>
                    </div>
                    <div class="pt-2">
                        <button type="submit"
                                id="btn-partage-add-submit"
                                @if($utilisateurs->isEmpty()) disabled @endif
                                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-sm hover:shadow transition-all focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-70">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            <span>Ajouter le partage</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Ligne 2 : Liste des partages --}}
        <div class="w-full">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden w-full">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                        Partages actifs
                        @if($partages->isNotEmpty())
                        <span class="text-sm font-normal text-slate-500 dark:text-slate-400">({{ $partages->count() }})</span>
                        @endif
                    </h3>
                </div>
                @if($partages->isEmpty())
                <div class="p-12 text-center">
                    <div class="inline-flex w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700/50 items-center justify-center text-3xl mb-4">👥</div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Aucun partage pour ce dossier</p>
                    <p class="text-slate-500 dark:text-slate-500 text-sm mt-1">Ajoutez un partage à l'aide du formulaire ci-contre</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Utilisateur</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lecture</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Écriture</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden sm:table-cell">Suppression</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">Expiration</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                            @foreach($partages as $p)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-lg font-semibold text-emerald-700 dark:text-emerald-300 flex-shrink-0">
                                            {{ strtoupper(substr($p->user->name ?? '?', 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <span class="font-medium text-slate-800 dark:text-slate-200 block">{{ $p->user->name }}</span>
                                            <span class="text-xs text-slate-500 dark:text-slate-400 truncate block">{{ $p->user->email }}</span>
                                            @if($p->propager_aux_sous_dossiers)
                                            <span class="mt-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Héritage auto activé</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($p->droits_lecture)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400" title="Lecture">✓</span>
                                    @else
                                    <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($p->droits_ecriture)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400" title="Écriture">✓</span>
                                    @else
                                    <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 hidden sm:table-cell">
                                    @if($p->droits_suppression)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400" title="Suppression">✓</span>
                                    @else
                                    <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400 hidden md:table-cell">{{ $p->date_expiration ? $p->date_expiration->format('d/m/Y') : '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button type="button"
                                                @click="openEdit({
                                                    url: '{{ route('dossiers.partages.update', [$dossier, $p->id]) }}',
                                                    droits_lecture: {{ $p->droits_lecture ? 'true' : 'false' }},
                                                    droits_ecriture: {{ $p->droits_ecriture ? 'true' : 'false' }},
                                                    droits_suppression: {{ $p->droits_suppression ? 'true' : 'false' }},
                                                    propager_aux_sous_dossiers: {{ $p->propager_aux_sous_dossiers ? 'true' : 'false' }},
                                                    date_expiration: @json(optional($p->date_expiration)->format('Y-m-d'))
                                                })"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                                                title="Modifier les droits">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11 21H8v-3l9.586-9.586z" />
                                            </svg>
                                        </button>

                                        <form action="{{ route('dossiers.partages.destroy', [$dossier, $p->id]) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" onclick="flashAlert('Retirer ce partage ? L\'utilisateur n\'aura plus accès à ce dossier.', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Retirer'})" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Retirer le partage">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Formulaire modification via flashAlert (un seul modal) --}}
    <div id="partage-edit-flash-pool" style="display:none;">
        <form id="partage-edit-flash-form" action="" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Droits</span>
                <div class="flex flex-wrap gap-5">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input id="partage-edit-flash-droits-lecture" type="checkbox" name="droits_lecture" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">Lecture</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input id="partage-edit-flash-droits-ecriture" type="checkbox" name="droits_ecriture" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Écriture (dépôt)</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input id="partage-edit-flash-droits-suppression" type="checkbox" name="droits_suppression" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">Suppression</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Date d’expiration (optionnel)</label>
                <input id="partage-edit-flash-date-expiration" type="date" name="date_expiration"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Laisser vide pour aucune expiration.</p>
            </div>
            <div>
                <label class="inline-flex items-start gap-2.5 cursor-pointer">
                    <input type="hidden" name="propager_aux_sous_dossiers" value="0">
                    <input id="partage-edit-flash-propager" type="checkbox" name="propager_aux_sous_dossiers" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                    <span class="text-sm text-slate-700 dark:text-slate-300">
                        Appliquer automatiquement aux sous-dossiers futurs
                    </span>
                </label>
            </div>
        </form>
    </div>

    <div class="flex items-center gap-4 pt-2">
        <a href="{{ route('dossiers.show', $dossier) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Retour au dossier
        </a>
    </div>
</div>

@push('scripts')
<script>
function partageEditModal() {
    return {
        modalOpen: false,
        editUrl: '',
        editLecture: true,
        editEcriture: false,
        editSuppression: false,
        editDate: '',
        editSubmitting: false,
        openEdit(p) {
            // On ne réouvre PAS un 2e modal : on réutilise le flashAlert unique
            var formEl = document.getElementById('partage-edit-flash-form');
            if (!formEl) return;

            formEl.action = p.url;

            var lectureEl = document.getElementById('partage-edit-flash-droits-lecture');
            var ecritureEl = document.getElementById('partage-edit-flash-droits-ecriture');
            var suppressionEl = document.getElementById('partage-edit-flash-droits-suppression');
            var propagerEl = document.getElementById('partage-edit-flash-propager');
            if (lectureEl) lectureEl.checked = !!p.droits_lecture;
            if (ecritureEl) ecritureEl.checked = !!p.droits_ecriture;
            if (suppressionEl) suppressionEl.checked = !!p.droits_suppression;
            if (propagerEl) propagerEl.checked = !!p.propager_aux_sous_dossiers;

            var dateEl = document.getElementById('partage-edit-flash-date-expiration');
            if (dateEl) dateEl.value = p.date_expiration || '';

            flashAlert('', formEl, {
                title: 'Modifier le partage',
                icon: '✏️',
                confirmText: 'Enregistrer',
                danger: false,
                customBodyId: 'partage-edit-flash-form',
                customBodyPoolId: 'partage-edit-flash-pool',
                // Pas d'input textarea supplémentaire : on soumet le formulaire directement
            });
        },
        closeModal() {
            this.modalOpen = false;
            this.editSubmitting = false;
        },
    };
}
(function(){
    var f = document.getElementById('form-partage-add');
    var b = document.getElementById('btn-partage-add-submit');
    if (!f || !b) return;
    f.addEventListener('submit', function(){
        if (b.dataset.loading === '1') return;
        b.dataset.loading = '1';
        b.innerHTML = '<span class="inline-block w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin shrink-0"></span> Enregistrement...';
        b.disabled = true;
    });
})();
</script>
@endpush
@endsection
