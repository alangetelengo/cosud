<?php

namespace App\Models;

use Database\Factories\FournisseurPrestataireFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FournisseurPrestataire extends Model
{
    /** @use HasFactory<FournisseurPrestataireFactory> */
    use HasFactory;

    public const TYPE_FOURNISSEUR = 'fournisseur';

    public const TYPE_PRESTATAIRE = 'prestataire';

    public const TYPE_PARTENAIRE = 'partenaire';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_FOURNISSEUR,
        self::TYPE_PRESTATAIRE,
        self::TYPE_PARTENAIRE,
    ];

    protected $fillable = [
        'nom',
        'nom_normalise',
        'type',
        'email',
        'telephone',
        'type_contrat',
        'a_contrat',
        'a_dossier_fiscal',
        'observation',
        'dossier_id',
        'actif',
        'createur_id',
    ];

    protected function casts(): array
    {
        return [
            'a_contrat' => 'boolean',
            'a_dossier_fiscal' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public static function normaliserNom(?string $libelle): string
    {
        $texte = mb_strtolower(trim((string) $libelle));
        $texte = preg_replace('/\s+/u', ' ', $texte) ?? '';

        return $texte;
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class, 'fournisseur_prestataire_id');
    }

    public function moratoires(): HasMany
    {
        return $this->hasMany(Moratoire::class, 'fournisseur_prestataire_id');
    }

    public function suiviPaiements(): HasMany
    {
        return $this->hasMany(SuiviPaiement::class, 'fournisseur_prestataire_id');
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function libelleType(): string
    {
        return match ($this->type) {
            self::TYPE_PRESTATAIRE => 'Prestataire',
            self::TYPE_PARTENAIRE => 'Partenaire',
            default => 'Fournisseur',
        };
    }

    public function libelleContratCourt(): string
    {
        return $this->a_contrat ? 'Oui' : 'Non';
    }

    public function libelleDossierFiscalCourt(): string
    {
        return $this->a_dossier_fiscal ? 'Oui' : 'Non';
    }
}
