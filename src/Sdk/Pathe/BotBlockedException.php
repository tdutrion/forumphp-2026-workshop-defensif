<?php

declare(strict_types=1);

namespace App\Sdk\Pathe;

/**
 * HTTP 403: Pathé blocks the caller (it takes it for a crawler). Trying again now only makes it worse.
 */
final class BotBlockedException extends PatheApiException
{
    public function __construct(string $path)
    {
        parent::__construct($path, \sprintf('Pathé refused the request %s (HTTP 403): synchronization aborted.', $path));
    }
}
