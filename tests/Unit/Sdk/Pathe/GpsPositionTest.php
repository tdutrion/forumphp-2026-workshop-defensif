<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\GpsPosition;
use PHPUnit\Framework\TestCase;

final class GpsPositionTest extends TestCase
{
    public function testPatheGivesTheLatitudeInXAndTheLongitudeInY(): void
    {
        // Act
        $position = GpsPosition::fromApi(['x' => 47.318031, 'y' => 5.029935]);

        // Assert
        self::assertNotNull($position);
        self::assertSame(47.318031, $position->latitude);
        self::assertSame(5.029935, $position->longitude);
    }

    public function testNoPositionWithoutBothCoordinates(): void
    {
        // Act and assert
        self::assertNull(GpsPosition::fromApi([]));
        self::assertNull(GpsPosition::fromApi(['x' => 47.318031]));
        self::assertNull(GpsPosition::fromApi(['x' => null, 'y' => null]));
    }

    public function testRefusesALatitudeOutOfRange(): void
    {
        // Assert: a latitude above 90° is a longitude put in the wrong place.
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new GpsPosition(latitude: 147.3, longitude: 5.0);
    }
}
