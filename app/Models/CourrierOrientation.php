<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourrierOrientation extends Model
{
    public const DEST_SECRETARIAT = 'secretariat';

    public const DEST_DIRECTEUR = 'directeur';

    public const DEST_PARTICULIERE = 'particuliere';

    protected $fillable = [
        'courrier_id',
        'structure_id',
        'destinataire_type',
        'destinataire_user_id',
        'instructions',
        'oriente_par_id',
    ];

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function destinataireUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_user_id');
    }

    public function orientePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oriente_par_id');
    }

    public function libelleDestinataire(): string
    {
        return match ($this->destinataire_type) {
            self::DEST_SECRETARIAT => 'Secrétariat de direction',
            self::DEST_DIRECTEUR => 'Directeur de direction',
            self::DEST_PARTICULIERE => 'Particulière du DG',
            default => '—',
        };
    }
}
