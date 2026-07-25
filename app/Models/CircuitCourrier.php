<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CircuitCourrier extends Model
{
    public const SENS_ARRIVEE = 'arrivee';

    public const SENS_DEPART = 'depart';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'sens_initial',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(CircuitCourrierEtape::class)->orderBy('ordre');
    }

    public function etapesActives(): HasMany
    {
        return $this->etapes()->where('actif', true);
    }

    public function premiereEtape(): ?CircuitCourrierEtape
    {
        return $this->etapesActives()->orderBy('ordre')->first();
    }

    public function typesCourrier(): HasMany
    {
        return $this->hasMany(TypeCourrier::class);
    }

    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class);
    }
}
