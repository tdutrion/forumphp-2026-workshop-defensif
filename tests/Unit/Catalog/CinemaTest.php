<?php

namespace App\Tests\Unit\Catalog;

use App\Catalog\Coordinates;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use PHPUnit\Framework\TestCase;

final class CinemaTest extends TestCase
{
    public function testExposesItsPositionAsOneValue(): void
    {
        // Arrange
        $cinema = CinemaBuilder::aCinema()->in(CityBuilder::aCity()->build())->at(47.318031, 5.029935)->build();

        // Act
        $coordinates = $cinema->coordinates;

        // Assert
        self::assertEquals(new Coordinates(47.318031, 5.029935), $coordinates);
    }

    public function testHasNoPositionUntilItIsLocated(): void
    {
        // Arrange
        $cinema = CinemaBuilder::aCinema()->in(CityBuilder::aCity()->build())->build();

        // Act
        $cinema->locate(null);

        // Assert
        self::assertNull($cinema->coordinates);
    }

    public function testCloseAndReopen(): void
    {
        // Arrange
        $cinema = CinemaBuilder::aCinema()->in(CityBuilder::aCity()->build())->build();

        // Act
        $cinema->close();
        $closed = $cinema->open;
        $cinema->reopen();

        // Assert
        self::assertFalse($closed);
        self::assertTrue($cinema->open);
    }

    public function testFollowsAChainOnlyInAKnownTimeZone(): void
    {
        // Arrange
        $cinema = CinemaBuilder::aCinema()->in(CityBuilder::aCity()->build())->build();

        // Assert
        $this->expectException(\DateInvalidTimeZoneException::class);

        // Act
        $cinema->follow('FR', 'Europe/Nowhere', 'fr');
    }
}
