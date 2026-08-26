<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoratoireEcheance extends Model
{
    protected $fillable = [
        'moratoire_id',
        'numero',
        'montant_dette',
        'montant_echeance',
        'solde',
        'numero_cheque',
        'banque',
        'observation',
        'date_paiement',
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
        ];
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
        return trim((string) $this->numero_cheque) !== ''
            || $this->suivi_paiement_id !== null
            || $this->date_paiement !== null;
    }
}
