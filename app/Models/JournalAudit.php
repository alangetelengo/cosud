<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalAudit extends Model
{
    protected $fillable = [
        'action',
        'module',
        'user_id',
        'document_id',
        'dossier_id',
        'adresse_ip',
        'user_agent',
        'donnees_avant',
        'donnees_apres',
        'commentaire',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public static function log(string $action, ?string $module = null, array $attrs = []): self
    {
        return static::create(array_merge([
            'action' => $action,
            'module' => $module,
            'user_id' => auth()->id(),
            'adresse_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $attrs));
    }
}
