<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Catalog\Repository\ShowtimeRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Days that can still be planned. The last bookable time of each day is computed by the import
 * (refresh()) and kept in the "cache.catalog" pool; a missing cache is computed again on demand.
 */
class CatalogCalendar
{
    public const string CACHE_KEY = 'catalog.last_bookable_by_day';

    public function __construct(
        private ShowtimeRepository $showtimeRepository,
        #[Autowire(service: 'cache.catalog')]
        private CacheInterface $cache,
    ) {
    }

    /**
     * @param \DateTimeImmutable $now current instant
     *
     * @return list<string> local days ('Y-m-d') that still have a bookable showtime after $now, in order
     */
    public function availableDates(\DateTimeImmutable $now): array
    {
        $utcNow = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $lastBookableByDay = $this->cache->get(self::CACHE_KEY, fn () => $this->showtimeRepository->findLastBookableByDay());

        $dates = [];
        foreach ($lastBookableByDay as $day => $lastBookable) {
            if ($lastBookable > $utcNow) {
                $dates[] = (string) $day;
            }
        }

        return $dates;
    }

    /**
     * Computes the calendar again from the catalog (after an import or a date shift).
     */
    public function refresh(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, fn () => $this->showtimeRepository->findLastBookableByDay());
    }
}
