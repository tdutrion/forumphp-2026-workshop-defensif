<?php

namespace App\Catalog\Sync\Message;

use App\Catalog\Sync\CatalogSyncRunner;
use App\Catalog\Sync\SyncAlreadyRunning;
use App\Sdk\Pathe\BotBlockedException;
use App\Sdk\Pathe\PatheUnavailableException;
use App\Sdk\Pathe\RateLimitedException;
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
        } catch (SyncAlreadyRunning $e) {
            $this->logger->warning('Scheduled synchronization skipped', ['error' => $e->getMessage()]);

            return;
        } catch (BotBlockedException|RateLimitedException $e) {
            $this->logger->error('Scheduled synchronization aborted: Pathé refuses us', ['error' => $e->getMessage()]);

            return;
        } catch (PatheUnavailableException $e) {
            $this->logger->error('Scheduled synchronization failed: Pathé reference data unreachable', ['error' => $e->getMessage()]);

            return;
        }

        $this->logger->info('Scheduled synchronization complete', $stats);
    }
}
