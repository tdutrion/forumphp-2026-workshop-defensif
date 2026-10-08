<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Sync;

use App\Catalog\BookingStatus;
use App\Catalog\CatalogCalendar;
use App\Catalog\Entity\Cinema;
use App\Catalog\Entity\City;
use App\Catalog\Entity\Film;
use App\Catalog\Entity\Showtime;
use App\Catalog\ShowtimeVersion;
use App\Catalog\Sync\CatalogSynchronizer;
use App\Catalog\Sync\CatalogSyncRunner;
use App\Tests\Builder\PatheApiBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\WikidataApiBuilder;
use App\Tests\Fake\FakePatheApi;
use App\Tests\Fake\FakeWikidataApi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

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

    private function synchronize(PatheApiBuilder $api): array
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
        self::assertEqualsWithDelta(47.318031, $cinema->coordinates?->latitude, 0.000001, 'Pathé "x" is the latitude');
        self::assertSame('Europe/Paris', $cinema->timezone->getName());
        self::assertSame('FR', $cinema->country->value);
        self::assertNull($this->em()->find(Film::class, 'ma-mini-seance-55477'), 'events are not films');
        $late = $this->em()->find(Showtime::class, 'V3345S85501');
        self::assertSame('2026-10-04 19:40:00', $late->startsAt->format('Y-m-d H:i:s'), '21:40 in Paris (summer time) is 19:40 UTC');
        self::assertSame('2026-10-04 22:09:00', $late->endsAt->format('Y-m-d H:i:s'), 'the film ends at 00:09, Paris time');
        self::assertSame('2026-10-04', $late->localDate->format('Y-m-d'), 'it still belongs to October 4 locally');
        self::assertSame(244, $late->capacity, 'Pathé sends "244", the catalog keeps a number of seats');
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

    public function testACinemaThatLeftTheListOfPatheIsClosed(): void
    {
        // Arrange: Dijon and Lyon were synchronized with a second cinema in Dijon.
        self::bootKernel();
        self::getContainer()->get(FakePatheApi::class)->serve($this->dijon()
            ->withCinema('cinema-pathe-dijon-sud', 'dijon', 47.30, 5.04)
            ->withShowtime('digger-51293', 'cinema-pathe-dijon-sud', '2026-10-04 20:00:00', 'V3346S1'));
        self::getContainer()->get(CatalogSynchronizer::class)->synchronize(['dijon', 'lyon'], '2026-10-04');
        $withoutDijonSudNorVaise = PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCity('lyon', 'Lyon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withFilm('digger-51293', 'Digger', 129)
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 21:40:00', 'V3345S85501');

        // Act: only Dijon is synchronized this time.
        $this->synchronize($withoutDijonSudNorVaise);

        // Assert
        $this->em()->clear();
        self::assertFalse($this->em()->find(Cinema::class, 'cinema-pathe-dijon-sud')->open);
        self::assertNull($this->em()->find(Showtime::class, 'V3346S1'), 'its showtimes are no longer offered');
        self::assertTrue($this->em()->find(Cinema::class, 'cinema-pathe-dijon')->open);
        self::assertTrue($this->em()->find(Cinema::class, 'cinema-pathe-vaise')->open, 'Lyon was not synchronized');
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

    public function testShowtimesOfAnUnexpectedShapeCountAsAFailedRead(): void
    {
        // Arrange
        self::bootKernel();
        $this->synchronize($this->dijon());
        $unexpected = $this->dijon()->failing('show/verity-50815/showtimes/cinema-pathe-dijon', 200, '[{"time": "2026-10-04 16:30:00"}]');

        // Act
        $stats = $this->synchronize($unexpected);

        // Assert
        $this->em()->clear();
        self::assertSame(1, $stats['errors']);
        self::assertSame(0, $stats['deleted']);
        self::assertNotNull($this->em()->find(Showtime::class, 'V3345S85483'));
    }

    public function testAShowtimeWithAnUnknownVersionOrStatusIsSkipped(): void
    {
        // Arrange
        self::bootKernel();
        $api = $this->dijon()
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 14:00:00', 'V3345S85470', version: 'vx')
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 16:00:00', 'V3345S85471', status: 'complet')
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', '2026-10-04 18:00:00', 'V3345S85472', version: 'vost', status: 'soldout');

        // Act
        $stats = $this->synchronize($api);

        // Assert
        self::assertSame(0, $stats['errors']);
        self::assertNull($this->em()->find(Showtime::class, 'V3345S85470'), 'unknown version');
        self::assertNull($this->em()->find(Showtime::class, 'V3345S85471'), 'unknown status');
        $soldOut = $this->em()->find(Showtime::class, 'V3345S85472');
        self::assertSame(ShowtimeVersion::Vost, $soldOut->version);
        self::assertSame(BookingStatus::SoldOut, $soldOut->status);
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

    public function testTheOriginalLanguageOfAFilmComesFromItsNationality(): void
    {
        // Arrange
        self::bootKernel();
        $api = $this->dijon()
            ->withFilm('la-bataille-1', 'La Bataille', 120, null, 'France')
            ->withFilm('digger-51293', 'Digger', 129, null, 'Etats-Unis')
            ->withShowtime('la-bataille-1', 'cinema-pathe-dijon', '2026-10-04 18:00:00', 'V3345S90001');

        // Act
        $this->synchronize($api);

        // Assert
        $this->em()->clear();
        self::assertSame('fr', $this->em()->find(Film::class, 'la-bataille-1')->getOriginalLanguage());
        self::assertSame('en', $this->em()->find(Film::class, 'digger-51293')->getOriginalLanguage());
        self::assertSame('fr', $this->em()->find(Cinema::class, 'cinema-pathe-dijon')->language, 'the language of the chain');
    }

    public function testTheSynopsisOfAFilmComesFromItsPageAsPlainText(): void
    {
        // Arrange
        self::bootKernel();
        $api = $this->dijon()->withSynopsis('digger-51293', '<p>L\'homme le plus <b>puissant</b> du monde.</p>');

        // Act
        $this->synchronize($api);

        // Assert
        $this->em()->clear();
        self::assertSame('L\'homme le plus puissant du monde.', $this->em()->find(Film::class, 'digger-51293')->getSynopsis());
    }

    public function testEverySynchronizedFilmHasItsOwnWork(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $this->synchronize($this->dijon());

        // Assert
        $this->em()->clear();
        $digger = $this->em()->find(Film::class, 'digger-51293');
        $verity = $this->em()->find(Film::class, 'verity-50815');
        self::assertSame('Digger', $digger->getWork()->getOriginalTitle());
        self::assertNotEquals($digger->getWork()->getId(), $verity->getWork()->getId());
    }

    public function testTheWorksOfThePlayingFilmsAreDescribedAndLinked(): void
    {
        // Arrange
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q130000001', 'Digger', 2026, ['Alejandro González Iñárritu'], 'tt30000001'));
        $api = $this->dijon()->withDetails('digger-51293', 'Digger', 2026, 'Alejandro González Iñárritu');

        // Act
        $stats = $this->synchronize($api);

        // Assert
        $this->em()->clear();
        $work = $this->em()->find(Film::class, 'digger-51293')->getWork();
        self::assertSame(['Alejandro González Iñárritu'], $work->getDirectors());
        self::assertSame('tt30000001', $work->getImdbId());
        self::assertSame(1, $stats['linked']);
    }

    public function testAnUnavailableWikidataDoesNotStopTheSynchronization(): void
    {
        // Arrange
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve(WikidataApiBuilder::aWikidataApi()->failing());

        // Act
        $stats = $this->synchronize($this->dijon());

        // Assert
        self::assertNotFalse($stats);
        self::assertSame(0, $stats['linked']);
        self::assertNotNull($this->em()->find(Showtime::class, 'V3345S85501'));
    }

    public function testAnEmptyCityListSynchronizesEveryCityOfTheChain(): void
    {
        // Arrange: PATHE_CITIES set to nothing.
        self::bootKernel();
        self::getContainer()->get(FakePatheApi::class)->serve($this->dijon());
        $container = self::getContainer();
        $runner = new CatalogSyncRunner(
            $container->get(CatalogSynchronizer::class),
            $container->get(LockFactory::class),
            $container->get(CatalogCalendar::class),
            [],
        );

        // Act
        $stats = $runner->run();

        // Assert
        self::assertSame(2, $stats['cities']);
        $cities = array_map(static fn (City $city) => $city->getSlug(), $this->em()->getRepository(City::class)->findAll());
        sort($cities);
        self::assertSame(['dijon', 'lyon'], $cities);
    }

    public function testTheLockIsKeptWhileTheSynchronizationRuns(): void
    {
        // Arrange: a sync of every city can outlast the lifetime of the lock; the store counts the extensions.
        self::bootKernel();
        self::getContainer()->get(FakePatheApi::class)->serve($this->dijon());
        $store = new class extends InMemoryStore {
            public int $extensions = 0;

            public function putOffExpiration(Key $key, float $ttl): void
            {
                ++$this->extensions;
                parent::putOffExpiration($key, $ttl);
            }
        };
        $container = self::getContainer();
        $runner = new CatalogSyncRunner(
            $container->get(CatalogSynchronizer::class),
            new LockFactory($store),
            $container->get(CatalogCalendar::class),
            [],
        );

        // Act
        $runner->run(['dijon', 'lyon']);

        // Assert: once when acquired, then at least once per cinema.
        self::assertGreaterThanOrEqual(1 + 2, $store->extensions);
    }
}
