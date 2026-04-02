<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierPartage extends Model
{
    protected $fillable = [
        'dossier_id',
        'user_id',
        'partage_par_id',
        'droits_lecture',
        'droits_ecriture',
        'droits_suppression',
        'propager_aux_sous_dossiers',
        'date_expiration',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'droits_lecture' => 'boolean',
            'droits_ecriture' => 'boolean',
            'droits_suppression' => 'boolean',
            'propager_aux_sous_dossiers' => 'boolean',
            'date_expiration' => 'datetime',
        ];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partagePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partage_par_id');
    }

    public function isExpire(): bool
    {
        return $this->date_expiration && $this->date_expiration->isPast();
    }
}
