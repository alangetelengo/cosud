<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetadonneeDocument extends Model
{
    protected $table = 'metadonnees_documents';

    protected $fillable = [
        'document_id',
        'type_metadonnee_id',
        'cle',
        'valeur',
        'valeur_numerique',
        'valeur_date',
        'valeur_booleen',
        'ordre_affichage',
    ];

    protected function casts(): array
    {
        return [
            'valeur_numerique' => 'float',
            'valeur_date' => 'datetime',
            'valeur_booleen' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function typeMetadonnee(): BelongsTo
    {
        return $this->belongsTo(TypeMetadonnee::class, 'type_metadonnee_id');
    }

    public function getValeurFormateeAttribute(): string|int|float|bool|null
    {
        return $this->valeur ?? $this->valeur_numerique ?? $this->valeur_date?->format('d/m/Y') ?? ($this->valeur_booleen === null ? null : ($this->valeur_booleen ? 'Oui' : 'Non'));
    }
}
