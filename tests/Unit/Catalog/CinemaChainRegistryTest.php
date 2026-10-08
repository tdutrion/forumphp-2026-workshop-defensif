<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\CinemaChainRegistry;
use App\Catalog\UnknownCinemaChain;
use PHPUnit\Framework\TestCase;

final class CinemaChainRegistryTest extends TestCase
{
    private const array PATHE = ['name' => 'Pathé', 'country' => 'FR', 'timezone' => 'Europe/Paris', 'language' => 'fr'];

    public function testBuildsTheChainsOnceFromTheConfiguration(): void
    {
        // Act
        $registry = new CinemaChainRegistry(['pathe' => self::PATHE], [['name' => 'Cineworld', 'countries' => ['GB', 'IE']]]);

        // Assert
        $pathe = $registry->get('pathe');
        self::assertSame('Pathé', $pathe->name);
        self::assertSame('FR', $pathe->country->value);
        self::assertSame('Europe/Paris', $pathe->timezone->getName());
        self::assertSame([$pathe], $registry->supported());
        self::assertSame(['GB', 'IE'], array_map(strval(...), $registry->planned()[0]->countries));
    }

    public function testAnUnknownChainIsABug(): void
    {
        // Arrange
        $registry = new CinemaChainRegistry(['pathe' => self::PATHE], []);

        // Assert
        $this->expectException(UnknownCinemaChain::class);

        // Act
        $registry->get('ugc');
    }

    public function testATypoInTheConfigurationStopsTheApplicationEarly(): void
    {
        // Assert
        $this->expectException(\DateInvalidTimeZoneException::class);

        // Act
        new CinemaChainRegistry(['pathe' => ['timezone' => 'Europe/Pariss'] + self::PATHE], []);
    }

    public function testAnUnknownCountryIsRefused(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new CinemaChainRegistry(['pathe' => ['country' => 'XX'] + self::PATHE], []);
    }

    public function testAChainIdentifierIsWhatTheRecordsStore(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new CinemaChainRegistry(['Pathé!' => self::PATHE], []);
    }
}
