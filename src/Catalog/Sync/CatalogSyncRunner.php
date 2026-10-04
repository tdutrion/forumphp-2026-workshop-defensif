<?php

namespace App\Catalog\Sync;

use App\Catalog\CatalogCalendar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * Runs a synchronization (command or scheduled task) then notifies the open pages.
 */
class CatalogSyncRunner
{
    public function __construct(
        private CatalogSynchronizer $synchronizer,
        private CatalogUpdatePublisher $publisher,
        private LockFactory $lockFactory,
        private CatalogCalendar $calendar,
        #[Autowire('%env(PATHE_CITIES)%')]
        private string $defaultCities,
    ) {
    }

    /**
     * @param array $citySlugs cities to synchronize; empty = the PATHE_CITIES list
     *
     * @return array|false the statistics of CatalogSynchronizer::synchronize(), or false
     */
    public function run(array $citySlugs = []): array|false
    {
        if ([] === $citySlugs) {
            $citySlugs = array_map('trim', explode(',', $this->defaultCities));
        }

        // A scheduled sync and a manual one must never write the catalog at the same time.
        $lock = $this->lockFactory->createLock('catalog-sync', 3600);
        if (!$lock->acquire()) {
            throw new \RuntimeException('A synchronization is already running.');
        }

        try {
            $stats = $this->synchronizer->synchronize($citySlugs);
            if (false !== $stats) {
                // The pages read the days to offer from this cache: compute it now, not on the next visit.
                $this->calendar->refresh();
                $this->publisher->publish($stats);
            }

            return $stats;
        } finally {
            $lock->release();
        }
    }
}
