<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Sync;

use App\Catalog\Sync\CatalogSchedule;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogScheduleTest extends KernelTestCase
{
    public function testTheCatalogIsNotSynchronizedOnAScheduleUnlessItIsEnabled(): void
    {
        // Arrange: CATALOG_SCHEDULE_ENABLED is off by default, so a workshop never calls Pathé on its own.
        self::bootKernel();

        // Act
        $messages = self::getContainer()->get(CatalogSchedule::class)->getSchedule()->getRecurringMessages();

        // Assert
        self::assertSame([], $messages);
    }
}
