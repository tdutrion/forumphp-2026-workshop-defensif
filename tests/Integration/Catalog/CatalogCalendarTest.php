<?php

namespace App\Tests\Integration\Catalog;

use App\Catalog\CatalogCalendar;
use App\Catalog\Sync\CatalogSyncRunner;
use App\Tests\Builder\PatheApiBuilder;
use App\Tests\Fake\FakePatheApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class CatalogCalendarTest extends KernelTestCase
{
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
}
