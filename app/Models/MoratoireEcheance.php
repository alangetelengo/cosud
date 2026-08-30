<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoratoireEcheance extends Model
{
    public const MODE_CHEQUE = 'cheque';

    public const MODE_ESPECE = 'espece';

    /** @var list<string> */
    public const MODES_PAIEMENT = [
        self::MODE_CHEQUE,
        self::MODE_ESPECE,
    ];

    protected $fillable = [
        'moratoire_id',
        'numero',
        'montant_dette',
        'montant_echeance',
        'solde',
        'mode_paiement',
        'numero_cheque',
        'banque',
        'observation',
        'date_paiement',
        'periode_mois',
        'periode_annee',
        'suivi_paiement_id',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'montant_dette' => 'decimal:2',
            'montant_echeance' => 'decimal:2',
            'solde' => 'decimal:2',
            'date_paiement' => 'date',
            'periode_annee' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function moisDisponibles(): array
    {
        return [
            'Janvier' => 'Janvier',
            'Février' => 'Février',
            'Mars' => 'Mars',
            'Avril' => 'Avril',
            'Mai' => 'Mai',
            'Juin' => 'Juin',
            'Juillet' => 'Juillet',
            'Août' => 'Août',
            'Septembre' => 'Septembre',
            'Octobre' => 'Octobre',
            'Novembre' => 'Novembre',
            'Décembre' => 'Décembre',
        ];
    }

    public function libellePeriode(): ?string
    {
        if (! $this->periode_mois) {
            return null;
        }

        return $this->periode_annee
            ? $this->periode_mois.' '.$this->periode_annee
            : $this->periode_mois;
    }

    public function libelleModePaiement(): string
    {
        return match ($this->mode_paiement) {
            self::MODE_ESPECE => 'Espèces',
            self::MODE_CHEQUE => 'Chèque',
            default => '—',
        };
    }

    public function moratoire(): BelongsTo
    {
        return $this->belongsTo(Moratoire::class);
    }

    public function suiviPaiement(): BelongsTo
    {
        return $this->belongsTo(SuiviPaiement::class);
    }

    public function estPayee(): bool
    {
        if ($this->suivi_paiement_id !== null) {
            return true;
        }

        if ($this->date_paiement === null) {
            return trim((string) $this->numero_cheque) !== '';
        }

        if (($this->mode_paiement ?? self::MODE_CHEQUE) === self::MODE_ESPECE) {
            return true;
        }

        return trim((string) $this->numero_cheque) !== '';
    }
}
