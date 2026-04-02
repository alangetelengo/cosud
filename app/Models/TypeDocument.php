<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeDocument extends Model
{
    protected $fillable = [
        'code',
        'libelle',
        'description',
        'extension_defaut',
        'taille_max_ko',
        'duree_conservation_annees',
        'actif',
        'validation_obligatoire',
        'niveau_validation_final',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'duree_conservation_annees' => 'integer',
            'validation_obligatoire' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function getTailleMaxBytesAttribute(): int
    {
        return $this->taille_max_ko * 1024;
    }

    /** Libellé affiché pour la durée de conservation (règles métier / archivistiques). */
    public function libelleDureeConservation(): string
    {
        if ($this->duree_conservation_annees === null) {
            return 'Non défini';
        }
        if ((int) $this->duree_conservation_annees === 0) {
            return 'Permanent';
        }

        $n = (int) $this->duree_conservation_annees;

        return $n.' '.($n === 1 ? 'an' : 'ans');
    }
}
