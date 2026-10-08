<?php

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\PatheMapper;
use App\Sdk\Pathe\PatheShowtimes;
use App\Tests\Builder\PatheApiBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class PatheMapperTest extends TestCase
{
    /**
     * Raw showtimes of Digger at Pathé Dijon on 2026-10-04: V3345S85501 at 21:40, V3345S85502 at 14:00.
     */
    private function rawShowtimes(): array
    {
        $responses = PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withFilm('digger-51293', 'Digger', 129)
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 21:40:00', 'V3345S85501')
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 14:00:00', 'V3345S85502')
            ->build();

        return json_decode($responses['show/digger-51293/showtimes/cinema-pathe-dijon'][0], true, 512, \JSON_THROW_ON_ERROR);
    }

    public function testConvertsTheLocalTimesOfPatheToUtc(): void
    {
        // Arrange
        $showtimes = PatheShowtimes::fromApiResponse($this->rawShowtimes());

        // Act
        $mapped = (new PatheMapper())->mapShowtimes($showtimes, new \DateTimeZone('Europe/Paris'));

        // Assert
        self::assertSame(['2026-10-04 19:40:00', '2026-10-04 12:00:00'], array_column($mapped, 'startsAt'));
        self::assertSame(['2026-10-04', '2026-10-04'], array_column($mapped, 'localDate'));
    }

    public function testAMalformedDateSkipsThatShowtimeOnly(): void
    {
        // Arrange
        $raw = $this->rawShowtimes();
        $raw['2026-10-04'][0]['time'] = 'demain soir';
        $logger = new BufferingLogger();

        // Act
        $mapped = (new PatheMapper($logger))->mapShowtimes(PatheShowtimes::fromApiResponse($raw), new \DateTimeZone('Europe/Paris'));

        // Assert
        self::assertSame(['V3345S85502'], array_column($mapped, 'id'));
        self::assertSame('V3345S85501', $logger->cleanLogs()[0][2]['showtime'], 'the skipped showtime is logged');
    }
}
