<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentValidation extends Model
{
    protected $fillable = [
        'document_id',
        'workflow_etape_id',
        'user_id',
        'action',
        'commentaire',
    ];

    public const ACTION_APPROBATION = 'approbation';
    public const ACTION_REJET = 'rejet';

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function workflowEtape(): BelongsTo
    {
        return $this->belongsTo(WorkflowEtape::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estApprobation(): bool
    {
        return $this->action === self::ACTION_APPROBATION;
    }
}
