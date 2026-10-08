<?php

declare(strict_types=1);

namespace App\Catalog\Sync;

/**
 * Tells nobody: for a synchronization that no page listens to (a script, a test).
 */
final class NullCatalogPublisher implements CatalogPublisher
{
    #[\Override]
    public function publish(array $stats): void
    {
    }
}
