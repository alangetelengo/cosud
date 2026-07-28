<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuiviPaiement extends Model
{
    public const TYPE_FSP_FACTURE = 'fsp_facture';

    public const TYPE_FSP_MAD = 'fsp_mad';

    protected $fillable = [
        'courrier_id',
        'type',
        'numero_ligne',
        'numero_annee',
        'date_suivi',
        'intitule',
        'montant',
        'fournisseur_libelle',
        'service_demandeur_libelle',
        'demandeur_libelle',
        'responsable_dossier_id',
        'instruction_dg',
        'observation',
        'etabli_par_id',
    ];

    protected function casts(): array
    {
        return [
            'date_suivi' => 'date',
            'montant' => 'decimal:2',
            'numero_ligne' => 'integer',
            'numero_annee' => 'integer',
        ];
    }

    public function courrier(): BelongsTo
    {
        return $this->belongsTo(Courrier::class);
    }

    public function responsableDossier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_dossier_id');
    }

    public function etabliPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etabli_par_id');
    }

    public function numeroComplet(): string
    {
        return $this->numero_ligne.'/'.$this->numero_annee;
    }

    public function libelleType(): string
    {
        return match ($this->type) {
            self::TYPE_FSP_MAD => 'FSP MAD',
            default => 'FSP FACTURE',
        };
    }
}
