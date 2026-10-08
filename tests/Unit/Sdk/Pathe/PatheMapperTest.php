<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\PatheMapper;
use App\Sdk\Pathe\PatheShowtimes;
use App\Tests\Builder\PatheApiBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function refusedBookingLinks(): array
    {
        return [
            'another host' => ['https://s.pathe.fr.evil.example/fr/V3345S85501/booking'],
            'credentials hiding the host' => ['https://s.pathe.fr@evil.example/fr/V3345S85501/booking'],
            'not https' => ['http://s.pathe.fr/fr/V3345S85501/booking'],
            'a script' => ['javascript:alert(1)'],
            'relative' => ['/fr/V3345S85501/booking'],
            'no showtime in the path' => ['https://s.pathe.fr/fr/booking'],
            'the id is the last segment' => ['https://s.pathe.fr/fr/V3345S85501'],
            'not a link' => ['not a link'],
            'none' => [null],
        ];
    }

    #[DataProvider('refusedBookingLinks')]
    public function testOnlyPatheHttpsBookingLinksAreAccepted(?string $link): void
    {
        // Arrange
        $raw = $this->rawShowtimes();
        $raw[array_key_first($raw)][0]['refCmd'] = $link;
        $showtimes = PatheShowtimes::fromApiResponse(array_map(static fn (array $day): array => [$day[0]], $raw));

        // Act
        $mapped = (new PatheMapper())->mapShowtimes($showtimes, new \DateTimeZone('Europe/Paris'));

        // Assert
        self::assertSame([], $mapped);
    }

    public function testTheShowtimeIdIsASegmentOfTheBookingPath(): void
    {
        // Arrange
        $raw = $this->rawShowtimes();
        $raw[array_key_first($raw)][0]['refCmd'] = 'https://S.PATHE.FR/pathe/fr/V3345S85501/booking?utm=x';
        $showtimes = PatheShowtimes::fromApiResponse(array_map(static fn (array $day): array => [$day[0]], $raw));

        // Act
        $mapped = (new PatheMapper())->mapShowtimes($showtimes, new \DateTimeZone('Europe/Paris'));

        // Assert
        self::assertSame(['V3345S85501'], array_column($mapped, 'id'));
    }

    public function testAPosterIsKeptOnlyOverHttps(): void
    {
        // Arrange
        $film = static fn (string $poster): array => ['isMovie' => true, 'slug' => 'digger-51293', 'title' => 'Digger', 'posterPath' => ['md' => $poster]];
        $mapper = new PatheMapper();

        // Act
        $https = $mapper->mapFilm($film('https://image.pathe.fr/digger.jpg'));
        $http = $mapper->mapFilm($film('http://image.pathe.fr/digger.jpg'));
        $relative = $mapper->mapFilm($film('//image.pathe.fr/digger.jpg'));

        // Assert
        self::assertIsArray($https);
        self::assertIsArray($http);
        self::assertIsArray($relative);
        self::assertSame('https://image.pathe.fr/digger.jpg', $https['posterUrl']);
        self::assertNull($http['posterUrl']);
        self::assertNull($relative['posterUrl']);
    }

    public function testReadsTheDirectorsOfAFilmPage(): void
    {
        // Act
        $details = (new PatheMapper())->mapFilmDetails(['title' => 'Digger', 'directors' => 'Alejandro G. Iñárritu, , Jane  Doe ']);

        // Assert
        self::assertSame(['Alejandro G. Iñárritu', 'Jane  Doe'], $details['directors']);
    }

    public function testReadsTheFirstCountryAndThePlainSynopsisOfAFilmPage(): void
    {
        // Arrange
        $mapper = new PatheMapper();

        // Act
        $language = $mapper->mapOriginalLanguage(['nationality' => ' Etats-Unis , France']);
        $synopsis = $mapper->mapSynopsis(['synopsis' => '<p>Un &amp; deux <b>trois</b></p> ']);

        // Assert
        self::assertSame('en', $language);
        self::assertSame('Un & deux trois', $synopsis);
    }
}
