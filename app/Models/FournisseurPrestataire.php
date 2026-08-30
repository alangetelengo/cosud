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
        'telephone_2',
        'notifier_telephone',
        'notifier_telephone_2',
        'type_contrat',
        'a_contrat',
        'scan_contrat_pieces',
        'a_dossier_fiscal',
        'scan_fiscal_pieces',
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
            'scan_contrat_pieces' => 'array',
            'scan_fiscal_pieces' => 'array',
            'notifier_telephone' => 'boolean',
            'notifier_telephone_2' => 'boolean',
        ];
    }

    public static function normaliserNom(?string $libelle): string
    {
        $texte = mb_strtolower(trim((string) $libelle));
        $texte = str_replace(['²', '³'], ['2', '3'], $texte);
        $texte = preg_replace('/\s+/u', ' ', $texte) ?? '';

        $sansPonctuation = preg_replace('/[.,\-]/u', ' ', $texte) ?? '';
        $sansPonctuation = preg_replace('/\s+/u', ' ', trim($sansPonctuation)) ?? '';

        /** @var array<string, string> $aliases */
        $aliases = [
            'edition les sozo' => 'ed. les sozo',
            'soft renovations' => 'soft-renovation',
            'soft renovation' => 'soft-renovation',
            'metre de luxe' => 'metro de luxe',
            'af,com' => 'afcom',
            'af com' => 'afcom',
            'ets db' => 'ets-db',
        ];

        if (isset($aliases[$texte])) {
            return $aliases[$texte];
        }

        if (isset($aliases[$sansPonctuation])) {
            return $aliases[$sansPonctuation];
        }

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

    public function aScanContrat(): bool
    {
        return $this->piecesContrat() !== [];
    }

    public function aScanFiscal(): bool
    {
        return $this->piecesFiscal() !== [];
    }

    /**
     * @return list<array{chemin: string, nom: string}>
     */
    public function piecesContrat(): array
    {
        return $this->normaliserPieces($this->scan_contrat_pieces);
    }

    /**
     * @return list<array{chemin: string, nom: string}>
     */
    public function piecesFiscal(): array
    {
        return $this->normaliserPieces($this->scan_fiscal_pieces);
    }

    /**
     * @return list<array{chemin: string, nom: string}>
     */
    private function normaliserPieces(mixed $brut): array
    {
        if (! is_array($brut)) {
            return [];
        }

        $pieces = [];
        foreach ($brut as $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $chemin = trim((string) ($piece['chemin'] ?? ''));
            $nom = trim((string) ($piece['nom'] ?? ''));
            if ($chemin === '') {
                continue;
            }
            $pieces[] = [
                'chemin' => $chemin,
                'nom' => $nom !== '' ? $nom : basename($chemin),
            ];
        }

        return $pieces;
    }
}
