<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeDossier extends Model
{
    protected $fillable = [
        'code',
        'libelle',
        'description',
        'icone_defaut',
        'couleur_defaut',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function dossiers(): HasMany
    {
        return $this->hasMany(Dossier::class);
    }

    /** Aligné sur {@see DossierController::store} (types « projet » / « project »). */
    public function estProjet(): bool
    {
        return in_array(mb_strtolower((string) $this->code), ['projet', 'project'], true);
    }
}
