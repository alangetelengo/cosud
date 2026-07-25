<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CircuitCourrierEtape extends Model
{
    public const ACTEUR_ROLE = 'role';

    public const ACTEUR_FONCTION = 'fonction';

    public const ACTEUR_SECRETARIAT = 'secretariat';

    public const ACTEUR_DG = 'dg';

    /**
     * Directeur de la structure destinataire du courrier (résolu dynamiquement via
     * `CircuitCourrierMoteurService::resoudreActeurDirecteur()`), avec repli sur le DG
     * lorsque le destinataire est la Direction Générale elle-même ou n’est pas renseigné.
     */
    public const ACTEUR_DIRECTEUR_DESTINATAIRE = 'directeur_destinataire';

    public const ACTION_ENREGISTRER = 'enregistrer';

    public const ACTION_INSTRUIRE = 'instruire';

    public const ACTION_TRAITER = 'traiter';

    public const ACTION_TRANSMETTRE = 'transmettre';

    public const ACTION_SIGNER = 'signer';

    public const ACTION_VALIDER = 'valider';

    public const ACTION_CLOTURER = 'cloturer';

    public const ACTION_NOTIFIER = 'notifier';

    public const MOUVEMENT_AUCUN = 'aucun';

    public const MOUVEMENT_CREER_DEPART = 'creer_depart';

    public const MOUVEMENT_ATTENDRE_ARRIVEE = 'attendre_arrivee';

    protected $fillable = [
        'circuit_courrier_id',
        'ordre',
        'code',
        'nom',
        'acteur_type',
        'acteur_valeur',
        'action',
        'mouvement',
        'notifie_roles',
        'instructions_aide',
        'est_finale',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'notifie_roles' => 'array',
            'est_finale' => 'boolean',
            'actif' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitCourrier::class, 'circuit_courrier_id');
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(CircuitCourrierHistorique::class);
    }

    public function etapeSuivante(): ?self
    {
        return static::query()
            ->where('circuit_courrier_id', $this->circuit_courrier_id)
            ->where('actif', true)
            ->where('ordre', '>', $this->ordre)
            ->orderBy('ordre')
            ->first();
    }

    /**
     * Indique si cette étape se termine naturellement par la création d’un courrier départ
     * (elle-même, ou l’étape suivante confiée au même acteur), auquel cas aucune validation
     * manuelle séparée n’est nécessaire : le bouton « Créer courrier réponse » suffit.
     */
    public function meneVersCreationDepart(): bool
    {
        if ($this->mouvement === self::MOUVEMENT_CREER_DEPART) {
            return true;
        }

        $suivante = $this->etapeSuivante();

        return (bool) $suivante
            && $suivante->mouvement === self::MOUVEMENT_CREER_DEPART
            && $suivante->acteur_type === $this->acteur_type
            && $suivante->acteur_valeur === $this->acteur_valeur;
    }

    public function libelleActeur(): string
    {
        return match ($this->acteur_type) {
            self::ACTEUR_SECRETARIAT => 'Secrétariat DG',
            self::ACTEUR_DG => 'Directeur Général',
            self::ACTEUR_DIRECTEUR_DESTINATAIRE => 'Directeur Général ou directeur de la structure destinataire',
            self::ACTEUR_FONCTION => 'Fonction : '.($this->acteur_valeur ?: '—'),
            default => 'Rôle : '.($this->acteur_valeur ?: '—'),
        };
    }

    public function libelleAction(): string
    {
        return match ($this->action) {
            self::ACTION_ENREGISTRER => 'Enregistrer',
            self::ACTION_INSTRUIRE => 'Instruire / orienter',
            self::ACTION_TRAITER => 'Traiter',
            self::ACTION_TRANSMETTRE => 'Transmettre',
            self::ACTION_SIGNER => 'Signer',
            self::ACTION_VALIDER => 'Valider / rejeter',
            self::ACTION_CLOTURER => 'Clôturer',
            self::ACTION_NOTIFIER => 'Notifier',
            default => $this->action,
        };
    }

    public function libelleMouvement(): string
    {
        return match ($this->mouvement) {
            self::MOUVEMENT_CREER_DEPART => 'Créer un courrier départ',
            self::MOUVEMENT_ATTENDRE_ARRIVEE => 'Attendre un courrier arrivée lié',
            default => 'Aucun',
        };
    }
}
