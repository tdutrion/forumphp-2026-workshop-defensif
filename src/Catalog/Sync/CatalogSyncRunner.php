<?php

namespace App\Catalog\Sync;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs a synchronization (command or scheduled task) then notifies the open pages.
 */
class CatalogSyncRunner
{
    public function __construct(
        private CatalogSynchronizer $synchronizer,
        private CatalogUpdatePublisher $publisher,
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

        $stats = $this->synchronizer->synchronize($citySlugs);
        if (false !== $stats) {
            $this->publisher->publish($stats);
        }

        return $stats;
    }
}
