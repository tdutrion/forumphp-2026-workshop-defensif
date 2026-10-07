<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\CountryCode;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CountryCodeTest extends TestCase
{
    public function testIsUpperCasedAndNamedInTheLanguageOfTheReader(): void
    {
        // Act
        $country = new CountryCode('fr');

        // Assert
        self::assertSame('FR', $country->value);
        self::assertSame('FR', (string) $country);
        self::assertSame('France', $country->name('en'));
        self::assertSame('Royaume-Uni', (new CountryCode('GB'))->name('fr'));
    }

    #[TestWith([''])]
    #[TestWith(['FRA'])]
    #[TestWith(['XX'])]
    #[TestWith(['F'])]
    public function testRefusesWhatIsNotACountry(string $code): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new CountryCode($code);
    }
}
