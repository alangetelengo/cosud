<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parapheur extends Model
{
    protected $fillable = ['sens_courrier_id', 'code', 'libelle', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
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
