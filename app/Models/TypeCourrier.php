<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeCourrier extends Model
{
    protected $fillable = [
        'code',
        'libelle',
        'actif',
        'circuit_courrier_id',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitCourrier::class, 'circuit_courrier_id');
    }

    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class);
    }
}
