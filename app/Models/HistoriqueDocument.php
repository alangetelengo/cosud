<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueDocument extends Model
{
    protected $fillable = [
        'document_id',
        'version_document_id',
        'operation',
        'user_id',
        'commentaire',
        'details',
        'adresse_ip',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function versionDocument(): BelongsTo
    {
        return $this->belongsTo(VersionDocument::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function enregistrer(Document $doc, string $operation, ?int $versionId = null, ?string $commentaire = null, array $details = []): self
    {
        return static::create([
            'document_id' => $doc->id,
            'version_document_id' => $versionId,
            'operation' => $operation,
            'user_id' => auth()->id(),
            'commentaire' => $commentaire,
            'details' => $details,
            'adresse_ip' => request()->ip(),
        ]);
    }
}
