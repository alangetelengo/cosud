<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensCourrier extends Model
{
    public const ARRIVEE = 'arrivee';

    public const DEPART = 'depart';

    protected $fillable = ['code', 'libelle', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function statuts(): HasMany
    {
        return $this->hasMany(StatutCourrier::class)->orderBy('ordre');
    }

    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class);
    }
}
