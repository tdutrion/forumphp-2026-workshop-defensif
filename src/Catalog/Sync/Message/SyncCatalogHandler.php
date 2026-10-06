<?php

namespace App\Catalog\Sync\Message;

use App\Catalog\Sync\CatalogSyncRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncCatalogHandler
{
    public function __construct(
        private CatalogSyncRunner $runner,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncCatalogMessage $message): void
    {
        try {
            $stats = $this->runner->run();
        } catch (\RuntimeException $e) {
            $this->logger->error('Scheduled synchronization aborted', ['error' => $e->getMessage()]);

            return;
        }

        if (false === $stats) {
            $this->logger->error('Scheduled synchronization failed: Pathé reference data unreachable');

            return;
        }

        $this->logger->info('Scheduled synchronization complete', $stats);
    }
}
