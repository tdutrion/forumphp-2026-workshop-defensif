<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Coordinates;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CoordinatesTest extends TestCase
{
    public function testHoldsALatitudeAndALongitude(): void
    {
        // Act
        $dijon = new Coordinates(latitude: 47.318031, longitude: 5.029935);

        // Assert
        self::assertSame(47.318031, $dijon->latitude);
        self::assertSame(5.029935, $dijon->longitude);
    }

    #[TestWith([90.1, 0.0])]
    #[TestWith([-90.1, 0.0])]
    #[TestWith([0.0, 180.1])]
    #[TestWith([0.0, -180.1])]
    public function testRefusesAPositionOffTheGlobe(float $latitude, float $longitude): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new Coordinates($latitude, $longitude);
    }
}
