<?php

namespace App\Services;

use App\Models\CategorieDepense;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\JournalAudit;
use App\Models\StatutDocument;
use App\Models\SuiviPaiement;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Classement des dépenses (Eleni) aligné sur le flux Mme Taty :
 * justificatifs d’abord en attente, puis classement manuel dans un dossier prestataire.
 */
class SuiviDepenseClassementService
{
    public const NOM_PARENT_PRESTATAIRES = 'Prestataires / fournisseurs';

    public const NOM_ATTENTE = 'À classer — dépenses';

    public function __construct(
        private readonly MesDossiersRacineService $mesDossiersRacine,
        private readonly CourrierClassementDossierService $classementCourrier,
    ) {}

    /**
     * Dépose les justificatifs dans un dossier d’attente (pas encore « classé » métier).
     *
     * @param  list<UploadedFile>  $fichiers
     */
    public function deposerJustificatifsEnAttente(
        SuiviPaiement $ligne,
        User $acteur,
        array $fichiers = [],
    ): ?Dossier {
        if ($fichiers === []) {
            return null;
        }

        return DB::transaction(function () use ($ligne, $acteur, $fichiers): Dossier {
            $racinePerso = $this->mesDossiersRacine->createDefaultRacinePourCommande($acteur);
            $attente = $this->assurerSousDossier(
                $acteur,
                $racinePerso,
                self::NOM_ATTENTE,
                'DEP-ATT-'.$acteur->id,
                'Justificatifs de dépenses en attente de classement prestataire.'
            );

            $nomLigne = mb_substr(
                'Ligne '.$ligne->numeroComplet().' — '.Str::limit((string) $ligne->intitule, 60, '…'),
                0,
                180
            );

            $dossierLigne = Dossier::query()->create([
                'parent_id' => $attente->id,
                'nom' => $nomLigne,
                'code' => $this->codeUnique('DEP-ATT-L-'.$ligne->id),
                'description' => 'En attente de classement — '.$ligne->intitule,
                'confidentiel' => false,
                'notify_sms' => false,
                'actif' => true,
                'ordre' => (int) (Dossier::where('parent_id', $attente->id)->max('ordre') ?? -1) + 1,
                'structure_id' => $acteur->structure_id ?? $attente->structure_id,
                'createur_id' => $acteur->id,
                'proprietaire_id' => $acteur->id,
            ]);

            foreach ($fichiers as $fichier) {
                $this->attacherJustificatif($dossierLigne, $acteur, $fichier, $ligne);
            }

            $ligne->update(['dossier_id' => $dossierLigne->id]);

            return $dossierLigne->fresh();
        });
    }

    /**
     * Conservé pour compatibilité tests / appels : même entrée que le dépôt en attente.
     *
     * @param  list<UploadedFile>  $fichiers
     */
    public function classerDepenseAvecJustificatifs(
        SuiviPaiement $ligne,
        User $acteur,
        CategorieDepense $categorie,
        array $fichiers = [],
    ): ?Dossier {
        return $this->deposerJustificatifsEnAttente($ligne, $acteur, $fichiers);
    }

    /**
     * Classement manuel (existant / nouveau), comme les factures fournisseurs.
     *
     * @param  array{mode: string, dossier_id?: int|null, nom_dossier?: string|null, parent_id?: int|null}  $data
     */
    public function classerManuellement(SuiviPaiement $ligne, User $user, array $data): Dossier
    {
        $mode = $data['mode'] ?? 'existant';

        return DB::transaction(function () use ($ligne, $user, $data, $mode): Dossier {
            $suggestion = $this->nomDossierPrestataire($ligne);
            if ($mode === 'nouveau' && blank($data['nom_dossier'] ?? null)) {
                $data['nom_dossier'] = $suggestion;
            }

            $cible = $mode === 'nouveau'
                ? $this->creerDossierPrestataire($user, $data)
                : $this->resoudreDossierExistant($user, $data);

            $ancien = $ligne->dossier;
            $ligne->update(['dossier_id' => $cible->id]);

            if ($ancien && (int) $ancien->id !== (int) $cible->id) {
                Document::query()
                    ->where('dossier_id', $ancien->id)
                    ->update(['dossier_id' => $cible->id]);

                if ($this->estDossierAttente($ancien) && ! Document::query()->where('dossier_id', $ancien->id)->exists()) {
                    $ancien->delete();
                }
            }

            JournalAudit::log('suivi_paiement.classer_dossier', 'suivi_paiements', [
                'commentaire' => json_encode([
                    'suivi_paiement_id' => $ligne->id,
                    'dossier_id' => $cible->id,
                    'dossier_avant_id' => $ancien?->id,
                    'mode' => $mode,
                ]),
            ]);

            return $cible->fresh();
        });
    }

    public function estClasseMetier(SuiviPaiement $ligne): bool
    {
        $dossier = $ligne->dossierEffectif();
        if (! $dossier) {
            return false;
        }

        return ! $this->estDossierAttente($dossier);
    }

    public function estDossierAttente(Dossier $dossier): bool
    {
        $dossier->loadMissing('parent');

        return $dossier->parent
            && strcasecmp((string) $dossier->parent->nom, self::NOM_ATTENTE) === 0;
    }

    public function dossiersCiblesPour(User $user, ?string $suggestionNom = null)
    {
        return $this->classementCourrier->dossiersCiblesPour($user, $suggestionNom);
    }

    public function nomDossierPrestataire(SuiviPaiement $ligne, ?CategorieDepense $categorie = null): string
    {
        $candidats = [
            trim((string) ($ligne->beneficiaire_libelle ?? '')),
            trim((string) ($ligne->fournisseur_libelle ?? '')),
            trim((string) ($ligne->demandeur_libelle ?? '')),
        ];

        foreach ($candidats as $nom) {
            if ($nom !== '') {
                return mb_substr($nom, 0, 180);
            }
        }

        $categorie ??= $ligne->categorieDepense;
        $fallback = $categorie?->libelle ? 'Divers — '.$categorie->libelle : 'Divers';

        return mb_substr($fallback, 0, 180);
    }

    /**
     * @param  array{nom_dossier?: string|null, parent_id?: int|null}  $data
     */
    private function creerDossierPrestataire(User $user, array $data): Dossier
    {
        $nom = trim((string) ($data['nom_dossier'] ?? ''));
        if ($nom === '') {
            throw ValidationException::withMessages([
                'nom_dossier' => 'Indiquez le nom du dossier prestataire / bénéficiaire.',
            ]);
        }

        $parentId = isset($data['parent_id']) && $data['parent_id'] !== null && $data['parent_id'] !== ''
            ? (int) $data['parent_id']
            : null;

        if ($parentId === null) {
            $racine = $this->mesDossiersRacine->createDefaultRacinePourCommande($user);
            $parent = $this->assurerSousDossier(
                $user,
                $racine,
                self::NOM_PARENT_PRESTATAIRES,
                'DEP-PREST-'.$user->id,
                'Dossiers fournisseurs / prestataires / bénéficiaires (suivi des dépenses).'
            );
        } else {
            $parent = Dossier::query()->where('actif', true)->find($parentId);
            if (! $parent || ! $this->classementCourrier->peutClasserDans($user, $parent)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Dossier parent introuvable ou non accessible.',
                ]);
            }
        }

        $existant = Dossier::query()
            ->where('parent_id', $parent->id)
            ->where('actif', true)
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])
            ->first();

        if ($existant) {
            if (! $this->classementCourrier->peutClasserDans($user, $existant)) {
                throw ValidationException::withMessages([
                    'nom_dossier' => 'Un dossier portant ce nom existe déjà, mais vous n’avez pas le droit d’y classer.',
                ]);
            }

            return $existant;
        }

        return Dossier::query()->create([
            'parent_id' => $parent->id,
            'nom' => $nom,
            'code' => $this->codeUnique('PREST-'.(Str::slug($nom) ?: 'x').'-'.$user->id),
            'description' => 'Dossier fournisseur / prestataire / bénéficiaire (classement dépenses).',
            'confidentiel' => false,
            'notify_sms' => false,
            'actif' => true,
            'ordre' => (int) (Dossier::where('parent_id', $parent->id)->max('ordre') ?? -1) + 1,
            'structure_id' => $user->structure_id ?? $parent->structure_id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
        ]);
    }

    /**
     * @param  array{dossier_id?: int|null}  $data
     */
    private function resoudreDossierExistant(User $user, array $data): Dossier
    {
        $dossierId = (int) ($data['dossier_id'] ?? 0);
        $dossier = Dossier::query()->where('actif', true)->find($dossierId);

        if (! $dossier || ! $this->classementCourrier->peutClasserDans($user, $dossier)) {
            throw ValidationException::withMessages([
                'dossier_id' => $dossier && $dossier->visiblePar($user)
                    ? 'Vous n’avez pas le droit d’écrire dans ce dossier.'
                    : 'Dossier introuvable ou non accessible.',
            ]);
        }

        return $dossier;
    }

    private function assurerSousDossier(
        User $acteur,
        Dossier $parent,
        string $nom,
        string $codePrefix,
        string $description,
    ): Dossier {
        $existant = Dossier::query()
            ->where('parent_id', $parent->id)
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])
            ->where('actif', true)
            ->first();

        if ($existant) {
            return $existant;
        }

        return Dossier::query()->create([
            'parent_id' => $parent->id,
            'nom' => $nom,
            'code' => $this->codeUnique($codePrefix),
            'description' => $description,
            'confidentiel' => false,
            'notify_sms' => false,
            'actif' => true,
            'ordre' => (int) (Dossier::where('parent_id', $parent->id)->max('ordre') ?? -1) + 1,
            'structure_id' => $acteur->structure_id ?? $parent->structure_id,
            'createur_id' => $acteur->id,
            'proprietaire_id' => $acteur->id,
        ]);
    }

    private function attacherJustificatif(
        Dossier $dossier,
        User $acteur,
        UploadedFile $fichier,
        SuiviPaiement $ligne,
    ): Document {
        $typeDoc = TypeDocument::query()
            ->whereIn('code', ['PDF', 'COURRIER_IN', 'COURRIER', 'LETTRE'])
            ->where('actif', true)
            ->orderByRaw("CASE WHEN code = 'PDF' THEN 0 WHEN code LIKE 'COURRIER_%' THEN 1 ELSE 2 END")
            ->first();

        if (! $typeDoc) {
            throw new \RuntimeException('Aucun type de document disponible pour les justificatifs de dépense.');
        }

        $statut = StatutDocument::query()->where('code', 'brouillon')->first();
        $chemin = $fichier->store('documents/'.date('Y/m'), 'public');

        return Document::query()->create([
            'type_document_id' => $typeDoc->id,
            'user_id' => $acteur->id,
            'createur_id' => $acteur->id,
            'proprietaire_id' => $acteur->id,
            'dossier_id' => $dossier->id,
            'nom_original' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'extension' => $fichier->getClientOriginalExtension(),
            'taille_octets' => $fichier->getSize(),
            'mime_type' => $fichier->getMimeType(),
            'titre' => 'Justificatif — '.$ligne->intitule,
            'description' => 'Scan / justificatif déposé lors de l’enregistrement de la dépense',
            'statut' => 'brouillon',
            'statut_document_id' => $statut?->id,
            'en_corbeille' => false,
            'confidentiel' => false,
        ]);
    }

    private function codeUnique(string $base): string
    {
        $base = mb_substr(preg_replace('/[^A-Za-z0-9\-_]+/', '-', $base) ?: 'PREST', 0, 80);
        $code = $base;
        $i = 0;
        while (Dossier::query()->where('code', $code)->exists()) {
            $i++;
            $code = $base.'-'.$i;
        }

        return $code;
    }
}
