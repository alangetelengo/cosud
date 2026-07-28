<?php

namespace Tests\Unit;

use App\Services\SmsService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmsServiceNormalizeTest extends TestCase
{
    #[DataProvider('phonesCongo')]
    public function test_normalise_les_numeros_congo(string $input, string $expected): void
    {
        $this->assertSame($expected, app(SmsService::class)->normalizeSmsPhone($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function phonesCongo(): array
    {
        return [
            'international_plus' => ['+242066835332', '242066835332'],
            'international_sans_plus' => ['242066835332', '242066835332'],
            'national_avec_zero' => ['066835332', '242066835332'],
            'national_8_chiffres' => ['66835332', '242066835332'],
            'vide' => ['', ''],
        ];
    }

    public function test_sanitize_retire_les_accents(): void
    {
        $texte = app(SmsService::class)->sanitizeSmsText('Dossier VALIDÉ — clôturé');

        $this->assertStringNotContainsString('É', $texte);
        $this->assertStringNotContainsString('—', $texte);
        $this->assertStringContainsString('VALIDE', $texte);
    }
}
