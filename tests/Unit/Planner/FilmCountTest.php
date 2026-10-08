<?php

namespace App\Tests\Unit\Planner;

use App\Planner\FilmCount;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class FilmCountTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([9])]
    public function testAMarathonHasOneToEightFilms(int $films): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new FilmCount($films);
    }

    public function testFallsBackOneFilmAtATime(): void
    {
        // Arrange
        $four = new FilmCount(4);

        // Act
        $fewer = $four->fewer();

        // Assert
        self::assertSame(3, $fewer->value);
        self::assertTrue($fewer->isFewerThan($four));
        self::assertFalse($four->isFewerThan($fewer));
    }

    public function testNeverFallsBackBelowASingleFilm(): void
    {
        // Arrange
        $single = new FilmCount(1);

        // Act
        $fewer = $single->fewer();

        // Assert
        self::assertSame(1, $fewer->value);
        self::assertTrue($fewer->isSingle());
    }
}
