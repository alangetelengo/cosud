<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourrierTransmission extends Model
{
    protected $fillable = [
        'courrier_id',
        'de_structure_id',
        'vers_structure_id',
        'de_user_id',
        'vers_user_id',
        'date_transmission',
        'accuse_reception',
        'accuse_chemin',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_transmission' => 'datetime',
            'accuse_reception' => 'boolean',
        ];
    }

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function deStructure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'de_structure_id');
    }

    public function versStructure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'vers_structure_id');
    }

    public function deUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'de_user_id');
    }

    public function versUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vers_user_id');
    }
}
