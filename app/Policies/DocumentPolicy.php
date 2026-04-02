<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        if (! $user->can('documents.view')) {
            return false;
        }
        if (! $document->visiblePar($user)) {
            return false;
        }
        $lieAuDocument = $this->utilisateurLieAuDocument($user, $document);
        $dossier = $document->dossier;
        if ($dossier && $dossier->confidentiel && ! $user->can('dossiers.view-confidentiel') && ! $user->aAccesTotal() && ! $lieAuDocument) {
            return false;
        }
        if ($document->confidentiel && ! $user->can('dossiers.view-confidentiel') && ! $user->aAccesTotal() && ! $lieAuDocument) {
            return false;
        }

        return true;
    }

    /** Propriétaire, créateur ou utilisateur lié au fichier (déposant) : toujours voir leur document, même confidentiel. */
    private function utilisateurLieAuDocument(User $user, Document $document): bool
    {
        return (int) ($document->proprietaire_id ?? 0) === (int) $user->id
            || (int) ($document->createur_id ?? 0) === (int) $user->id
            || (int) ($document->user_id ?? 0) === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('documents.create');
    }

    public function update(User $user, Document $document): bool
    {
        if (! $user->can('documents.edit') || ! $document->visiblePar($user)) {
            return false;
        }
        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (in_array($statutCode, ['archive', 'archivé'])) {
            return false;
        }

        // Admin / DG conservent la capacité de gestion.
        if ($user->aAccesTotal()) {
            return true;
        }

        // Le créateur / propriétaire / déposant garde le droit de modifier son document.
        if ($this->utilisateurLieAuDocument($user, $document)) {
            return true;
        }

        // Sinon, la modification d'un document partagé exige explicitement un droit d'écriture sur le dossier.
        $dossier = $document->relationLoaded('dossier') ? $document->dossier : $document->dossier()->first();
        if (! $dossier) {
            return false;
        }

        if ((int) ($dossier->proprietaire_id ?? 0) === (int) $user->id || (int) ($dossier->createur_id ?? 0) === (int) $user->id) {
            return true;
        }

        return $dossier->partages()
            ->where('user_id', $user->id)
            ->where('droits_ecriture', true)
            ->where(function ($q) {
                $q->whereNull('date_expiration')->orWhere('date_expiration', '>', now());
            })
            ->exists();
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.delete') && $document->visiblePar($user);
    }

    public function approuver(User $user, Document $document): bool
    {
        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (! in_array($statutCode, ['en_attente']) || ! $document->workflowEtapeActuelle) {
            return false;
        }

        return $document->workflowEtapeActuelle->peutValider($user, $document);
    }

    public function rejeter(User $user, Document $document): bool
    {
        return $this->approuver($user, $document);
    }

    /**
     * Premier envoi en validation : réservé au propriétaire du document (les validateurs font avancer le circuit via approuver/rejeter).
     */
    public function envoyerValidation(User $user, Document $document): bool
    {
        if (! $user->can('documents.edit') || ! $document->visiblePar($user)) {
            return false;
        }
        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (! in_array($statutCode, ['brouillon', 'rejete'])) {
            return false;
        }
        $proprietaireId = $document->proprietaire_id ?? $document->createur_id;

        return $proprietaireId !== null && (int) $proprietaireId === (int) $user->id;
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->can('documents.delete') && $document->visiblePar($user);
    }
}
