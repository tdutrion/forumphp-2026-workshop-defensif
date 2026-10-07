<?php

declare(strict_types=1);

namespace App\Catalog\Sync;

use App\Catalog\CatalogCalendar;
use App\Sdk\Pathe\BotBlockedException;
use App\Sdk\Pathe\PatheUnavailableException;
use App\Sdk\Pathe\RateLimitedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * Runs a synchronization (command or scheduled task) then notifies the open pages.
 */
class CatalogSyncRunner
{
    public function __construct(
        private CatalogSynchronizer $synchronizer,
        private LockFactory $lockFactory,
        private CatalogCalendar $calendar,
        /** @var list<string> */
        #[Autowire('%env(csv:PATHE_CITIES)%')]
        private array $defaultCities,
        private CatalogPublisher $publisher = new NullCatalogPublisher(),
    ) {
    }

    /**
     * @param list<string> $citySlugs cities to synchronize; empty = the PATHE_CITIES list (itself empty = every city)
     *
     * @return array{cities: int, cinemas: int, films: int, showtimes: int, deleted: int, errors: int, linked: int} the statistics of CatalogSynchronizer::synchronize()
     *
     * @throws SyncAlreadyRunning
     * @throws PatheUnavailableException|BotBlockedException|RateLimitedException see CatalogSynchronizer::synchronize()
     */
    public function run(array $citySlugs = []): array
    {
        if ([] === $citySlugs) {
            $citySlugs = $this->defaultCities;
        }

        // A scheduled sync and a manual one must never write the catalog at the same time. A sync of every
        // city can take longer than the lifetime of the lock: the synchronizer extends it as it goes.
        $lock = $this->lockFactory->createLock('catalog-sync', 3600);
        if (!$lock->acquire()) {
            throw new SyncAlreadyRunning();
        }

        try {
            $stats = $this->synchronizer->synchronize($citySlugs, stillRunning: static fn () => $lock->refresh());
            // The pages read the days to offer from this cache: compute it now, not on the next visit.
            $this->calendar->refresh();
            $this->publisher->publish($stats);

            return $stats;
        } finally {
            $lock->release();
        }
    }
}
