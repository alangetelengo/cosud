<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Moratoire extends Model
{
    public const STATUT_ACTIF = 'actif';

    public const STATUT_SOLDE = 'solde';

    public const STATUT_ANNULE = 'annule';

    protected $fillable = [
        'fournisseur_libelle',
        'fournisseur_normalise',
        'fournisseur_prestataire_id',
        'montant_dette_initial',
        'montant_echeance_defaut',
        'statut',
        'lieu',
        'date_document',
        'signataire_libelle',
        'observation',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'montant_dette_initial' => 'decimal:2',
            'montant_echeance_defaut' => 'decimal:2',
            'date_document' => 'date',
        ];
    }

    public function echeances(): HasMany
    {
        return $this->hasMany(MoratoireEcheance::class)->orderBy('numero');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fournisseurPrestataire(): BelongsTo
    {
        return $this->belongsTo(FournisseurPrestataire::class, 'fournisseur_prestataire_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'moratoire_document')
            ->withPivot('est_principal')
            ->withTimestamps();
    }

    public function libelleStatut(): string
    {
        return match ($this->statut) {
            self::STATUT_SOLDE => 'Soldé',
            self::STATUT_ANNULE => 'Annulé',
            default => 'Actif',
        };
    }

    public function estActif(): bool
    {
        return $this->statut === self::STATUT_ACTIF;
    }

    public function montantPaye(): float
    {
        return (float) $this->echeances
            ->filter(fn (MoratoireEcheance $e) => $e->estPayee())
            ->sum(fn (MoratoireEcheance $e) => (float) $e->montant_echeance);
    }

    public function soldeRestant(): float
    {
        return max(0, (float) $this->montant_dette_initial - $this->montantPaye());
    }
}
