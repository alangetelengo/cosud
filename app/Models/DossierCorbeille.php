<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierCorbeille extends Model
{
    protected $fillable = [
        'dossier_id',
        'supprime_par_id',
        'date_suppression',
        'raison_suppression',
        'date_expiration',
    ];

    protected function casts(): array
    {
        return [
            'date_suppression' => 'datetime',
            'date_expiration' => 'datetime',
        ];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function supprimePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supprime_par_id');
    }
}
