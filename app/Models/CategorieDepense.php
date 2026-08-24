<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieDepense extends Model
{
    public const CODE_FACTURE = 'facture';

    public const CODE_PAIEMENT_DIVERS = 'paiement_divers';

    public const CODE_PAIE = 'paie';

    public const CODE_COMMISSION = 'commission';

    public const CODE_TTF = 'ttf';

    protected $fillable = [
        'code',
        'libelle',
        'ordre',
        'actif',
        'est_systeme',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
            'est_systeme' => 'boolean',
        ];
    }

    public function suiviPaiements(): HasMany
    {
        return $this->hasMany(SuiviPaiement::class);
    }

    /**
     * @return Collection<int, self>
     */
    public static function activesPourSaisie(): Collection
    {
        return static::query()
            ->where('actif', true)
            ->whereNotIn('code', [self::CODE_FACTURE, self::CODE_PAIEMENT_DIVERS])
            ->orderBy('ordre')
            ->orderBy('libelle')
            ->get();
    }

    /**
     * @return Collection<int, self>
     */
    public static function toutesActives(): Collection
    {
        return static::query()
            ->where('actif', true)
            ->orderBy('ordre')
            ->orderBy('libelle')
            ->get();
    }

    public static function idPourCode(string $code): ?int
    {
        $id = static::query()->where('code', $code)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function codeDepuisTypeLegacy(string $type): string
    {
        return match ($type) {
            SuiviPaiement::TYPE_FSP_FACTURE => self::CODE_FACTURE,
            SuiviPaiement::TYPE_FSP_MAD => self::CODE_PAIEMENT_DIVERS,
            SuiviPaiement::TYPE_FSP_PAIE => self::CODE_PAIE,
            SuiviPaiement::TYPE_FSP_COMMISSION => self::CODE_COMMISSION,
            SuiviPaiement::TYPE_FSP_TTF => self::CODE_TTF,
            default => self::CODE_PAIEMENT_DIVERS,
        };
    }
}
