<?php

namespace App\Catalog\Sync;

use App\Catalog\Sync\Message\SyncCatalogMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * When CATALOG_SCHEDULE_ENABLED is on, synchronizes the catalog every 6 hours ("scheduler_catalog" transport, "worker" Compose service).
 */
#[AsSchedule('catalog')]
class CatalogSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        #[Autowire('%env(bool:CATALOG_SCHEDULE_ENABLED)%')]
        private bool $enabled,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = (new Schedule())->stateful($this->cache);
        // Off by default: during the workshop the application must never call Pathé.
        if ($this->enabled) {
            $schedule->add(RecurringMessage::every('6 hours', new SyncCatalogMessage()));
        }

        return $schedule;
    }
}
