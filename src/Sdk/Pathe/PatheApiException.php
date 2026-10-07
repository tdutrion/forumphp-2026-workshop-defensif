<?php

namespace App\Sdk\Pathe;

/**
 * Pathé did not give the answer the SDK asked for. Catch the subclass that tells what to do about it.
 */
abstract class PatheApiException extends \RuntimeException
{
    public function __construct(public readonly string $path, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
