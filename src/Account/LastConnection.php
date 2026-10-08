<?php

declare(strict_types=1);

namespace App\Account;

/**
 * A user keeps at least one way to sign in: their last connection cannot be removed.
 */
final class LastConnection extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The last connection of an account cannot be removed.');
    }
}
