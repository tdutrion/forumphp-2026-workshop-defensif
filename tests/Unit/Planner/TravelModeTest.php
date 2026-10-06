<?php

namespace App\Tests\Unit\Planner;

use App\Planner\TravelMode;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TravelModeTest extends TestCase
{
    #[TestWith([TravelMode::Walking, 5, 0])]
    #[TestWith([TravelMode::Cycling, 15, 0])]
    #[TestWith([TravelMode::Transit, 20, 10])]
    #[TestWith([TravelMode::Car, 30, 15])]
    public function testEachModeHasItsSpeedAndItsFixedMinutes(TravelMode $mode, int $speedKmh, int $fixedMinutes): void
    {
        // Act and assert
        self::assertSame($speedKmh, $mode->speedKmh());
        self::assertSame($fixedMinutes, $mode->fixedMinutes());
    }

    public function testAnUnknownModeIsRefused(): void
    {
        // Assert
        $this->expectException(\ValueError::class);

        // Act
        TravelMode::from('teleport');
    }
}
