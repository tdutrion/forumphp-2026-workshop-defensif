<?php

namespace App\Tests\Integration\Catalog\Sync;

use App\Catalog\Entity\Cinema;
use App\Catalog\Entity\City;
use App\Catalog\Entity\Film;
use App\Catalog\Entity\Showtime;
use App\Catalog\Sync\CatalogSynchronizer;
use App\Tests\Builder\PatheApiBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Fake\FakePatheApi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogSyncTest extends KernelTestCase
{
    private function dijon(): PatheApiBuilder
    {
        return PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCity('lyon', 'Lyon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withCinema('cinema-pathe-vaise', 'lyon', 45.7746, 4.8048)
            ->withFilm('digger-51293', 'Digger', 129)
            ->withFilm('verity-50815', 'Verity', 117)
            ->withEvent('ma-mini-seance-55477', 'Ma Mini-Séance')
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 21:40:00', 'V3345S85501')
            ->withShowtime('verity-50815', 'cinema-pathe-dijon', '2026-10-04 16:30:00', 'V3345S85483');
    }

    private function synchronize(PatheApiBuilder $api): array|false
    {
        self::getContainer()->get(FakePatheApi::class)->serve($api);

        return self::getContainer()->get(CatalogSynchronizer::class)->synchronize(['dijon'], '2026-10-04');
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCopiesTheRequestedCitiesIntoTheCatalogWithInstantsInUtc(): void
    {
        // Arrange
        self::bootKernel();
        $api = $this->dijon()->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 14:00:00', 'V3345S85470', 'https://evil.example/fr/V3345S85470/booking');

        // Act
        $stats = $this->synchronize($api);

        // Assert
        self::assertSame(0, $stats['errors']);
        self::assertSame(['dijon'], array_map(static fn (City $city) => $city->getSlug(), $this->em()->getRepository(City::class)->findAll()));
        $cinema = $this->em()->find(Cinema::class, 'cinema-pathe-dijon');
        self::assertEqualsWithDelta(47.318031, $cinema->getLatitude(), 0.000001, 'Pathé "x" is the latitude');
        self::assertSame('Europe/Paris', $cinema->getTimezone());
        self::assertSame('FR', $cinema->getCountry());
        self::assertNull($this->em()->find(Film::class, 'ma-mini-seance-55477'), 'events are not films');
        $late = $this->em()->find(Showtime::class, 'V3345S85501');
        self::assertSame('2026-10-04 19:40:00', $late->getStartsAt()->format('Y-m-d H:i:s'), '21:40 in Paris (summer time) is 19:40 UTC');
        self::assertSame('2026-10-04 22:09:00', $late->getEndsAt()->format('Y-m-d H:i:s'), 'the film ends at 00:09, Paris time');
        self::assertSame('2026-10-04', $late->getLocalDate()->format('Y-m-d'), 'it still belongs to October 4 locally');
        self::assertNull($this->em()->find(Showtime::class, 'V3345S85470'), 'a booking link outside s.pathe.fr is never stored');
    }

    public function testASecondSyncRemovesTheShowtimesThatDisappeared(): void
    {
        // Arrange
        self::bootKernel();
        $this->synchronize($this->dijon());
        $withoutVerity = PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withFilm('digger-51293', 'Digger', 129)
            ->withFilm('verity-50815', 'Verity', 117)
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 21:40:00', 'V3345S85501');

        // Act
        $stats = $this->synchronize($withoutVerity);

        // Assert
        $this->em()->clear();
        self::assertSame(1, $stats['deleted']);
        self::assertSame(1, $this->em()->getRepository(Showtime::class)->count([]));
        self::assertNotNull($this->em()->find(Showtime::class, 'V3345S85501'));
    }

    public function testKeepsTheShowtimesOfACinemaWhoseScheduleCouldNotBeFullyRead(): void
    {
        // Arrange
        self::bootKernel();
        $this->synchronize($this->dijon());
        $partlyFailing = $this->dijon()->failing('show/verity-50815/showtimes/cinema-pathe-dijon', 500);

        // Act
        $stats = $this->synchronize($partlyFailing);

        // Assert
        $this->em()->clear();
        self::assertSame(1, $stats['errors']);
        self::assertSame(0, $stats['deleted']);
        self::assertNotNull($this->em()->find(Showtime::class, 'V3345S85483'));
    }

    public function testOnlySecurePosterLinksAreStored(): void
    {
        // Arrange
        self::bootKernel();
        $api = $this->dijon()
            ->withFilm('safe-1', 'Safe', 90, 'https://cdn.pathe.fr/posters/safe.jpg')
            ->withFilm('plain-2', 'Plain', 90, 'http://cdn.pathe.fr/posters/plain.jpg')
            ->withFilm('script-3', 'Script', 90, 'javascript:alert(1)');

        // Act
        $this->synchronize($api);

        // Assert
        self::assertSame('https://cdn.pathe.fr/posters/safe.jpg', $this->em()->find(Film::class, 'safe-1')->getPosterUrl());
        self::assertNull($this->em()->find(Film::class, 'plain-2')->getPosterUrl());
        self::assertNull($this->em()->find(Film::class, 'script-3')->getPosterUrl());
    }

    public function testPastShowtimesAreRemoved(): void
    {
        // Arrange: a showtime of September stayed in the catalog.
        self::bootKernel();
        $this->synchronize($this->dijon());
        $cinema = $this->em()->find(Cinema::class, 'cinema-pathe-dijon');
        $film = $this->em()->find(Film::class, 'digger-51293');
        $old = ShowtimeBuilder::aShowtime()->withId('V1S999')->of($film)->at($cinema)->startingAt('2026-09-01 20:00:00')->build();
        $this->em()->persist($old);
        $this->em()->flush();

        // Act
        $stats = $this->synchronize($this->dijon());

        // Assert
        $this->em()->clear();
        self::assertNull($this->em()->find(Showtime::class, 'V1S999'));
        self::assertGreaterThanOrEqual(1, $stats['deleted']);
        self::assertNotNull($this->em()->find(Showtime::class, 'V3345S85501'), 'today\'s showtimes stay');
    }
}
