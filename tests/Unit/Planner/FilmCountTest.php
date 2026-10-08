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

    public function testIgnoringTheResultOfFewerIsReported(): void
    {
        // Arrange: fewer() returns a new count, it does not change this one; ignoring it is a bug.
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, \E_USER_WARNING);

        try {
            // Act
            \call_user_func([new FilmCount(3), 'fewer']); // PHPStan would flag a plain call, which is the point
        } finally {
            restore_error_handler();
        }

        // Assert
        self::assertCount(1, $warnings);
        self::assertStringContainsString('fewer', $warnings[0]);
    }

    public function testIgnoringTheResultIntentionallyIsAllowed(): void
    {
        // Arrange
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, \E_USER_WARNING);

        try {
            // Act
            (void) (new FilmCount(3))->fewer();
        } finally {
            restore_error_handler();
        }

        // Assert
        self::assertSame([], $warnings);
    }
}
