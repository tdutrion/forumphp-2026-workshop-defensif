<?php

namespace App\Catalog\Sync;

use App\Catalog\Sync\Message\SyncCatalogMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Synchronizes the catalog every 6 hours ("scheduler_catalog" transport, "worker" Compose service).
 */
#[AsSchedule('catalog')]
class CatalogSchedule implements ScheduleProviderInterface
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('6 hours', new SyncCatalogMessage()))
            ->stateful($this->cache);
    }
}
