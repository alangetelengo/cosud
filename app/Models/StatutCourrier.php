<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatutCourrier extends Model
{
    protected $fillable = [
        'sens_courrier_id', 'code', 'libelle', 'ordre', 'est_initial', 'est_final', 'actif',
    ];

    protected function casts(): array
    {
        return [
            'est_initial' => 'boolean',
            'est_final' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function sensCourrier(): BelongsTo
    {
        return $this->belongsTo(SensCourrier::class);
    }

    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class);
    }
}
