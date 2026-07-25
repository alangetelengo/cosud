@extends('layouts.app')

@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')
@section('page-title', 'Fiche document')
@section('page-title-info', $document->titre ?: $document->nom_original)

@section('content')
<div class="w-full">
    @if(strtolower($document->statut ?? '') === 'rejete' && ($motifRejet = $document->dernierMotifRejet()))
    <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <p class="font-semibold text-red-800 dark:text-red-200 flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            Motif du rejet
        </p>
        <p class="mt-2 text-sm text-red-700 dark:text-red-300 whitespace-pre-wrap">{{ $motifRejet }}</p>
    </div>
    @endif

    <div id="doc-fiche-grid" class="grid grid-cols-1 md:grid-cols-12 gap-6">
        {{-- BLOC GAUCHE : Métadonnées (lecture seule) --}}
        <div id="doc-fiche-meta" class="md:col-span-5 flex flex-col min-w-0 order-2 md:order-1">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Détails du document
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Informations en lecture seule</p>
                </div>
                <div class="p-6 space-y-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Dossier</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ $document->dossier?->chemin_complet ?? '— Aucun —' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Type de document</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ $document->typeDocument?->libelle ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Titre</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ $document->titre ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Référence</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ $document->reference ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Mots-clés</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ $document->mots_cles ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Description</dt>
                            <dd class="text-slate-800 dark:text-slate-200 whitespace-pre-wrap">{{ $document->description ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Déposé par</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ $document->user?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Date de dépôt</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ $document->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd>
                        </div>
                        @if(isset($courriersLies) && $courriersLies->isNotEmpty())
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Courriers liés (traçabilité)</dt>
                            <dd class="space-y-2">
                                @foreach($courriersLies as $courrierLie)
                                <div class="text-sm rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 bg-slate-50/50 dark:bg-slate-900/30">
                                    <a href="{{ route('courriers.show', $courrierLie) }}" class="text-emerald-600 font-medium no-underline">
                                        {{ $courrierLie->sensCourrier->libelle }} n° {{ $courrierLie->numeroRegistreComplet() }}
                                    </a>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ ucfirst($courrierLie->origine) }} · {{ $courrierLie->statutCourrier->libelle }}
                                        @if($courrierLie->estArrivee())
                                        · <span class="text-emerald-600 font-semibold">Document entrant</span>
                                        @else
                                        · <span class="text-sky-600 font-semibold">Document sortant</span>
                                        @endif
                                    </p>
                                </div>
                                @endforeach
                            </dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Confidentiel</dt>
                            <dd class="text-slate-800 dark:text-slate-200">
                                @if($document->confidentiel)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    Oui
                                </span>
                                @else
                                <span class="text-slate-500 dark:text-slate-400">Non</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Statut</dt>
                            <dd>
                                @php
                                    $statut = $document->statutDocument?->libelle ?? ucfirst($document->statut ?? '');
                                    $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
                                    $roleLabelPourUser = function ($user): string {
                                        if (! $user) {
                                            return 'valideur';
                                        }
                                        if ($user->hasRole('dg')) {
                                            return 'DG';
                                        }
                                        if ($user->hasRole('directeur')) {
                                            return 'Directeur';
                                        }
                                        if ($user->hasRole('chef_service')) {
                                            return 'Chef de service';
                                        }

                                        return 'valideur';
                                    };
                                    $roleAvecArticle = function (string $role): string {
                                        return match ($role) {
                                            'DG' => 'le DG',
                                            'Directeur' => 'le Directeur',
                                            'Chef de service' => 'le Chef de service',
                                            default => 'le valideur',
                                        };
                                    };
                                    $roleAvecDe = function (string $role): string {
                                        return match ($role) {
                                            'DG' => 'du Directeur Général',
                                            'Directeur' => 'du Directeur',
                                            'Chef de service' => 'du Chef de service',
                                            default => 'du valideur',
                                        };
                                    };
                                    $roleEnAttente = $roleLabelPourUser($document->workflowValidateur);
                                    $validateursAyantVise = $document->validations
                                        ->where('action', \App\Models\DocumentValidation::ACTION_APPROBATION)
                                        ->map(fn ($v) => $roleAvecArticle($roleLabelPourUser($v->user)))
                                        ->filter()
                                        ->unique()
                                        ->values()
                                        ->all();
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-medium
                                    @if(in_array($statutCode, ['brouillon'])) bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300
                                    @elseif(in_array($statutCode, ['en_attente'])) bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300
                                    @elseif(in_array($statutCode, ['valide', 'validé'])) bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300
                                    @elseif(in_array($statutCode, ['rejete', 'rejeté'])) bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300
                                    @elseif(in_array($statutCode, ['archive', 'archivé'])) bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-300
                                    @else bg-slate-100 dark:bg-slate-600 text-slate-700 dark:text-slate-300 @endif">
                                    {{ $statut }}
                                </span>
                                @if(in_array($statutCode, ['en_attente']))
                                    <div class="mt-2 space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        <p>En attente de validation {{ $roleAvecDe($roleEnAttente) }}.</p>
                                        @if(! empty($validateursAyantVise))
                                            <p>Déjà visé par : {{ implode(', ', $validateursAyantVise) }}.</p>
                                        @endif
                                    </div>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-200 dark:border-slate-600">
                        @can('update', $document)
                        <a href="{{ route('documents.edit', $document) }}" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition-colors no-underline">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            Modifier
                        </a>
                        @endcan
                        @can('documents.view')
                        <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            Télécharger
                        </a>
                        @php $extViewable = in_array(strtolower($document->extension ?? pathinfo($document->nom_original ?? '', PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp']); @endphp
                        @if($extViewable)
                        <a href="{{ route('documents.show', $document) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            Visualiser
                        </a>
                        @endif
                        @endcan
                        <a href="{{ route('documents.index') }}" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
                            Retour à la liste
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- BLOC DROITE : Fichier + Métadonnées extraites --}}
        <div id="doc-fiche-preview" class="md:col-span-7 flex flex-col gap-6 min-w-0 order-1 md:order-2">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Fichier
                    </h3>
                </div>
                <div class="p-6">
                    @php
                        $ext = strtolower($document->extension ?? pathinfo($document->nom_original ?? '', PATHINFO_EXTENSION));
                        $lib = strtolower($document->typeDocument->libelle ?? '');
                        $icon = match(true) {
                            $ext === 'pdf' || str_contains($lib, 'pdf') => ['bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400', '📄'],
                            in_array($ext, ['doc','docx']) || str_contains($lib, 'word') => ['bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400', '📝'],
                            in_array($ext, ['xls','xlsx']) || str_contains($lib, 'excel') => ['bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400', '📊'],
                            in_array($ext, ['jpg','jpeg','png','gif','webp']) => ['bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400', '🖼️'],
                            default => ['bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400', '📄'],
                        };
                    @endphp
                    <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50/50 dark:bg-slate-700/30">
                        <div class="w-16 h-16 rounded-xl flex items-center justify-center text-3xl {{ $icon[0] }}">
                            {{ $icon[1] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $document->nom_original }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $document->taille_formatee }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Métadonnées extraites du fichier --}}
            @if($document->metadonnees->isNotEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        Métadonnées extraites
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Propriétés du fichier</p>
                </div>
                <div class="p-6">
                    <dl class="space-y-2">
                        @foreach($document->metadonnees as $m)
                        <div class="flex justify-between gap-4 py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ $m->typeMetadonnee?->libelle ?? $m->cle }}</dt>
                            <dd class="text-sm text-slate-800 dark:text-slate-200 text-right">{{ $m->valeur_formatee ?? '—' }}</dd>
                        </div>
                        @endforeach
                    </dl>
                </div>
            </div>
            @endif

            {{-- Historique des mouvements --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Historique des mouvements
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Traçabilité du document</p>
                </div>
                <div class="p-6">
                    @if($document->historiques->isEmpty())
                    <p class="text-sm text-slate-500 dark:text-slate-400">Aucun mouvement enregistré.</p>
                    @else
                    <div class="space-y-0">
                        @foreach($document->historiques as $h)
                        @php
                            $opLabels = [
                                'depot' => 'Dépôt',
                                'nouvelle_version' => 'Nouvelle version',
                                'modification' => 'Modification',
                                'validation' => 'Validation',
                                'workflow_envoi' => 'Envoi en validation',
                                'workflow_approbation' => 'Approbation',
                                'workflow_rejet' => 'Rejet',
                                'archivage' => 'Archivage',
                                'corbeille' => 'Corbeille',
                                'restauration' => 'Restauration',
                            ];
                            $opLabel = $opLabels[$h->operation] ?? ucfirst($h->operation);
                        @endphp
                        <div class="flex gap-4 py-3 border-b border-slate-100 dark:border-slate-700 last:border-0 last:pb-0">
                            <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full bg-sky-400"></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $opLabel }}</p>
                                @if($h->commentaire)
                                <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">{{ $h->commentaire }}</p>
                                @endif
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    {{ $h->created_at?->format('d/m/Y à H:i') ?? '—' }}
                                    @if($h->user)
                                    · {{ $h->user->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
@media (min-width: 768px) {
    #doc-fiche-grid { display: grid !important; grid-template-columns: 5fr 7fr !important; gap: 1.5rem !important; }
    #doc-fiche-meta { order: 1; }
    #doc-fiche-preview { order: 2; }
}
</style>
@endpush
@endsection
