<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DossierPartage;
use App\Models\GedSetting;
use Illuminate\Support\Facades\Log;

/**
 * Lorsque le paramètre « lecture du dossier parent lors d’un envoi en validation » est actif,
 * accorde un partage dossier en lecture seule au(x) validateur(s), puis révoque ce partage automatique en fin de circuit.
 */
class ValidationDossierLecturePartageService
{
    public const COMMENTAIRE_PREFIX = 'ged:auto-validation-envoi:';

    public function syncPourUtilisateurs(Document $document, iterable $userIds, int $partageParUserId): void
    {
        if (! GedSetting::lectureDossierLorsPartageDocument()) {
            return;
        }
        $dossierId = $document->dossier_id ? (int) $document->dossier_id : 0;
        if ($dossierId <= 0) {
            return;
        }

        $ids = [];
        foreach ($userIds as $uid) {
            $id = (int) $uid;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        // Révoque d’abord les partages auto de ce document (ex. validateur précédent),
        // puis réaccorde uniquement aux destinataires de l’étape courante.
        $this->revoquerPourDocument($document);

        foreach (array_keys($ids) as $userId) {
            $this->accorderLectureSurDossier($document, $dossierId, $userId, $partageParUserId);
        }
    }

    public function revoquerPourDocument(Document $document): void
    {
        $dossierId = $document->dossier_id ? (int) $document->dossier_id : 0;
        if ($dossierId <= 0) {
            return;
        }

        $docId = (int) $document->id;
        $partages = DossierPartage::query()
            ->where('dossier_id', $dossierId)
            ->where('commentaire', 'like', self::COMMENTAIRE_PREFIX.'%')
            ->get();

        foreach ($partages as $partage) {
            if ($partage->droits_ecriture || $partage->droits_suppression) {
                continue;
            }
            $ids = $this->parseDocumentIdsDepuisCommentaire((string) ($partage->commentaire ?? ''));
            $ids = array_values(array_diff($ids, [$docId]));
            if ($ids === []) {
                $partage->delete();
                Log::channel('eged')->debug('Partage dossier auto validation révoqué (suppression)', [
                    'dossier_partage_id' => $partage->id,
                    'document_id' => $docId,
                    'dossier_id' => $dossierId,
                ]);
            } else {
                $partage->update([
                    'commentaire' => self::COMMENTAIRE_PREFIX.implode(',', $ids),
                ]);
                Log::channel('eged')->debug('Partage dossier auto validation révoqué (retrait document)', [
                    'dossier_partage_id' => $partage->id,
                    'document_id' => $docId,
                    'dossier_id' => $dossierId,
                    'ids_restants' => $ids,
                ]);
            }
        }
    }

    /**
     * @return list<int>
     */
    private function parseDocumentIdsDepuisCommentaire(string $commentaire): array
    {
        if ($commentaire === '' || ! str_starts_with($commentaire, self::COMMENTAIRE_PREFIX)) {
            return [];
        }
        $rest = substr($commentaire, strlen(self::COMMENTAIRE_PREFIX));
        $parts = array_filter(array_map('intval', explode(',', $rest)));

        return array_values(array_unique($parts));
    }

    private function accorderLectureSurDossier(Document $document, int $dossierId, int $userId, int $partageParUserId): void
    {
        $docId = (int) $document->id;
        $partage = DossierPartage::query()
            ->where('dossier_id', $dossierId)
            ->where('user_id', $userId)
            ->first();

        if ($partage) {
            if ($partage->droits_ecriture || $partage->droits_suppression) {
                if (! $partage->droits_lecture) {
                    $partage->update(['droits_lecture' => true]);
                }

                return;
            }

            $commentaire = (string) ($partage->commentaire ?? '');
            if ($commentaire !== '' && ! str_starts_with($commentaire, self::COMMENTAIRE_PREFIX)) {
                if (! $partage->droits_lecture) {
                    $partage->update(['droits_lecture' => true]);
                }

                return;
            }

            $ids = $this->parseDocumentIdsDepuisCommentaire($commentaire);
            $ids[] = $docId;
            $ids = array_values(array_unique(array_filter($ids)));

            $partage->update([
                'droits_lecture' => true,
                'droits_ecriture' => false,
                'droits_suppression' => false,
                'propager_aux_sous_dossiers' => false,
                'commentaire' => self::COMMENTAIRE_PREFIX.implode(',', $ids),
                'partage_par_id' => $partageParUserId,
            ]);

            Log::channel('eged')->info('Partage dossier lecture (validation) mis à jour', [
                'dossier_id' => $dossierId,
                'user_id' => $userId,
                'document_id' => $docId,
            ]);

            return;
        }

        DossierPartage::create([
            'dossier_id' => $dossierId,
            'user_id' => $userId,
            'partage_par_id' => $partageParUserId,
            'droits_lecture' => true,
            'droits_ecriture' => false,
            'droits_suppression' => false,
            'propager_aux_sous_dossiers' => false,
            'date_expiration' => null,
            'commentaire' => self::COMMENTAIRE_PREFIX.$docId,
        ]);

        Log::channel('eged')->info('Partage dossier lecture (validation) créé', [
            'dossier_id' => $dossierId,
            'user_id' => $userId,
            'document_id' => $docId,
        ]);
    }
}
