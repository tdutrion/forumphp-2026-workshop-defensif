<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Entity\Cinema;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\StoresEntities;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CinemaStorageTest extends KernelTestCase
{
    use StoresEntities;

    public function testTheCountryAndTheTimeZoneAreStoredAsTheirNamesAndComeBackAsObjects(): void
    {
        // Arrange
        self::bootKernel();
        $city = CityBuilder::aCity()->build();
        $this->store($city, CinemaBuilder::aCinema()->in($city)->inTimezone('Europe/London')->build());
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        // Act
        $cinema = self::getContainer()->get(EntityManagerInterface::class)->find(Cinema::class, 'cinema-pathe-dijon');
        $row = self::getContainer()->get(Connection::class)->fetchAssociative('SELECT country, timezone FROM cinema');

        // Assert
        self::assertSame('FR', $cinema?->country->value);
        self::assertSame('Europe/London', $cinema->timezone->getName());
        self::assertSame(['country' => 'FR', 'timezone' => 'Europe/London'], $row);
    }
}
