<?php

namespace App\Services;

use App\Models\FournisseurPrestataire;
use App\Models\JournalAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FournisseurPrestataireService
{
    /**
     * @param  array{
     *     nom: string,
     *     type: string,
     *     email?: ?string,
     *     telephone?: ?string,
     *     type_contrat?: ?string,
     *     a_contrat?: bool,
     *     a_dossier_fiscal?: bool,
     *     observation?: ?string,
     *     dossier_id?: ?int,
     *     actif?: bool,
     *     scan_contrat?: list<UploadedFile>|UploadedFile|null,
     *     scan_fiscal?: list<UploadedFile>|UploadedFile|null
     * }  $donnees
     */
    public function creer(User $acteur, array $donnees): FournisseurPrestataire
    {
        $nom = trim($donnees['nom']);
        $normalise = FournisseurPrestataire::normaliserNom($nom);
        $this->assertNomUnique($normalise);

        $aContrat = (bool) ($donnees['a_contrat'] ?? false);
        $aFiscal = (bool) ($donnees['a_dossier_fiscal'] ?? false);

        $fiche = FournisseurPrestataire::query()->create([
            'nom' => $nom,
            'nom_normalise' => $normalise,
            'type' => $donnees['type'],
            'email' => $donnees['email'] ?? null,
            'telephone' => $donnees['telephone'] ?? null,
            'type_contrat' => $donnees['type_contrat'] ?? null,
            'a_contrat' => $aContrat,
            'a_dossier_fiscal' => $aFiscal,
            'observation' => $donnees['observation'] ?? null,
            'dossier_id' => $donnees['dossier_id'] ?? null,
            'actif' => (bool) ($donnees['actif'] ?? true),
            'createur_id' => $acteur->id,
        ]);

        $fiche->update([
            'scan_contrat_pieces' => $aContrat
                ? $this->stockerPieces(
                    $fiche,
                    'contrat',
                    $this->collecterFichiers($donnees['scan_contrat'] ?? null)
                )
                : [],
            'scan_fiscal_pieces' => $aFiscal
                ? $this->stockerPieces(
                    $fiche,
                    'fiscal',
                    $this->collecterFichiers($donnees['scan_fiscal'] ?? null)
                )
                : [],
        ]);

        JournalAudit::log('fournisseur_prestataire.create', 'fournisseur_prestataires', [
            'commentaire' => json_encode([
                'id' => $fiche->id,
                'nom' => $fiche->nom,
                'acteur_id' => $acteur->id,
            ]),
        ]);

        return $fiche->fresh();
    }

    /**
     * @param  array{
     *     nom: string,
     *     type: string,
     *     email?: ?string,
     *     telephone?: ?string,
     *     type_contrat?: ?string,
     *     a_contrat?: bool,
     *     a_dossier_fiscal?: bool,
     *     observation?: ?string,
     *     dossier_id?: ?int,
     *     actif?: bool,
     *     scan_contrat?: list<UploadedFile>|UploadedFile|null,
     *     scan_fiscal?: list<UploadedFile>|UploadedFile|null
     * }  $donnees
     */
    public function mettreAJour(FournisseurPrestataire $fiche, User $acteur, array $donnees): FournisseurPrestataire
    {
        $nom = trim($donnees['nom']);
        $normalise = FournisseurPrestataire::normaliserNom($nom);
        $this->assertNomUnique($normalise, $fiche->id);

        $aContrat = (bool) ($donnees['a_contrat'] ?? false);
        $aFiscal = (bool) ($donnees['a_dossier_fiscal'] ?? false);

        $piecesContrat = $fiche->piecesContrat();
        if ($aContrat) {
            $scansContrat = $this->collecterFichiers($donnees['scan_contrat'] ?? null);
            if ($scansContrat !== []) {
                $piecesContrat = array_merge(
                    $piecesContrat,
                    $this->stockerPieces($fiche, 'contrat', $scansContrat)
                );
            }
        } else {
            $this->supprimerPieces($piecesContrat);
            $piecesContrat = [];
        }

        $piecesFiscal = $fiche->piecesFiscal();
        if ($aFiscal) {
            $scansFiscal = $this->collecterFichiers($donnees['scan_fiscal'] ?? null);
            if ($scansFiscal !== []) {
                $piecesFiscal = array_merge(
                    $piecesFiscal,
                    $this->stockerPieces($fiche, 'fiscal', $scansFiscal)
                );
            }
        } else {
            $this->supprimerPieces($piecesFiscal);
            $piecesFiscal = [];
        }

        $fiche->update([
            'nom' => $nom,
            'nom_normalise' => $normalise,
            'type' => $donnees['type'],
            'email' => $donnees['email'] ?? null,
            'telephone' => $donnees['telephone'] ?? null,
            'type_contrat' => $donnees['type_contrat'] ?? null,
            'a_contrat' => $aContrat,
            'a_dossier_fiscal' => $aFiscal,
            'scan_contrat_pieces' => $piecesContrat,
            'scan_fiscal_pieces' => $piecesFiscal,
            'observation' => $donnees['observation'] ?? null,
            'dossier_id' => $donnees['dossier_id'] ?? null,
            'actif' => (bool) ($donnees['actif'] ?? true),
        ]);

        JournalAudit::log('fournisseur_prestataire.update', 'fournisseur_prestataires', [
            'commentaire' => json_encode([
                'id' => $fiche->id,
                'nom' => $fiche->nom,
                'acteur_id' => $acteur->id,
            ]),
        ]);

        return $fiche->fresh();
    }

    /**
     * @param  list<UploadedFile>|UploadedFile|null  $fichiers
     * @return list<UploadedFile>
     */
    private function collecterFichiers(mixed $fichiers): array
    {
        if ($fichiers instanceof UploadedFile) {
            return [$fichiers];
        }

        if (! is_array($fichiers)) {
            return [];
        }

        return array_values(array_filter(
            $fichiers,
            fn ($f): bool => $f instanceof UploadedFile && $f->isValid()
        ));
    }

    /**
     * @param  list<UploadedFile>  $fichiers
     * @return list<array{chemin: string, nom: string}>
     */
    private function stockerPieces(FournisseurPrestataire $fiche, string $type, array $fichiers): array
    {
        $pieces = [];
        $dossier = 'fournisseurs-prestataires/'.$fiche->id.'/'.$type;

        foreach ($fichiers as $fichier) {
            $chemin = $fichier->store($dossier, 'local');
            if (! is_string($chemin) || $chemin === '') {
                continue;
            }

            $pieces[] = [
                'chemin' => $chemin,
                'nom' => $fichier->getClientOriginalName(),
            ];
        }

        return $pieces;
    }

    /**
     * @param  list<array{chemin: string, nom: string}>  $pieces
     */
    private function supprimerPieces(array $pieces): void
    {
        foreach ($pieces as $piece) {
            $chemin = $piece['chemin'] ?? null;
            if (! is_string($chemin) || $chemin === '') {
                continue;
            }

            Storage::disk('local')->delete($chemin);
        }
    }

    public function desactiver(FournisseurPrestataire $fiche, User $acteur): FournisseurPrestataire
    {
        $fiche->update(['actif' => false]);

        JournalAudit::log('fournisseur_prestataire.desactiver', 'fournisseur_prestataires', [
            'commentaire' => json_encode([
                'id' => $fiche->id,
                'nom' => $fiche->nom,
                'acteur_id' => $acteur->id,
            ]),
        ]);

        return $fiche->fresh();
    }

    /**
     * Trouve ou crée une fiche minimale à partir d’un libellé (saisie facture / régularisation).
     */
    public function trouverOuCreerParLibelle(User $acteur, string $libelle, string $type = FournisseurPrestataire::TYPE_FOURNISSEUR): FournisseurPrestataire
    {
        $nom = trim($libelle);
        $normalise = FournisseurPrestataire::normaliserNom($nom);

        $existant = FournisseurPrestataire::query()
            ->where('nom_normalise', $normalise)
            ->first();

        if ($existant) {
            return $existant;
        }

        return $this->creer($acteur, [
            'nom' => $nom,
            'type' => in_array($type, FournisseurPrestataire::TYPES, true)
                ? $type
                : FournisseurPrestataire::TYPE_FOURNISSEUR,
            'actif' => true,
        ]);
    }

    /**
     * @return Collection<int, FournisseurPrestataire>
     */
    public function actifsPourSelect(): Collection
    {
        return FournisseurPrestataire::query()
            ->actifs()
            ->orderBy('nom')
            ->get(['id', 'nom', 'type', 'type_contrat']);
    }

    /**
     * @param  array{q?: string, type?: string, contrat?: string, fiscal?: string, actif?: string}  $filtres
     */
    public function queryListe(array $filtres = []): Builder
    {
        $query = FournisseurPrestataire::query()->with('dossier');

        $q = trim((string) ($filtres['q'] ?? ''));
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('nom', 'like', "%{$q}%")
                    ->orWhere('type_contrat', 'like', "%{$q}%")
                    ->orWhere('observation', 'like', "%{$q}%");
            });
        }

        $type = trim((string) ($filtres['type'] ?? ''));
        if ($type !== '' && in_array($type, FournisseurPrestataire::TYPES, true)) {
            $query->where('type', $type);
        }

        $contrat = trim((string) ($filtres['contrat'] ?? ''));
        if ($contrat === 'oui') {
            $query->where('a_contrat', true);
        } elseif ($contrat === 'non') {
            $query->where('a_contrat', false);
        }

        $fiscal = trim((string) ($filtres['fiscal'] ?? ''));
        if ($fiscal === 'oui') {
            $query->where('a_dossier_fiscal', true);
        } elseif ($fiscal === 'non') {
            $query->where('a_dossier_fiscal', false);
        }

        $actif = trim((string) ($filtres['actif'] ?? 'oui'));
        if ($actif === 'oui') {
            $query->where('actif', true);
        } elseif ($actif === 'non') {
            $query->where('actif', false);
        }

        return $query->orderBy('nom');
    }

    private function assertNomUnique(string $normalise, ?int $ignoreId = null): void
    {
        $query = FournisseurPrestataire::query()->where('nom_normalise', $normalise);
        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nom' => 'Un fournisseur ou prestataire avec un nom identique ou très proche existe déjà.',
            ]);
        }
    }
}
