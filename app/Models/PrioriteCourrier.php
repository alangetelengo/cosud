<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrioriteCourrier extends Model
{
    protected $table = 'priorite_courriers';

    protected $fillable = ['code', 'libelle', 'ordre', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class);
    }
}
