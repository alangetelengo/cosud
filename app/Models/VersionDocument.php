<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VersionDocument extends Model
{
    protected $fillable = [
        'document_id',
        'numero',
        'chemin',
        'nom_fichier',
        'mime_type',
        'taille_octets',
        'empreinte',
        'commentaire',
        'auteur_id',
        'est_actuel',
    ];

    protected function casts(): array
    {
        return [
            'est_actuel' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function getTailleFormateeAttribute(): string
    {
        $o = $this->taille_octets ?? 0;
        if ($o >= 1048576) {
            return number_format($o / 1048576, 2, ',', ' ') . ' Mo';
        }
        if ($o >= 1024) {
            return number_format($o / 1024, 2, ',', ' ') . ' Ko';
        }
        return $o . ' o';
    }
}
