<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Format montant FCFA : séparateur de milliers = espace (ex. 1 949 700).
 */
final class MontantFcfa
{
    public static function formater(float|int|string|null $montant): string
    {
        if ($montant === null || $montant === '') {
            return '';
        }

        if (is_string($montant)) {
            $montant = self::versFloat($montant);
        }

        return number_format((float) $montant, 0, ',', ' ');
    }

    /**
     * Retire espaces / séparateurs pour validation ou persistance.
     */
    public static function normaliser(mixed $montant): string
    {
        return preg_replace('/\s+/', '', (string) $montant) ?? '';
    }

    public static function versFloat(mixed $montant): float
    {
        return (float) self::normaliser($montant);
    }

    /**
     * Valeur à afficher dans un champ (old() ou montant stocké).
     */
    public static function pourSaisie(mixed $montant): string
    {
        if ($montant === null || $montant === '') {
            return '';
        }

        return self::formater(self::versFloat($montant));
    }

    /**
     * Montant en lettres (français), ex. « Sept cent mille ».
     */
    public static function enLettres(float|int|string|null $montant): string
    {
        $entier = (int) round(self::versFloat($montant ?? 0));

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);
            $lettres = $formatter->format($entier);

            if (is_string($lettres) && $lettres !== '') {
                return Str::ucfirst($lettres);
            }
        }

        return self::formater($entier);
    }
}
