<?php

namespace Tests\Unit;

use App\Support\MontantFcfa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

class MontantFcfaTest extends TestCase
{
    public function test_formater_separe_les_milliers_par_espace(): void
    {
        $this->assertSame('1 949 700', MontantFcfa::formater(1949700));
    }

    /**
     * Conversion dynamique (NumberFormatter), comme progcaisse — tout montant.
     *
     * @return array<string, array{0: int|float|string, 1: list<string>}>
     */
    public static function montantsEnLettresProvider(): array
    {
        return [
            'petit montant' => [1500, ['mille', 'cinq']],
            'exemple liste' => [700000, ['sept', 'cent', 'mille']],
            'montant composé' => [1250000, ['million', 'deux', 'cent', 'cinquante']],
        ];
    }

    #[RequiresPhpExtension('intl')]
    #[DataProvider('montantsEnLettresProvider')]
    public function test_en_lettres_convertit_dynamiquement(int|float|string $montant, array $fragments): void
    {
        $lettres = MontantFcfa::enLettres($montant);

        foreach ($fragments as $fragment) {
            $this->assertStringContainsStringIgnoringCase($fragment, $lettres);
        }
    }
}
