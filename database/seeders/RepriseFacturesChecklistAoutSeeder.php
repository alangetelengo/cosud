<?php

namespace Database\Seeders;

use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\FournisseurPrestataire;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Reprise ponctuelle des factures du registre papier août 2026
 * (docs/Checklist-reprise-registre-aout.xlsx → data/checklist_reprise_aout_factures.json).
 *
 * Créateur : Mme Taty (001958d@acsi.cg) — responsable dossiers fournisseurs / prestataires.
 *
 * Ne pas appeler depuis DatabaseSeeder — lancer à la main :
 * php artisan db:seed --class=RepriseFacturesChecklistAoutSeeder
 */
class RepriseFacturesChecklistAoutSeeder extends Seeder
{
    public const EMAIL_CREATEUR = '001958d@acsi.cg';

    public const OBSERVATIONS = 'Reprise des courriers août 2026';

    /** Surcharge de chemin JSON (tests uniquement). */
    public static ?string $cheminJson = null;

    /**
     * Reformule l’objet checklist : retire le préfixe facture/proforma/devis + N°,
     * retire « comptant », capitalise. Le N° extrait part dans reference.
     *
     * @return array{objet: string, reference: ?string}
     */
    public static function reformulerObjet(string $objetBrut): array
    {
        $objet = trim(preg_replace("/\s+/u", ' ', str_replace(["\r\n", "\n", "\r"], ' ', $objetBrut)) ?? $objetBrut);
        $reference = null;

        if (preg_match(
            '/^(?:facture(?:\s+proforma)?|proforma|devis)\s*n[°º]?\s*([A-Z0-9][A-Z0-9\/\-]*)/iu',
            $objet,
            $m
        )) {
            $reference = trim($m[1], " \t,.");
        }

        $objet = preg_replace(
            '/^(?:facture(?:\s+proforma)?|proforma|devis)\s*n[°º]?\s*[A-Z0-9][A-Z0-9\/\-]*\s*,?\s*(?:relative|relatif)\s+(?:à\s+la|à\s+l[\'’]|au|aux|à)\s*/iu',
            '',
            $objet
        ) ?? $objet;

        $objet = preg_replace(
            '/^(?:facture(?:\s+proforma)?|proforma|devis)\s*n[°º]?\s*[A-Z0-9][A-Z0-9\/\-]*\s*,?\s*(?:du|de\s+la|de\s+l[\'’]|des)?\s*/iu',
            '',
            $objet
        ) ?? $objet;

        $objet = preg_replace(
            '/^proforma\s+(?:relative|relatif)\s+(?:à\s+la|à\s+l[\'’]|au|aux|à)\s*/iu',
            '',
            $objet
        ) ?? $objet;

        $objet = preg_replace(
            '/^devis\s+estimatif\s+[^,]*,\s*(?:relative|relatif)\s+(?:à\s+la|à\s+l[\'’]|au|aux|à)\s*/iu',
            '',
            $objet
        ) ?? $objet;

        $objet = preg_replace('/^paiement\s+facture\s+/iu', 'Paiement facture ', $objet) ?? $objet;

        $objet = preg_replace('/\bcomptant\b/iu', '', $objet) ?? $objet;
        $objet = preg_replace('/\s+pour le\s+mois\b/iu', ' mois', $objet) ?? $objet;
        $objet = preg_replace('/\s+/u', ' ', $objet) ?? $objet;
        $objet = trim($objet, " \t,.;");

        if ($objet === '' || preg_match('/^(?:relative|relatif)\s*$/iu', $objet)) {
            if ($reference !== null) {
                $objet = $reference;
            } else {
                $objet = trim(preg_replace("/\s+/u", ' ', $objetBrut) ?? $objetBrut);
            }
        }

        $objet = mb_strtoupper(mb_substr($objet, 0, 1)).mb_substr($objet, 1);

        return [
            'objet' => $objet,
            'reference' => $reference,
        ];
    }

    /**
     * @return array{numero: int, annee: int, complet: string}
     */
    public static function parserNumeroRegistre(string $complet): array
    {
        $complet = trim($complet);
        $complet = preg_replace('/^(\d+)\s*DG$/iu', '$1/DG', $complet) ?? $complet;

        if (! preg_match('/^(\d+)\s*\/\s*DG$/iu', $complet, $m)) {
            throw new InvalidArgumentException('N° registre invalide : '.$complet);
        }

        return [
            'numero' => (int) $m[1],
            'annee' => 2026,
            'complet' => ((int) $m[1]).'/DG',
        ];
    }

    public function run(): void
    {
        $path = self::$cheminJson ?? database_path('seeders/data/checklist_reprise_aout_factures.json');
        if (! is_file($path)) {
            $this->command?->error('Fichier introuvable : '.$path);

            return;
        }

        /** @var list<array<string, mixed>> $lignes */
        $lignes = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if ($lignes === []) {
            $this->command?->warn('Aucune facture dans le JSON de reprise.');

            return;
        }

        $createur = $this->resoudreCreateur();
        if (! $createur) {
            $this->command?->error(
                'Utilisateur créateur introuvable : '.self::EMAIL_CREATEUR
                .' (Mme ANNE LETHICIA TATY-TCHICAYA NÉE ND). Lancez CourrierActeursDgSeeder.'
            );

            return;
        }

        $sens = SensCourrier::query()->where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::query()
            ->where('sens_courrier_id', $sens->id)
            ->where('code', 'recu')
            ->firstOrFail();
        $type = TypeCourrier::query()->where('code', 'facture')->firstOrFail();
        $priorite = PrioriteCourrier::query()->where('code', 'normale')->firstOrFail();
        $circuit = CircuitCourrier::query()->where('code', 'facture_prestataire')->firstOrFail();
        $structureId = $createur->structure_id
            ?? Structure::query()->where('code', 'SEC-DIR')->value('id');

        $corriges = Courrier::query()
            ->where(function ($q): void {
                $q->where('observations', 'Reprise checklist registre août 2026')
                    ->orWhere('observations', self::OBSERVATIONS);
            })
            ->update([
                'createur_id' => $createur->id,
                'structure_id' => $structureId,
                'observations' => self::OBSERVATIONS,
            ]);

        if ($corriges > 0) {
            $this->command?->info("Correction créateur / observations : {$corriges} courrier(s).");
        }

        $moteur = app(CircuitCourrierMoteurService::class);
        $crees = 0;
        $ignores = 0;
        $erreurs = 0;

        Notification::fake();

        foreach ($lignes as $ligne) {
            try {
                $nums = self::parserNumeroRegistre((string) ($ligne['numero_registre_complet'] ?? ''));
            } catch (InvalidArgumentException $e) {
                $this->command?->warn($e->getMessage());
                $erreurs++;

                continue;
            }

            $existant = Courrier::query()->where('numero_fulgurant', $nums['complet'])->first();
            if ($existant) {
                [$tel1, $tel2] = self::decomposerTelephones($ligne['telephone'] ?? null);
                $existant->forceFill([
                    'createur_id' => $createur->id,
                    'structure_id' => $structureId,
                    'observations' => self::OBSERVATIONS,
                    'expediteur_telephone' => $tel1,
                    'expediteur_telephone_2' => $tel2,
                ])->save();
                $ignores++;

                continue;
            }

            $reformule = self::reformulerObjet((string) ($ligne['objet_brut'] ?? ''));
            $expediteur = trim((string) ($ligne['expediteur_libelle'] ?? ''));
            $fournisseurId = $expediteur !== ''
                ? FournisseurPrestataire::query()
                    ->where('nom_normalise', FournisseurPrestataire::normaliserNom($expediteur))
                    ->value('id')
                : null;

            [$tel1, $tel2] = self::decomposerTelephones($ligne['telephone'] ?? null);

            $courrier = Courrier::query()->create([
                'sens_courrier_id' => $sens->id,
                'type_courrier_id' => $type->id,
                'statut_courrier_id' => $statut->id,
                'priorite_courrier_id' => $priorite->id,
                'numero_registre' => $nums['numero'],
                'numero_registre_annee' => $nums['annee'],
                'numero_fulgurant' => $nums['complet'],
                'reference' => $reformule['reference'],
                'origine' => Courrier::ORIGINE_EXTERNE,
                'date_reception' => $ligne['date_reception'] ?? null,
                'objet' => $reformule['objet'],
                'expediteur_libelle' => $expediteur !== '' ? $expediteur : null,
                'expediteur_telephone' => $tel1,
                'expediteur_telephone_2' => $tel2,
                'expediteur_notifier_telephone' => true,
                'expediteur_notifier_telephone_2' => true,
                'est_expediteur_externe' => true,
                'montant_facture' => isset($ligne['montant']) ? (float) $ligne['montant'] : null,
                'fournisseur_prestataire_id' => $fournisseurId,
                'createur_id' => $createur->id,
                'structure_id' => $structureId,
                'observations' => self::OBSERVATIONS,
            ]);

            $moteur->demarrer($courrier->fresh(['sensCourrier', 'typeCourrier']), $circuit, $createur);
            $crees++;
        }

        $this->command?->info(sprintf(
            'Reprise factures checklist : %d créé(s), %d déjà présent(s), %d erreur(s) — créateur %s.',
            $crees,
            $ignores,
            $erreurs,
            $createur->email
        ));
    }

    private function resoudreCreateur(): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(self::EMAIL_CREATEUR)])
            ->first();
    }

    /**
     * Sépare « 050333232 / 044323232 » (ou virgule / point-virgule / « et ») en tél. 1 et 2.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function decomposerTelephones(mixed $telephone): array
    {
        if ($telephone === null) {
            return [null, null];
        }

        $texte = trim((string) $telephone);
        if ($texte === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*(?:\/|,|;|\bet\b)\s*/iu', $texte) ?: [];
        $parts = array_values(array_filter(
            array_map(static fn (string $p): string => trim($p), $parts),
            static fn (string $p): bool => $p !== ''
        ));

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }
}
