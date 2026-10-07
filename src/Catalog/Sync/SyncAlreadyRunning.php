<?php

namespace App\Catalog\Sync;

/**
 * Another synchronization (the scheduled task or a command) holds the lock of the catalog.
 */
final class SyncAlreadyRunning extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('A synchronization is already running.');
    }
}
