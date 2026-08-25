<?php

namespace App\Models;

use App\Services\SuiviDepenseClassementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuiviPaiement extends Model
{
    public const TYPE_FSP_FACTURE = 'fsp_facture';

    public const TYPE_FSP_MAD = 'fsp_mad';

    public const TYPE_FSP_PAIE = 'fsp_paie';

    public const TYPE_FSP_COMMISSION = 'fsp_commission';

    public const TYPE_FSP_TTF = 'fsp_ttf';

    public const TYPE_FSP_MANUEL = 'fsp_manuel';

    public const ORIGINE_CIRCUIT_CHEQUE = 'circuit_cheque';

    public const ORIGINE_REMISE_DG = 'remise_dg';

    /**
     * @return list<string>
     */
    public static function typesCircuit(): array
    {
        return [
            self::TYPE_FSP_FACTURE,
            self::TYPE_FSP_MAD,
        ];
    }

    /**
     * Types saisis hors circuit (remise personnelle DG → Eleni).
     *
     * @return list<string>
     */
    public static function typesRemiseDg(): array
    {
        return [
            self::TYPE_FSP_PAIE,
            self::TYPE_FSP_COMMISSION,
            self::TYPE_FSP_TTF,
        ];
    }

    /**
     * @return list<string>
     */
    public static function tousLesTypes(): array
    {
        return array_merge(self::typesCircuit(), self::typesRemiseDg());
    }

    protected $fillable = [
        'courrier_id',
        'type',
        'categorie_depense_id',
        'dossier_id',
        'origine',
        'numero_ligne',
        'numero_annee',
        'date_suivi',
        'date_decharge',
        'intitule',
        'montant',
        'numero_piece',
        'banque',
        'beneficiaire_libelle',
        'programmation',
        'fournisseur_libelle',
        'service_demandeur_libelle',
        'demandeur_libelle',
        'responsable_dossier_id',
        'instruction_dg',
        'observation',
        'etabli_par_id',
        'controle_par_id',
        'controle_at',
    ];

    protected function casts(): array
    {
        return [
            'date_suivi' => 'date',
            'date_decharge' => 'date',
            'montant' => 'decimal:2',
            'numero_ligne' => 'integer',
            'numero_annee' => 'integer',
            'controle_at' => 'datetime',
        ];
    }

    public function categorieDepense(): BelongsTo
    {
        return $this->belongsTo(CategorieDepense::class);
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * Dossier métier affiché : lien direct sur la fiche, sinon dossier du courrier (classement Taty).
     */
    public function dossierEffectif(): ?Dossier
    {
        if ($this->relationLoaded('dossier') && $this->dossier) {
            return $this->dossier;
        }

        if ($this->dossier_id) {
            return $this->dossier()->first();
        }

        $this->loadMissing('courrier.dossier');

        return $this->courrier?->dossier;
    }

    public function estClasseMetier(): bool
    {
        return app(SuiviDepenseClassementService::class)->estClasseMetier($this);
    }

    /**
     * Classement COSUD réservé à Mme Taty (factures prestataires / circuit chèque facture).
     */
    public function estClassementReserveFacturesPrestataires(): bool
    {
        if ($this->origine !== self::ORIGINE_CIRCUIT_CHEQUE) {
            return false;
        }

        if ($this->type === self::TYPE_FSP_FACTURE) {
            return true;
        }

        $this->loadMissing('categorieDepense');

        return $this->categorieDepense?->code === CategorieDepense::CODE_FACTURE;
    }

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function responsableDossier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_dossier_id');
    }

    public function etabliPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etabli_par_id');
    }

    public function controlePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controle_par_id');
    }

    public function numeroComplet(): string
    {
        return $this->numero_ligne.'/'.$this->numero_annee;
    }

    public function libelleType(): string
    {
        return match ($this->type) {
            self::TYPE_FSP_MAD => 'FSP MAD',
            self::TYPE_FSP_PAIE => 'FSP PAIE',
            self::TYPE_FSP_COMMISSION => 'FSP COMMISSION',
            self::TYPE_FSP_TTF => 'FSP TTF',
            default => 'FSP FACTURE',
        };
    }

    public function estRemiseDg(): bool
    {
        return $this->origine === self::ORIGINE_REMISE_DG
            || in_array($this->type, self::typesRemiseDg(), true);
    }
}
