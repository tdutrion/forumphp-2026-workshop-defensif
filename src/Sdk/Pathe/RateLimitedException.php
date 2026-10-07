<?php

namespace App\Sdk\Pathe;

/**
 * HTTP 429: the caller is too fast. Stop, and come back later.
 */
final class RateLimitedException extends PatheApiException
{
    public function __construct(string $path)
    {
        parent::__construct($path, \sprintf('Pathé refused the request %s (HTTP 429): synchronization aborted.', $path));
    }
}
