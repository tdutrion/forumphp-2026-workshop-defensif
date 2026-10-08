<?php

namespace App\Tests\Integration\Catalog;

use App\Catalog\CatalogCalendar;
use App\Catalog\Repository\ShowtimeRepository;
use App\Catalog\Sync\CatalogSyncRunner;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\PatheApiBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Fake\FakePatheApi;
use App\Tests\StoresEntities;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;

final class CatalogCalendarTest extends KernelTestCase
{
    use StoresEntities;

    private function catalogCache(): CacheInterface
    {
        return self::getContainer()->get('cache.catalog');
    }

    public function testTheImportPrecomputesTheLastShowtimeOfEachDay(): void
    {
        // Arrange: showtimes tomorrow, inside the week the import reads.
        self::bootKernel();
        $tomorrow = (new \DateTimeImmutable('tomorrow', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        self::getContainer()->get(FakePatheApi::class)->serve(PatheApiBuilder::aPatheApi()
            ->withCity('dijon', 'Dijon')
            ->withCinema('cinema-pathe-dijon', 'dijon', 47.318031, 5.029935)
            ->withFilm('digger-51293', 'Digger', 129)
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', $tomorrow.' 14:00:00', 'V1S1')
            ->withShowtime('digger-51293', 'cinema-pathe-dijon', $tomorrow.' 21:40:00', 'V1S2'));

        // Act
        self::getContainer()->get(CatalogSyncRunner::class)->run(['dijon']);

        // Assert: the cache is already filled, no request recomputes it.
        $recomputed = false;
        $cached = $this->catalogCache()->get(CatalogCalendar::CACHE_KEY, static function () use (&$recomputed): array {
            $recomputed = true;

            return [];
        });
        self::assertFalse($recomputed, 'the import did not fill the cache');
        self::assertArrayHasKey($tomorrow, $cached);
    }

    public function testAMissingCacheIsRecreated(): void
    {
        // Arrange: the cache was cleared (cache:pool:clear, make db-load...).
        self::bootKernel();
        $this->catalogCache()->delete(CatalogCalendar::CACHE_KEY);

        // Act
        $dates = self::getContainer()->get(CatalogCalendar::class)->availableDates(new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')));

        // Assert
        $recomputed = false;
        $this->catalogCache()->get(CatalogCalendar::CACHE_KEY, static function () use (&$recomputed): array {
            $recomputed = true;

            return [];
        });
        self::assertSame([], $dates);
        self::assertFalse($recomputed, 'the cache was not recreated');
    }

    public function testTheCalendarIsStoredWhereTheWebAndWorkerContainersBothSeeIt(): void
    {
        // Arrange: the worker and the web server have their own var/ directory, but the same database.
        $config = Yaml::parseFile(\dirname(__DIR__, 3).'/config/packages/framework.yaml', Yaml::PARSE_CUSTOM_TAGS);

        // Act
        $adapter = $config['framework']['cache']['pools']['cache.catalog']['adapter'] ?? null;

        // Assert
        self::assertSame('cache.adapter.doctrine_dbal', $adapter, 'the catalog calendar must live in MySQL, not in a container');
    }

    public function testARefreshByTheWorkerIsSeenByTheWebServer(): void
    {
        // Arrange: one pool per container, on the same database (the migration created the table).
        self::bootKernel();
        $cinema = CinemaBuilder::aCinema()->in(CityBuilder::aCity()->build())->build();
        $film = FilmBuilder::aFilm()->build();
        $this->store($cinema->city, $cinema, $film, ShowtimeBuilder::aShowtime()->of($film)->at($cinema)->startingAt('2030-01-10 14:00:00')->build());
        $connection = self::getContainer()->get(Connection::class);
        $showtimes = self::getContainer()->get(ShowtimeRepository::class);
        $worker = new CatalogCalendar($showtimes, new DoctrineDbalAdapter($connection, 'catalog'));
        $web = new CatalogCalendar($showtimes, new DoctrineDbalAdapter($connection, 'catalog'));
        $web->availableDates(new \DateTimeImmutable('2030-01-01', new \DateTimeZone('UTC')));

        // Act: a new day is imported and the worker refreshes the calendar.
        $this->store(ShowtimeBuilder::aShowtime()->of($film)->at($cinema)->startingAt('2030-01-11 14:00:00')->build());
        $worker->refresh();

        // Assert
        self::assertSame(['2030-01-10', '2030-01-11'], $web->availableDates(new \DateTimeImmutable('2030-01-01', new \DateTimeZone('UTC'))));
    }
}
