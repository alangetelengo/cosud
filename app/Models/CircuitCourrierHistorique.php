<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircuitCourrierHistorique extends Model
{
    protected $fillable = [
        'courrier_id',
        'circuit_courrier_etape_id',
        'user_id',
        'evenement',
        'commentaire',
    ];

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function etape(): BelongsTo
    {
        return $this->belongsTo(CircuitCourrierEtape::class, 'circuit_courrier_etape_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
