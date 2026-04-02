<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatutDocument extends Model
{
    protected $fillable = ['code', 'libelle', 'est_initial', 'est_final', 'actif', 'ordre'];

    protected function casts(): array
    {
        return [
            'est_initial' => 'boolean',
            'est_final' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
