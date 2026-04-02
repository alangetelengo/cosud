<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeMetadonnee extends Model
{
    protected $fillable = ['code', 'libelle', 'type_valeur', 'description', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function metadonnees(): HasMany
    {
        return $this->hasMany(MetadonneeDocument::class, 'type_metadonnee_id');
    }
}
