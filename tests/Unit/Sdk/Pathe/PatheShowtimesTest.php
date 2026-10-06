<?php

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\PatheShowtimes;
use App\Tests\Builder\PatheApiBuilder;
use PHPUnit\Framework\TestCase;

final class PatheShowtimesTest extends TestCase
{
    public function testShowtimesIndexedByDateBecomeOneList(): void
    {
        // Arrange
        $responses = PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withFilm('digger-51293', 'Digger', 129)
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 21:40:00', 'V3345S85501')
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-05 14:00:00', 'V3345S85502')
            ->build();
        $raw = json_decode($responses['show/digger-51293/showtimes/cinema-pathe-dijon'][0], true, 512, \JSON_THROW_ON_ERROR);

        // Act
        $showtimes = PatheShowtimes::fromApiResponse($raw);

        // Assert
        self::assertSame(['2026-10-04 21:40:00', '2026-10-05 14:00:00'], array_column($showtimes->items, 'time'));
    }

    public function testAnEmptyListMeansNoShowtime(): void
    {
        // Act
        $showtimes = PatheShowtimes::fromApiResponse([]);

        // Assert
        self::assertSame([], $showtimes->items);
    }

    public function testRefusesAListOfShowtimesThatIsNotIndexedByDate(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        PatheShowtimes::fromApiResponse([['time' => '2026-10-04 21:40:00']]);
    }

    public function testRefusesADayThatIsNotAListOfShowtimes(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        PatheShowtimes::fromApiResponse(['2026-10-04' => ['time' => '2026-10-04 21:40:00']]);
    }
}
