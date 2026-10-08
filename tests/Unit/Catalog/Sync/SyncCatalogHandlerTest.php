<?php

namespace App\Tests\Unit\Catalog\Sync;

use App\Catalog\Sync\CatalogSyncRunner;
use App\Catalog\Sync\Message\SyncCatalogHandler;
use App\Catalog\Sync\Message\SyncCatalogMessage;
use App\Catalog\Sync\SyncAlreadyRunning;
use App\Sdk\Pathe\BotBlockedException;
use App\Sdk\Pathe\PatheUnavailableException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class SyncCatalogHandlerTest extends TestCase
{
    /**
     * @return array{0: SyncCatalogHandler, 1: \ArrayObject<int, array{0: string, 1: string}>}
     */
    private function handlerWhere(\Closure|array $run): array
    {
        $runner = $this->createStub(CatalogSyncRunner::class);
        $runner->method('run')->willReturnCallback(static fn () => $run instanceof \Closure ? $run() : $run);
        $logs = new \ArrayObject();
        $logger = new class($logs) extends AbstractLogger {
            public function __construct(private \ArrayObject $logs)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        return [new SyncCatalogHandler($runner, $logger), $logs];
    }

    public function testALockedSynchronizationIsSkippedWithAWarning(): void
    {
        // Arrange
        [$handler, $logs] = $this->handlerWhere(static fn () => throw new SyncAlreadyRunning());

        // Act
        $handler(new SyncCatalogMessage());

        // Assert
        self::assertSame([['warning', 'Scheduled synchronization skipped']], $logs->getArrayCopy());
    }

    public function testBeingBlockedIsAnError(): void
    {
        // Arrange
        [$handler, $logs] = $this->handlerWhere(static fn () => throw new BotBlockedException('cities'));

        // Act
        $handler(new SyncCatalogMessage());

        // Assert
        self::assertSame([['error', 'Scheduled synchronization aborted: Pathé refuses us']], $logs->getArrayCopy());
    }

    public function testUnreachableReferenceDataIsAnError(): void
    {
        // Arrange
        [$handler, $logs] = $this->handlerWhere(static fn () => throw new PatheUnavailableException('cities', 'down'));

        // Act
        $handler(new SyncCatalogMessage());

        // Assert
        self::assertSame([['error', 'Scheduled synchronization failed: Pathé reference data unreachable']], $logs->getArrayCopy());
    }

    public function testACompleteSynchronizationIsLogged(): void
    {
        // Arrange
        [$handler, $logs] = $this->handlerWhere(['cities' => 1]);

        // Act
        $handler(new SyncCatalogMessage());

        // Assert
        self::assertSame([['info', 'Scheduled synchronization complete']], $logs->getArrayCopy());
    }
}
